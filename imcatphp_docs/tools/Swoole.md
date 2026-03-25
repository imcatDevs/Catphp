# Swoole — 고성능 비동기 HTTP/WebSocket 서버

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Swoole` |
| 파일 | `catphp/Swoole.php` (1246줄) |
| Shortcut | `swoole()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | `ext-swoole` 또는 `ext-openswoole` |
| 의존 도구 | `Cat\Router` (HTTP 디스패치), `Cat\Log` (에러 로깅) |

---

## 설정

```php
// config/app.php
'swoole' => [
    'host'              => '0.0.0.0',
    'port'              => 9501,
    'mode'              => 'process',     // process | base
    'worker_num'        => 4,             // CPU 코어 수 기본
    'task_worker_num'   => 4,
    'max_request'       => 10000,         // 워커당 최대 요청 (메모리 누수 방지)
    'max_conn'          => 10000,
    'daemonize'         => false,
    'log_file'          => '',
    'log_level'         => 4,             // 0=DEBUG ~ 5=OFF
    'pid_file'          => '',            // 기본: storage/swoole.pid
    'dispatch_mode'     => 2,             // 1=라운드로빈, 2=FD, 3=큐경쟁
    'open_tcp_nodelay'  => true,
    'enable_coroutine'  => true,
    'static_handler'    => false,
    'document_root'     => '',
    'hot_reload'        => false,         // 개발 전용
    'hot_reload_paths'  => [],
    'heartbeat_idle'    => 600,           // 유휴 연결 타임아웃 (초)
    'heartbeat_check'   => 60,
    'ssl_cert'          => '',
    'ssl_key'           => '',
    'buffer_output_size'   => 2097152,    // 2MB
    'package_max_length'   => 2097152,
    'pool' => [
        'db'    => 0,   // 0이면 비활성, >0이면 워커 시작 시 자동 생성
        'redis' => 0,
    ],
],
```

---

## 메서드 레퍼런스

### 서버 타입

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `http` | `http(): self` | `self` | HTTP 서버 모드 |
| `websocket` | `websocket(): self` | `self` | WebSocket 서버 모드 (HTTP 겸용) |

### 설정 체이닝

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `listen` | `listen(string $host, int $port): self` | `self` | 바인드 주소 |
| `workers` | `workers(int $num): self` | `self` | 워커 수 |
| `taskWorkers` | `taskWorkers(int $num): self` | `self` | 태스크 워커 수 |
| `daemonize` | `daemonize(bool $enable = true): self` | `self` | 데몬 모드 |
| `ssl` | `ssl(string $certFile, string $keyFile): self` | `self` | SSL (HTTPS/WSS) |
| `staticFiles` | `staticFiles(string $documentRoot): self` | `self` | 정적 파일 서빙 |
| `set` | `set(array $settings): self` | `self` | Swoole 설정 직접 지정 |
| `onBoot` | `onBoot(\Closure $callback): self` | `self` | 워커 부트스트랩 콜백 |
| `use` | `use(callable $middleware): self` | `self` | 미들웨어 등록 |

### 서버 생명주기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `start` | `start(): void` | `void` | 서버 시작 (블로킹) |
| `stop` | `stop(): bool` | `bool` | 서버 중지 (static, PID 기반) |
| `reload` | `reload(): bool` | `bool` | 워커 리로드 (static) |
| `status` | `status(): array` | `array` | 서버 상태 (static) |

### 서버 이벤트

| 메서드 | 시그니처 | 설명 |
| --- | --- | --- |
| `onStart` | `onStart(callable $cb): self` | 마스터 시작 |
| `onWorkerStart` | `onWorkerStart(callable $cb): self` | 워커 시작 |
| `onWorkerStop` | `onWorkerStop(callable $cb): self` | 워커 종료 |
| `onShutdown` | `onShutdown(callable $cb): self` | 서버 종료 |

### WebSocket

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `onWsOpen` | `onWsOpen(callable $cb): self` | `self` | 연결 열림 |
| `onWsMessage` | `onWsMessage(callable $cb): self` | `self` | 메시지 수신 |
| `onWsClose` | `onWsClose(callable $cb): self` | `self` | 연결 닫힘 |
| `push` | `push(int $fd, string\|array $data, int $opcode = TEXT): bool` | `bool` | FD에 메시지 전송 |
| `broadcast` | `broadcast(string\|array $data, ?int $excludeFd = null): int` | `int` | 전체 브로드캐스트 |

