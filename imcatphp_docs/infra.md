# 인프라 — Mail · Queue · Storage · Schedule · Notify · Excel · Hash

CatPHP의 인프라 계층. 이메일, 작업 큐, 파일 저장소, 스케줄러, 알림, CSV/Excel, 해싱을 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Mail | `mailer()` | `Cat\Mail` | 359 |
| Queue | `queue()` | `Cat\Queue` | 384 |
| Storage | `storage()` | `Cat\Storage` | 468 |
| Schedule | `schedule()` | `Cat\Schedule` | 364 |
| Notify | `notify()` | `Cat\Notify` | 194 |
| Excel | `excel()` | `Cat\Excel` | 340 |
| Hash | `hasher()` | `Cat\Hash` | 184 |

---

## 목차

1. [Mail — SMTP 이메일](#1-mail--smtp-이메일)
2. [Queue — 비동기 작업 큐](#2-queue--비동기-작업-큐)
3. [Storage — 파일시스템 추상화](#3-storage--파일시스템-추상화)
4. [Schedule — 크론 스케줄러](#4-schedule--크론-스케줄러)
5. [Notify — 다채널 알림](#5-notify--다채널-알림)
6. [Excel — CSV/XLSX 가져오기·내보내기](#6-excel--csvxlsx-가져오기내보내기)
7. [Hash — 체크섬/무결성](#7-hash--체크섬무결성)

---

## 1. Mail — SMTP 이메일

순수 소켓 기반 SMTP 클라이언트. 외부 의존성 없음.

### Mail 설정

```php
'mail' => [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'username'   => 'you@gmail.com',
    'password'   => 'app-password',
    'encryption' => 'tls',         // tls | ssl | none
    'from_email' => 'you@gmail.com',
    'from_name'  => 'CatPHP',
],
```

### Mail 사용법

```php
// 기본 발송
mailer()->to('user@example.com')
    ->subject('환영합니다')
    ->body('<h1>안녕하세요</h1><p>가입을 환영합니다.</p>')
    ->send();

// 다중 수신자 + CC/BCC + Reply-To
mailer()->to('a@b.com', 'c@d.com')
    ->cc('manager@b.com')
    ->bcc('archive@b.com')
    ->replyTo('support@b.com')
    ->subject('알림')
    ->body('<p>내용</p>')
    ->send();

// 파일 첨부
mailer()->to('user@example.com')
    ->subject('보고서')
    ->body('<p>첨부 파일을 확인하세요.</p>')
    ->attach('/path/to/report.pdf')
    ->attach('/path/to/data.csv', 'monthly-data.csv')
    ->send();

// 템플릿 사용
mailer()->to('user@example.com')
    ->subject('환영 이메일')
    ->template('emails/welcome', ['name' => '홍길동'])
    ->send();

// 텍스트 전용
mailer()->to('user@example.com')
    ->subject('알림')
    ->text('순수 텍스트 메시지')
    ->send();

// 디버그 (발송 없이 MIME 미리보기)
$mime = mailer()->to('user@example.com')
    ->subject('테스트')
    ->body('<p>테스트</p>')
    ->preview();
```

### Mail 내부 동작

- **이뮤터블**: 모든 빌더 메서드가 `clone` 사용 (싱글턴 상태 오염 없음)
- **MIME**: multipart/alternative (HTML+텍스트) + multipart/mixed (첨부)
- **인코딩**: UTF-8 Base64 (한국어 안전)
- **헤더**: 비ASCII 값은 `=?UTF-8?B?...?=` 인코딩
- **TLS**: STARTTLS + TLSv1.2/1.3 지원

### Mail 보안

- `sanitizeEmail()` — CRLF 인젝션 방어 + `filter_var` 검증
- `#[\SensitiveParameter]` 비밀번호는 직접 프로퍼티 접근 불가

---

## 2. Queue — 비동기 작업 큐

Redis 또는 DB 백엔드. 지연 실행, 재시도, 실패 관리.

### Queue 설정

```php
'queue' => [
    'driver'  => 'redis',    // redis | db
    'default' => 'default',  // 기본 큐 이름
],
```

### Queue 핸들러 등록

```php
// cli.php 또는 부트스트랩에서
queue()->handle('email', function (array $payload) {
    mailer()->to($payload['to'])->subject($payload['subject'])->body($payload['body'])->send();
});

queue()->handle('resize', function (array $payload) {
    image()->open($payload['path'])->thumbnail(200, 200)->save($payload['output']);
});
```

### Queue 작업 추가

```php
// 즉시 큐
$id = queue()->push('email', ['to' => 'a@b.com', 'subject' => '알림', 'body' => '<p>내용</p>']);

// 큐 이름 + 재시도 횟수 지정
$id = queue()->push('resize', ['path' => '/img.jpg'], 'images', 5);

// 지연 실행 (60초 후)
$id = queue()->later(60, 'cleanup', ['days' => 7]);
```

### Queue 워커 (CLI)

```bash
php cli.php queue:work                    # 기본 큐
php cli.php queue:work --queue=images     # 지정 큐
```

### Queue 관리

```php
queue()->size();                    // 큐 크기
queue()->size('images');            // 지정 큐
queue()->clear();                   // 큐 비우기
queue()->failed();                  // 실패 목록
queue()->retryFailed($jobId);       // 실패 작업 재시도
```

### Queue 내부 동작

| 기능 | Redis | DB |
| --- | --- | --- |
| 즉시 큐 | List (rPush/lPop) | `queue_jobs` 테이블 |
| 지연 큐 | Sorted Set (score=시간) | `available_at` 컬럼 |
| 승격 | Lua 스크립트 원자적 이동 | `WHERE available_at <= NOW()` |
| 실패 | `queue:failed` List | `queue_failed_jobs` 테이블 |
| 동시성 | Redis 원자 연산 | 트랜잭션 + `reserved_at` |
| 재시도 | 지수 백오프 (2^n초) | 지수 백오프 (2^n초) |

- **PCNTL**: `SIGTERM`/`SIGINT` 그레이스풀 셧다운

---

## 3. Storage — 파일시스템 추상화

로컬 + S3 디스크를 통합 API로 사용.

### Storage 설정

```php
'storage' => [
    'default' => 'local',
    'disks' => [
        'local'  => ['driver' => 'local', 'root' => dirname(__DIR__) . '/storage/app'],
        'public' => ['driver' => 'local', 'root' => dirname(__DIR__) . '/Public/uploads', 'url' => '/uploads'],
        's3'     => [
            'driver'   => 's3',
            'key'      => env('AWS_KEY'),
            'secret'   => env('AWS_SECRET'),
            'region'   => 'ap-northeast-2',
            'bucket'   => 'my-bucket',
        ],
    ],
],
```

### Storage 사용법

```php
// 기본 디스크 (local)
storage()->put('uploads/file.txt', $content);
$content = storage()->get('uploads/file.txt');
storage()->exists('uploads/file.txt');   // true
storage()->delete('uploads/file.txt');

// 디스크 선택 (이뮤터블)
storage()->disk('s3')->put('backups/db.sql', $dump);
storage()->disk('public')->url('photos/avatar.jpg');  // '/uploads/photos/avatar.jpg'

// 파일 정보
storage()->size('file.txt');           // 바이트
storage()->lastModified('file.txt');   // Unix timestamp
storage()->mimeType('file.txt');       // 'text/plain'

// 복사/이동
storage()->copy('old.txt', 'new.txt');
storage()->move('temp.txt', 'final.txt');

// 추가 쓰기
storage()->append('logs/app.log', "새 로그\n");

// 디렉토리
storage()->files('uploads');                       // 파일 목록
storage()->files('uploads', recursive: true);       // 재귀
storage()->makeDirectory('uploads/2024');
storage()->deleteDirectory('uploads/old');

// 파일 스트리밍 (대용량 다운로드)
storage()->stream('exports/large.zip', 'download.zip');
```

### Storage S3 드라이버

AWS Signature V4 직접 구현 (cURL). 외부 SDK 불필요.

### Storage 보안

- **경로 트래버설 방어**: `safePath()` — `../` 반복 제거 + `realpath` 이중 검증
- **모든 public 메서드**에 `safePath()` 적용

---

## 4. Schedule — 크론 스케줄러

CLI 기반 작업 예약. 시스템 크론탭에 1분 간격 등록.

```bash
# crontab -e
* * * * * php /path/to/cli.php schedule:run
```

### Schedule 태스크 등록

```php
// CLI 명령어
schedule()->command('cache:clear')->daily();
schedule()->command('log:rotate --days=30')->weeklyOn(1, '03:00');

// 콜백
schedule()->call(fn() => logger()->info('heartbeat'))->everyFiveMinutes();
schedule()->call(fn() => backup()->database())->dailyAt('02:00')->withoutOverlapping();
```

### Schedule 스케줄 메서드

| 메서드 | 표현식 |
| --- | --- |
| `everyMinute()` | `* * * * *` |
| `everyMinutes(N)` | `*/N * * * *` |
| `everyFiveMinutes()` | `*/5 * * * *` |
| `everyFifteenMinutes()` | `*/15 * * * *` |
| `everyThirtyMinutes()` | `*/30 * * * *` |
| `hourly()` | `0 * * * *` |
| `hourlyAt(30)` | `30 * * * *` |
| `daily()` | `0 0 * * *` |
| `dailyAt('03:00')` | `0 3 * * *` |
| `weekly()` | `0 0 * * 0` |
| `weeklyOn(1, '03:00')` | `0 3 * * 1` |
| `monthly()` | `0 0 1 * *` |
| `monthlyOn(15, '06:00')` | `0 6 15 * *` |
| `cron('expr')` | 원시 표현식 |

### Schedule 옵션

```php
schedule()->command('backup')->daily()
    ->description('일일 백업')
    ->withoutOverlapping();  // 중복 실행 방지 (flock)
```

### Schedule CLI 명령어

```bash
php cli.php schedule:run    # 현재 시각 기준 실행
php cli.php schedule:list   # 등록된 태스크 목록
```

### Schedule 내부 동작

- **cron 파서**: 5필드 매칭 (분, 시, 일, 월, 요일) — 범위(`1-5`), 스텝(`*/5`, `10-20/3`), 콤마(`1,5,15`) 지원
- **중복 방지**: `flock(LOCK_EX | LOCK_NB)` 비블로킹 락 + 10분 데드락 자동 정리
- **명령어 실행**: `escapeshellarg()` 인수 분리 (인젝션 방어)

---

## 5. Notify — 다채널 알림

Mail, Telegram, 커스텀 채널 통합 관리.

### Notify 사용법

```php
// 이메일 알림
notify()->to('user@example.com')->via('mail')->send('제목', '<p>본문</p>');

// 텔레그램 알림
notify()->to('@12345678')->via('telegram')->send('알림 메시지');

// 다채널 동시 발송
$results = notify()
    ->to('user@example.com', '@12345678')
    ->via('mail', 'telegram')
    ->send('긴급 알림', '서버 상태를 확인하세요.');
// ['mail' => true, 'telegram' => true]

// 간편 알림 (설정된 채널 자동 선택)
notify()->to('admin@example.com')->alert('디스크 사용률 90% 초과');
```

### Notify 커스텀 채널

```php
// Slack 채널 등록
Notify::channel('slack', function (string $to, string $subject, string $body): bool {
    $payload = json_encode(['text' => "{$subject}\n{$body}"]);
    $response = http()->json($to, ['text' => "{$subject}\n{$body}"]);
    return $response->ok();
});

// 사용
notify()->to('https://hooks.slack.com/...')->via('slack')->send('알림', '내용');
```

### Notify 채널별 수신자 규칙

| 채널 | 수신자 형식 |
| --- | --- |
| `mail` | 이메일 주소 (`@` 포함) |
| `telegram` | `@chat_id` 또는 순수 숫자 |
| 커스텀 | 핸들러에 그대로 전달 |

---

## 6. Excel — CSV/XLSX 가져오기·내보내기

외부 의존성 없이 CSV + 간이 XLSX 지원.

### Excel 내보내기

```php
// CSV 파일 저장
excel()->from($rows)->headers(['이름', '나이', '이메일'])->toCsv('/path/to/export.csv');

// CSV 문자열
$csv = excel()->from($rows)->toCsvString();

// XLSX 파일 (ext-zip 필요)
excel()->from($rows)->headers(['이름', '나이'])->toXlsx('/path/to/export.xlsx');

// TSV (탭 구분)
excel()->from($rows)->delimiter("\t")->toCsv('/path/to/export.tsv');

// 브라우저 다운로드
excel()->from($rows)->headers(['이름', '나이'])->download('report.csv');
excel()->from($rows)->download('report.xlsx');  // XLSX 다운로드
```

### Excel 가져오기

```php
// CSV 파일 읽기 (헤더 자동 매핑)
$data = excel()->fromCsv('/path/to/import.csv');
// [['이름' => 'Alice', '나이' => '30'], ...]

// 헤더 없는 CSV
$data = excel()->fromCsv('/path/to/data.csv', hasHeader: false);
// [['Alice', '30'], ...]

// CSV 문자열에서 읽기
$data = excel()->fromCsvString($csvContent);
```

### Excel 보안

- **BOM 처리**: UTF-8 BOM 자동 추가/건너뛰기 (Excel 한글 호환)
- **CSV Injection 방어**: `=`, `+`, `-`, `@`, `\t`, `\r`로 시작하는 셀에 `'` 접두사 추가

---

## 7. Hash — 체크섬/무결성

파일/문자열 해싱, HMAC, 비밀번호, 디렉토리 매니페스트.

### Hash 설정

```php
'hash' => [
    'algo' => 'sha256',  // 기본 알고리즘
],
```

### Hash 파일 해싱

```php
// 파일 해시
$hash = hasher()->file('/path/to/file.zip');           // SHA-256
$hash = hasher()->file('/path/to/file.zip', 'md5');    // MD5

// 무결성 검증
hasher()->verify('/path/to/file.zip', $expectedHash);  // true/false

// CRC32 체크섬
hasher()->checksum('/path/to/file.zip');
```

### Hash 문자열/HMAC

```php
// 문자열 해시
hasher()->string('hello world');                        // SHA-256
hasher()->string('hello', 'md5');                       // MD5

// HMAC
$mac = hasher()->hmac('message', 'secret-key');
hasher()->verifyHmac('message', $mac, 'secret-key');    // true

// 타이밍 안전 비교
hasher()->equals($hash1, $hash2);
```

### Hash 비밀번호

```php
// 해싱 (Auth.php와 동일 알고리즘)
$hash = hasher()->password('mypassword');

// 검증
hasher()->passwordVerify('mypassword', $hash);

// 리해시 필요 여부
hasher()->passwordNeedsRehash($hash);
```

### Hash 디렉토리 매니페스트

```php
// 디렉토리 전체 해시
$manifest = hasher()->directory('/path/to/app');
// ['src/index.php' => 'abc123...', 'config/app.php' => 'def456...']

// 무결성 검증 (변경/추가/삭제 감지)
$diff = hasher()->verifyDirectory('/path/to/app', $manifest);
// ['modified' => ['src/index.php'], 'added' => ['new.php'], 'removed' => []]
```

### Hash 보안

- `hash_equals()` — 모든 비교에 타이밍 공격 방어
- `#[\SensitiveParameter]` — HMAC 키, 비밀번호 보호

---

## 도구 간 연동

```text
Mail → (독립) 소켓 SMTP
Queue → Redis (Redis 드라이버)
Queue → DB (DB 드라이버)
Storage → (독립) 로컬/S3
Schedule → Log (에러 로깅)
Notify → Mail (이메일 알림)
Notify → Telegram (텔레그램 알림)
Notify → Log (실패 로깅)
Excel → (독립) ZipArchive (XLSX)
Hash → (독립) PHP hash 함수
```
