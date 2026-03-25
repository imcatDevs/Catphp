# 운영 — Sitemap · Backup · Webhook · Swoole · Telegram

CatPHP의 운영·배포 계층. 사이트맵 생성, DB 백업, 웹훅, 고성능 서버, 텔레그램 봇을 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Sitemap | `sitemap()` | `Cat\Sitemap` | 279 |
| Backup | `backup()` | `Cat\Backup` | 380 |
| Webhook | `webhook()` | `Cat\Webhook` | 328 |
| Swoole | `swoole()` | `Cat\Swoole` | 1246 |
| Telegram | `telegram()` | `Cat\Telegram` | 199 |

---

## 목차

1. [Sitemap — XML 사이트맵](#1-sitemap--xml-사이트맵)
2. [Backup — DB 백업/복원](#2-backup--db-백업복원)
3. [Webhook — 웹훅 발송/수신](#3-webhook--웹훅-발송수신)
4. [Swoole — 고성능 비동기 서버](#4-swoole--고성능-비동기-서버)
5. [Telegram — 텔레그램 Bot API](#5-telegram--텔레그램-bot-api)

---

## 1. Sitemap — XML 사이트맵

사이트맵 프로토콜 0.9 스펙 준수. URL 사이트맵 + 사이트맵 인덱스.

### Sitemap 설정

```php
'sitemap' => [
    'base_url'  => 'https://example.com',
    'cache_ttl' => 3600,  // 캐시 TTL (초)
],
```

### Sitemap URL 추가

```php
// 단일 URL
sitemap()->url('/', '2024-01-01', 'daily', 1.0)
    ->url('/about', '2024-01-01', 'monthly', 0.8)
    ->output();  // Content-Type: application/xml + exit

// 복수 URL 일괄
sitemap()->urls([
    ['loc' => '/blog', 'lastmod' => '2024-06-01', 'changefreq' => 'weekly', 'priority' => 0.9],
    ['loc' => '/contact', 'changefreq' => 'yearly'],
])->output();
```

### Sitemap DB 연동

```php
// DB 쿼리 결과에서 자동 생성
$posts = db()->table('posts')->select('slug', 'updated_at')->get();

sitemap()
    ->url('/', '2024-01-01', 'daily', 1.0)
    ->fromQuery($posts, '/post/{slug}', 'updated_at', 'weekly', 0.7)
    ->output();
```

- `{slug}` — 행의 `slug` 컬럼 값으로 치환
- `updated_at` — 날짜 컬럼 (lastmod)

### Sitemap 인덱스

```php
// 사이트맵 인덱스 (대규모 사이트)
sitemap()->index([
    '/sitemap-posts.xml',
    '/sitemap-pages.xml',
    ['loc' => '/sitemap-products.xml', 'lastmod' => '2024-06-01'],
])->output();
```

### Sitemap 파일 저장

```php
sitemap()->url('/')->save('Public/sitemap.xml');  // 파일로 저장
sitemap()->url('/')->render();                     // XML 문자열 반환
sitemap()->url('/')->count();                      // URL 수
```

### Sitemap 검증

- **changefreq**: `always|hourly|daily|weekly|monthly|yearly|never` 외 값은 `InvalidArgumentException`
- **priority**: 0.0~1.0 자동 클램핑
- **URL 수**: 50,000개 초과 시 `RuntimeException` (인덱스로 분할 권장)
- **캐시**: `output()` 시 Cache 도구 자동 저장

### Sitemap CLI

```bash
php cli.php sitemap:generate --table=posts --url=/post/{slug}
```

---

## 2. Backup — DB 백업/복원

MySQL(mysqldump), PostgreSQL(pg_dump), SQLite(파일 복사) 지원.

### Backup 설정

```php
'backup' => [
    'path'      => dirname(__DIR__) . '/storage/backup',
    'keep_days' => 30,       // 자동 정리 보관 일수
    'compress'  => false,    // gzip 압축
],
```

### Backup 실행

```php
// 자동 파일명 (YYYYMMDD_HHmmss_driver.sql)
$path = backup()->database();

// 지정 경로
$path = backup()->database('custom/backup.sql');

// gzip 압축 (config compress=true)
$path = backup()->database();  // *.sql.gz
```

### Backup 복원

```php
backup()->restore('storage/backup/20240601_120000_mysql.sql');
backup()->restore('storage/backup/20240601_120000_mysql.sql.gz');  // gzip 자동 감지
```

### Backup 관리

```php
// 목록 (최신순)
$list = backup()->list();
// [['name' => '20240601_...', 'path' => '...', 'size' => 12345, 'date' => '2024-06-01 12:00:00'], ...]

// 최신 백업
$latest = backup()->latest();  // 파일 경로 또는 null

// 오래된 파일 정리
$deleted = backup()->clean();      // config keep_days 기준
$deleted = backup()->clean(7);     // 7일 이전 삭제

// 백업 디렉토리 경로
backup()->getPath();
```

### Backup CLI

```bash
php cli.php db:backup                # 백업 실행
php cli.php db:restore               # 최신 백업 복원
php cli.php db:backup:list           # 백업 목록
php cli.php db:backup:clean          # 오래된 파일 정리
```

### Backup 드라이버별 동작

| 기능 | MySQL | PostgreSQL | SQLite |
| --- | --- | --- | --- |
| 백업 | `mysqldump` | `pg_dump` | `copy()` |
| 복원 | `mysql < file` | `psql < file` | `copy()` |
| 압축 | `\| gzip` | `\| gzip` | 미지원 |
| 비밀번호 | `--password` | `PGPASSWORD` 환경변수 | 해당 없음 |
| 옵션 | `--single-transaction --routines --triggers` | `--format=plain --no-owner` | WAL 체크포인트 + wal/shm 복사 |

### Backup 보안

- **경로 트래버설 방어**: `restore()` — `realpath` + `backupPath` 내부 제한
- **복원 전 안전 백업**: SQLite `restore()` 시 현재 DB를 `.before_restore.*`로 자동 백업
- **인수 이스케이프**: `escapeshellarg()` 모든 셸 파라미터

---

## 3. Webhook — 웹훅 발송/수신

HMAC-SHA256 서명 기반 Webhook 발송 + 수신 검증.

### Webhook 설정

```php
'webhook' => [
    'secret'      => 'my-webhook-secret',
    'timeout'     => 10,     // HTTP 타임아웃 (초)
    'retry'       => 2,      // 실패 시 재시도 횟수
    'retry_delay' => 1,      // 재시도 간 대기 (초)
    'log'         => true,   // 발송/수신 로깅
],
```

### Webhook 발송

```php
// 기본 발송
$result = webhook()
    ->to('https://example.com/hook')
    ->payload(['event' => 'user.created', 'data' => $user])
    ->send();

$result->ok();        // true (2xx)
$result->status();    // 200
$result->body();      // 응답 본문
$result->json();      // JSON 디코딩
$result->attempts();  // 시도 횟수

// HMAC 서명 첨부 (X-Webhook-Signature 헤더)
$result = webhook()
    ->to('https://example.com/hook')
    ->secret('my-secret')
    ->payload($data)
    ->send();

// 커스텀 헤더
$result = webhook()
    ->to('https://example.com/hook')
    ->header('X-Event', 'user.created')
    ->payload($data)
    ->send();
```

### Webhook 수신

```php
// 수신 (라우트 핸들러에서)
$wh = webhook()->receive();

// 서명 검증
if ($wh->isValid('my-secret')) {
    $data = $wh->getPayload();   // JSON 디코딩된 배열
    $raw  = $wh->getRawBody();   // 원본 body
    // 처리...
}
```

- **서명 형식**: `sha256=...` 접두사 자동 처리 (GitHub 스타일 호환)
- **헤더**: `X-Webhook-Signature` 또는 `X-Hub-Signature-256`

### Webhook 유틸

```php
// HMAC 서명 생성
$sig = webhook()->sign($payload, 'secret');

// 서명 헤더 문자열
$header = webhook()->signatureHeader($payload, 'secret');
// 'sha256=abc123...'
```

### Webhook 보안

- **URL 검증**: `to()` — `http://` 또는 `https://`만 허용 (SSRF 차단)
- **리다이렉트 비활성화**: `CURLOPT_FOLLOWLOCATION = false`
- **CRLF 인젝션 방어**: `header()` — `\r`, `\n`, `\0` 제거
- **타이밍 안전 비교**: `hash_equals()` 서명 검증
- **재시도**: 지수 대기 없이 고정 간격 재시도 (config `retry_delay`)

### Webhook WebhookResult 객체

| 메서드 | 반환 | 설명 |
| --- | --- | --- |
| `ok()` | `bool` | 2xx 여부 |
| `status()` | `int` | HTTP 상태 코드 |
| `body()` | `string` | 응답 본문 |
| `json()` | `?array` | JSON 디코딩 |
| `attempts()` | `int` | 시도 횟수 |

---

## 4. Swoole — 고성능 비동기 서버

Swoole 확장 기반 상주 프로세스 서버. HTTP + WebSocket + 코루틴 + 연결 풀 + 태스크 워커.

### Swoole 설정

```php
'swoole' => [
    'host'             => '0.0.0.0',
    'port'             => 9501,
    'mode'             => 'process',      // process | base
    'worker_num'       => 4,              // CPU 코어 수 기본
    'task_worker_num'  => 4,
    'max_request'      => 10000,          // 메모리 누수 방지
    'max_conn'         => 10000,
    'daemonize'        => false,
    'pid_file'         => 'storage/swoole.pid',
    'log_file'         => 'storage/logs/swoole.log',
    'enable_coroutine' => true,
    'hot_reload'       => false,          // 개발 전용
    'hot_reload_paths' => ['catphp', 'Public', 'config'],
    'pool'             => [
        'db'    => 4,                     // DB 연결 풀 크기
        'redis' => 4,                     // Redis 연결 풀 크기
    ],
],
```

### Swoole HTTP 서버

```php
// 기본 시작 (CatPHP Router 자동 통합)
swoole()->http()->start();

// 설정 체이닝
swoole()
    ->http()
    ->listen('0.0.0.0', 8080)
    ->workers(8)
    ->taskWorkers(4)
    ->daemonize()
    ->staticFiles(dirname(__DIR__) . '/Public')
    ->start();
```

### Swoole WebSocket 서버

```php
swoole()
    ->websocket()
    ->onWsOpen(function (int $fd) {
        swoole()->push($fd, json_encode(['type' => 'welcome']));
    })
    ->onWsMessage(function (int $fd, string $data) {
        $msg = json_decode($data, true);
        // 에코
        swoole()->push($fd, 'echo: ' . $data);
    })
    ->onWsClose(function (int $fd) {
        // 자동 룸 퇴장 처리됨
    })
    ->start();
```

### Swoole WebSocket 메시지

```php
// 특정 클라이언트에 전송
swoole()->push($fd, 'hello');
swoole()->push($fd, ['type' => 'data', 'value' => 42]);  // JSON 자동 인코딩

// 전체 브로드캐스트
swoole()->broadcast('공지사항');
swoole()->broadcast('메시지', excludeFd: $senderFd);  // 발신자 제외
```

### Swoole 룸 관리

```php
// 룸 참가/퇴장
swoole()->join($fd, 'chat-room-1');
swoole()->leave($fd, 'chat-room-1');
swoole()->leaveAll($fd);  // 모든 룸에서 퇴장 (연결 종료 시 자동)

// 룸 브로드캐스트
swoole()->toRoom('chat-room-1', '새 메시지', excludeFd: $fd);

// 룸 정보
swoole()->roomMembers('chat-room-1');  // [1, 2, 3] (FD 목록)
swoole()->roomList();                   // [['room' => 'chat-room-1', 'count' => 3], ...]
```

### Swoole 태스크 워커

```php
// 핸들러 등록
swoole()->handle('email', function (array $payload) {
    mailer()->to($payload['to'])->subject($payload['subject'])->body($payload['body'])->send();
});

// 비동기 태스크 전송 (즉시 반환)
swoole()->task('email', ['to' => 'a@b.com', 'subject' => '알림', 'body' => '내용']);

// 동기 태스크 (결과 대기)
$result = swoole()->taskWait('process', $data, timeout: 5.0);

// 병렬 태스크 (여러 태스크 동시 실행)
$results = swoole()->taskCo([
    ['name' => 'resize', 'payload' => ['path' => '/img1.jpg']],
    ['name' => 'resize', 'payload' => ['path' => '/img2.jpg']],
], timeout: 10.0);
```

### Swoole 코루틴

```php
// 코루틴 생성
swoole()->co(function () {
    $db = swoole()->poolGet('db');
    // ... 쿼리 실행
    swoole()->poolPut('db', $db);
});

// 비블로킹 sleep
swoole()->sleep(1.5);

// 채널 (프로듀서-컨슈머)
$ch = swoole()->channel(10);

// WaitGroup (여러 코루틴 완료 대기)
$wg = swoole()->waitGroup();

// 병렬 실행 + 결과 수집
$results = swoole()->parallel([
    'users'  => fn() => db()->table('users')->count(),
    'posts'  => fn() => db()->table('posts')->count(),
    'orders' => fn() => db()->table('orders')->count(),
], timeout: 5.0);
// ['users' => ['key' => 'users', 'value' => 100, 'error' => null], ...]
```

### Swoole 연결 풀

```php
// 자동 초기화 (config 기반)
swoole()->createDbPool();      // DB 연결 풀
swoole()->createRedisPool();   // Redis 연결 풀

// 커스텀 풀
swoole()->createPool('custom', function () {
    return new \PDO('mysql:host=remote;dbname=analytics', 'user', 'pass');
}, size: 8);

// 사용
$pdo = swoole()->poolGet('db', timeout: 3.0);
// ... 쿼리 실행
swoole()->poolPut('db', $pdo);

// 풀 상태
swoole()->poolStats('db');
// ['name' => 'db', 'exists' => true, 'capacity' => 4, 'available' => 3, 'in_use' => 1]
```

### Swoole 타이머

```php
// 반복 타이머 (밀리초)
$id = swoole()->tick(1000, fn() => logger()->info('매 1초'));

// 1회 타이머
swoole()->after(5000, fn() => logger()->info('5초 후 1회'));

// 타이머 해제
swoole()->clearTimer($id);
swoole()->clearAllTimers();
```

### Swoole 서버 생명주기

```php
swoole()
    ->onBoot(function ($svr, int $workerId) {
        // 워커 시작 시 실행 (라우트 정의 등)
    })
    ->onStart(fn($svr) => logger()->info('서버 시작'))
    ->onWorkerStart(fn($svr, $id) => null)
    ->onWorkerStop(fn($svr, $id) => null)
    ->onShutdown(fn($svr) => logger()->info('서버 종료'))
    ->use(function ($req, $res) {
        // 미들웨어 (false 반환 시 요청 중단)
        return true;
    })
    ->start();
```

### Swoole SSL (HTTPS/WSS)

```php
swoole()
    ->http()
    ->ssl('/path/to/cert.pem', '/path/to/key.pem')
    ->start();
```

### Swoole CLI

```bash
php cli.php swoole:start     # 서버 시작
php cli.php swoole:stop      # Graceful Shutdown (SIGTERM)
php cli.php swoole:reload    # 워커 리로드 (SIGUSR1)
php cli.php swoole:status    # 실행 상태 확인
```

### Swoole 내부 동작

- **요청 격리**: 매 요청마다 `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, `$_SERVER` 초기화
- **Router 브릿지**: 출력 버퍼 캡처 + `router()->dispatch()` → Swoole 응답 전송
- **JSON 요청**: `Content-Type: application/json` 자동 파싱 → `input()` 통합
- **Hot Reload**: inotify 기반 (미설치 시 2초 폴링 폴백), 서브디렉토리 재귀 감시
- **PID 파일**: `start()` 시 기록, `stop()` / `shutdown` 시 삭제
- **연결 풀 정리**: 워커 종료 시 Redis `close()` + 채널 해제

---

## 5. Telegram — 텔레그램 Bot API

텔레그램 Bot API 래퍼. 메시지, 사진, 파일, 인라인 키보드.

### Telegram 설정

```php
'telegram' => [
    'bot_token'  => '123456:ABC-DEF...',
    'chat_id'    => '12345678',       // 기본 채팅 ID
    'admin_chat' => '87654321',       // 관리자 채팅 ID
],
```

### Telegram 메시지 전송

```php
// 기본 수신자 (config chat_id)
telegram()->message('서버 상태 정상')->send();

// 수신자 지정
telegram()->to('12345678')->message('알림 메시지')->send();

// HTML 포맷
telegram()->to($chatId)
    ->html('<b>긴급</b> 서버 다운 감지')
    ->send();

// MarkdownV2 포맷
telegram()->to($chatId)
    ->markdown('*긴급* 서버 다운 감지')
    ->send();
```

### Telegram 미디어 전송

```php
// 사진 (URL)
telegram()->to($chatId)
    ->photo('https://example.com/chart.png', '월간 리포트')
    ->send();

// 파일 업로드
telegram()->to($chatId)
    ->file('/path/to/report.pdf', '보고서')
    ->send();
```

### Telegram 인라인 키보드

```php
telegram()->to($chatId)
    ->message('작업을 선택하세요:')
    ->keyboard([
        [['text' => '승인', 'callback_data' => 'approve'], ['text' => '거절', 'callback_data' => 'reject']],
        ['취소'],  // 문자열 → text + callback_data 자동 설정
    ])
    ->send();
```

### Telegram 관리자 알림 패턴

```php
// 에러 알림
telegram()
    ->to(config('telegram.admin_chat'))
    ->html("<b>🚨 에러 발생</b>\n<code>{$e->getMessage()}</code>\n{$e->getFile()}:{$e->getLine()}")
    ->send();

// 일일 리포트
schedule()->call(function () {
    $users = db()->table('users')->count();
    $orders = db()->table('orders')->where('created_at', '>=', date('Y-m-d'))->count();
    telegram()->to(config('telegram.admin_chat'))
        ->html("<b>📊 일일 리포트</b>\n신규 유저: {$users}\n오늘 주문: {$orders}")
        ->send();
})->dailyAt('09:00');
```

### Telegram 내부 동작

- **이뮤터블**: 모든 빌더 메서드 `clone` 사용
- **HTTP**: `http()` 도구 활용 (cURL 기반)
- **파일 업로드**: `http()->upload()` (multipart/form-data)
- **에러 로깅**: API 실패 시 `logger()->error()` 자동 기록
- **`#[\SensitiveParameter]`**: `$botToken` 보호

---

## 도구 간 연동

```text
Sitemap → Cache (출력 캐시)
Sitemap → DB (fromQuery)
Backup → DB (설정 읽기)
Backup → Log (에러 로깅)
Webhook → Http (cURL 발송)
Webhook → Log (발송/수신 로깅)
Swoole → Router (HTTP 요청 디스패치)
Swoole → Log (에러 로깅)
Swoole → DB + Redis (연결 풀)
Telegram → Http (Bot API 호출)
Telegram → Log (에러 로깅)
```