### 룸 관리

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `join` | `join(int $fd, string $room): self` | `self` | 룸 참가 |
| `leave` | `leave(int $fd, string $room): self` | `self` | 룸 퇴장 |
| `leaveAll` | `leaveAll(int $fd): self` | `self` | 모든 룸 퇴장 |
| `toRoom` | `toRoom(string $room, string\|array $data, ?int $excludeFd = null): int` | `int` | 룸 브로드캐스트 |
| `roomMembers` | `roomMembers(string $room): array` | `array` | 룸 멤버 |
| `roomList` | `roomList(): array` | `array` | 전체 룸 목록 |

### 태스크 워커

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `handle` | `handle(string $name, callable $handler): self` | `self` | 태스크 핸들러 등록 |
| `task` | `task(string $name, array $payload = []): int\|false` | `int\|false` | 비동기 태스크 전송 |
| `taskWait` | `taskWait(string $name, array $payload = [], float $timeout = 5.0): mixed` | `mixed` | 동기 태스크 (결과 대기) |
| `taskCo` | `taskCo(array $tasks, float $timeout = 5.0): array` | `array` | 병렬 태스크 |

### 코루틴

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `co` | `co(callable $callback): int` | `int` | 코루틴 생성 |
| `sleep` | `sleep(float $seconds): void` | `void` | 비블로킹 sleep |
| `channel` | `channel(int $capacity = 1): Channel` | `Channel` | 코루틴 채널 |
| `waitGroup` | `waitGroup(): WaitGroup` | `WaitGroup` | 코루틴 대기 그룹 |
| `parallel` | `parallel(array $callables, float $timeout = -1): array` | `array` | 병렬 실행 + 결과 수집 |

### 연결 풀

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `createPool` | `createPool(string $name, callable $factory, int $size = 0): self` | `self` | 커스텀 풀 생성 |
| `createDbPool` | `createDbPool(?int $size = null): self` | `self` | DB 연결 풀 |
| `createRedisPool` | `createRedisPool(?int $size = null): self` | `self` | Redis 연결 풀 |
| `poolGet` | `poolGet(string $name, float $timeout = 3.0): mixed` | `mixed` | 풀에서 연결 가져오기 |
| `poolPut` | `poolPut(string $name, mixed $connection): void` | `void` | 풀에 연결 반환 |
| `poolStats` | `poolStats(string $name): array` | `array` | 풀 상태 조회 |

### 타이머

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `tick` | `tick(int $ms, callable $callback): int` | `int` | 반복 타이머 |
| `after` | `after(int $ms, callable $callback): int` | `int` | 1회 타이머 |
| `clearTimer` | `clearTimer(int $timerId): bool` | `bool` | 타이머 해제 |
| `clearAllTimers` | `clearAllTimers(): void` | `void` | 전체 타이머 해제 |

### 서버 정보

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `raw` | `raw(): Server\|null` | `Server\|null` | Swoole 서버 인스턴스 |
| `stats` | `stats(): array` | `array` | 서버 통계 |
| `connectionCount` | `connectionCount(): int` | `int` | 활성 연결 수 |
| `getClientInfo` | `getClientInfo(int $fd): array\|false` | `array\|false` | 클라이언트 정보 |

---

## 사용 예제

### HTTP 서버

```php
// cli.php 또는 별도 스크립트
swoole()
    ->http()
    ->onBoot(function () {
        // 라우트 정의 (워커마다 실행)
        router()->get('/', fn() => 'Hello CatPHP!');
        router()->get('/api/users', function () {
            json()->ok(db()->table('users')->all());
        });
    })
    ->start();
```

### WebSocket 서버

```php
swoole()
    ->websocket()
    ->onWsOpen(fn(int $fd) => swoole()->push($fd, '연결 성공'))
    ->onWsMessage(function (int $fd, string $data) {
        $msg = json_decode($data, true);
        match ($msg['type'] ?? '') {
            'join'    => swoole()->join($fd, $msg['room']),
            'message' => swoole()->toRoom($msg['room'], $msg['text'], $fd),
            default   => swoole()->push($fd, '알 수 없는 메시지'),
        };
    })
    ->onWsClose(fn(int $fd) => swoole()->leaveAll($fd))
    ->start();
```

### 비동기 태스크

```php
swoole()->handle('send_email', function (array $payload) {
    mail()->to($payload['to'])->subject($payload['subject'])->body($payload['body'])->send();
});

// 요청 핸들러에서 비동기 발송
router()->post('/register', function () {
    $user = createUser(input('email'));
    swoole()->task('send_email', ['to' => $user['email'], 'subject' => '환영', 'body' => '...']);
    json()->ok($user);
});
```

### 코루틴 병렬 실행

```php
$results = swoole()->parallel([
    'users'    => fn() => db()->table('users')->count(),
    'posts'    => fn() => db()->table('posts')->count(),
    'comments' => fn() => db()->table('comments')->count(),
]);
// ['users' => ['key'=>'users', 'value'=>150, 'error'=>null], ...]
```

### 연결 풀 사용

```php
swoole()->onBoot(function ($svr, $workerId) {
    swoole()->createDbPool(10);
});

// 요청 핸들러에서
$pdo = swoole()->poolGet('db');
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([1]);
    $user = $stmt->fetch();
} finally {
    swoole()->poolPut('db', $pdo);  // 반드시 반환
}
```

### 타이머 사용

```php
// 매 1초마다 실행
swoole()->tick(1000, function () {
    logger()->info('heartbeat');
});

// 5초 후 1회 실행
swoole()->after(5000, function () {
    logger()->info('지연 작업 완료');
});
```

### SSL (HTTPS)

```php
swoole()
    ->http()
    ->ssl('/path/to/cert.pem', '/path/to/key.pem')
    ->start();
```

### 서버 관리 (CLI)

```bash
php cli.php swoole:start
php cli.php swoole:stop
php cli.php swoole:reload
php cli.php swoole:status
```

---

## 내부 동작

### HTTP 요청 브릿지

```text
Swoole Request → handleRequest()
├─ 1. 슈퍼글로벌 초기화 ($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER)
│   └─ JSON Content-Type → rawContent() 파싱
├─ 2. 미들웨어 실행 (false 반환 시 중단)
├─ 3. ob_start() → router()->dispatch() → ob_get_clean()
├─ 4. headers_list() → Swoole Response 헤더 설정
├─ 5. $res->end($body) → 응답 전송
└─ catch: 500 에러 (debug 모드면 상세, 아니면 간결)
```

### Hot Reload

```text
hot_reload=true, 워커 0에서만:
├─ ext-inotify 있음 → inotify_add_watch (실시간 감지)
└─ ext-inotify 없음 → 2초 폴링 (RecursiveDirectoryIterator)
    └─ .php 파일 mtime 변경 감지 → $svr->reload()
```

### 연결 풀 생명주기

```text
WorkerStart → createDbPool()/createRedisPool()
              └─ Channel에 PDO/Redis 인스턴스 push

요청 처리 → poolGet('db') → Channel.pop() (코루틴 대기)
         → 쿼리 실행
         → poolPut('db') → Channel.push() (반환)

WorkerStop → destroyPools()
             └─ Channel 비우기 + Redis.close() + Channel.close()
```

### 룸 자동 퇴장

WebSocket `Close` 이벤트 시 `leaveAll($fd)` 자동 호출 — 유령 FD 방지.

---

## 보안 고려사항

- **요청 격리**: 매 요청마다 슈퍼글로벌 완전 초기화 — 이전 요청 데이터 잔류 방지
- **PID 파일**: `Shutdown` 이벤트에서 자동 삭제
- **Graceful Shutdown**: `SIGTERM` → 현재 처리 중인 요청 완료 후 종료
- **에러 노출**: `app.debug=false` 시 스택 트레이스 미노출 ("서버 오류" 메시지만)
- **출력 버퍼 정리**: `finally` 블록에서 `ob_end_clean()` 루프 — 버퍼 누수 방지

---

## 주의사항

1. **ext-swoole 필수**: Swoole 또는 OpenSwoole 확장 미설치 시 `RuntimeException`.
2. **상주 프로세스**: 코드 변경 후 서버 재시작 또는 `reload` 필요 (hot_reload 제외).
3. **전역 상태 주의**: 싱글턴이 요청 간 공유됨. 요청별 상태는 슈퍼글로벌/input()에만 의존.
4. **연결 풀 반환 필수**: `poolGet()` 후 반드시 `poolPut()` 호출. `try/finally` 패턴 권장.
5. **max_request**: 메모리 누수 방지를 위해 워커당 최대 요청 수 이후 자동 재시작.
6. **Windows 미지원**: Swoole은 Linux/macOS 전용. Windows에서는 WSL2 사용.

---

## 연관 도구

- [Router](Router.md) — HTTP 라우팅 (자동 통합)
- [Redis](Redis.md) — Redis 연결 풀
- [DB](DB.md) — DB 연결 풀
- [Queue](Queue.md) — 태스크 워커 대안
- [Log](Log.md) — 에러 로깅
