# Queue — 비동기 작업 큐

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Queue` |
| 파일 | `catphp/Queue.php` (384줄) |
| Shortcut | `queue()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Redis` (redis 드라이버), `Cat\DB` (db 드라이버) |

---

## 설정

```php
// config/app.php
'queue' => [
    'driver'  => 'redis',    // redis | db
    'default' => 'default',  // 기본 큐 이름
],
```

### DB 드라이버 테이블

```sql
CREATE TABLE queue_jobs (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    queue VARCHAR(50) NOT NULL DEFAULT 'default',
    payload TEXT NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    reserved_at DATETIME NULL,
    available_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL
);

CREATE TABLE queue_failed_jobs (
    id VARCHAR(64) PRIMARY KEY,
    queue VARCHAR(50) NOT NULL,
    payload TEXT NOT NULL,
    error TEXT,
    failed_at DATETIME NOT NULL
);
```

---

## 메서드 레퍼런스

### 핸들러

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `handle` | `handle(string $job, callable $handler): self` | `self` | 작업 핸들러 등록 |

### 작업 추가

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `push` | `push(string $job, array $payload = [], ?string $queue = null, int $maxRetries = 3): string` | `string` | 즉시 실행 큐에 추가 → 작업 ID |
| `later` | `later(int $delaySeconds, string $job, array $payload = [], ?string $queue = null, int $maxRetries = 3): string` | `string` | 지연 실행 |

### 워커

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `pop` | `pop(?string $queue = null): bool` | `bool` | 단일 작업 꺼내기 + 실행 |
| `work` | `work(?string $queue = null, int $sleep = 3, int $maxJobs = 0): void` | `void` | 워커 루프 (블로킹) |

### 관리

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `size` | `size(?string $queue = null): int` | `int` | 큐 크기 |
| `clear` | `clear(?string $queue = null): int` | `int` | 큐 비우기 |
| `failed` | `failed(int $limit = 50): array` | `array` | 실패한 작업 목록 |
| `retryFailed` | `retryFailed(string $id): bool` | `bool` | 실패 작업 재시도 |

---

## 사용 예제

### 핸들러 등록 + 작업 추가

```php
// 부팅 시 핸들러 등록
queue()->handle('send_email', function (array $payload) {
    mail()->to($payload['to'])->subject($payload['subject'])->body($payload['body'])->send();
});

queue()->handle('resize_image', function (array $payload) {
    image()->open($payload['path'])->thumbnail(300, 300)->save($payload['output']);
});

// 작업 추가
queue()->push('send_email', [
    'to'      => 'user@example.com',
    'subject' => '환영합니다',
    'body'    => '<h1>가입 완료</h1>',
]);
```

### 지연 실행

```php
// 60초 후 실행
queue()->later(60, 'send_email', ['to' => 'user@example.com', ...]);

// 특정 큐, 최대 5회 재시도
queue()->push('resize_image', ['path' => '/img.jpg'], 'images', 5);
```

### CLI 워커

```bash
# 기본 큐 워커
php cli.php queue:work

# 특정 큐 + 폴링 간격 1초
php cli.php queue:work --queue=images --sleep=1

# 최대 100개 처리 후 종료
php cli.php queue:work --max-jobs=100
```

### 실패 작업 관리

```php
$failures = queue()->failed();
queue()->retryFailed($failures[0]['id']);
```

---

## 내부 동작

### Redis 드라이버

```text
push() → RPUSH queue:default {json}
pop()  → LPOP queue:default (FIFO)

later(60) → ZADD queue:default:delayed {score=time()+60} {json}
            워커가 promoteDelayed()로 승격:
            Lua 스크립트: ZRANGEBYSCORE → ZREM → RPUSH (원자적)
```

### DB 드라이버

```text
push() → INSERT INTO queue_jobs
pop()  → 트랜잭션 내:
         1. SELECT ... WHERE reserved_at IS NULL AND available_at <= NOW ORDER BY id
         2. UPDATE reserved_at (낙관적 락)
         3. DELETE (처리 완료)
```

### 재시도 (지수 백오프)

```text
실패 시:
  attempts < max_retries → 지연 재큐잉
    delay = 2^attempts 초 (1회=2초, 2회=4초, 3회=8초)
  attempts >= max_retries → failed 큐로 이동
```

### 그레이스풀 셧다운

```text
work() 루프:
├─ PCNTL 확장 있으면 SIGTERM/SIGINT 핸들링
├─ 시그널 수신 → $shouldQuit = true
└─ 현재 작업 완료 후 루프 종료
```

---

## 보안 고려사항

- **작업 ID**: `random_bytes(16)` — 예측 불가 고유 ID
- **DB 원자적 dequeue**: 트랜잭션 + `reserved_at` 마킹으로 다중 워커 중복 소비 방지
- **Redis Lua 스크립트**: 지연 작업 승격을 원자적으로 처리

---

## 주의사항

1. **핸들러 등록 필수**: 워커에서 `handle()` 미등록 작업은 즉시 실패 처리.
2. **Redis 직렬화**: Redis의 `SERIALIZER_JSON` 설정에 의존. 복잡한 객체는 배열로 변환 필요.
3. **PCNTL 선택적**: `ext-pcntl` 없으면 SIGTERM 핸들링 불가. `Ctrl+C`로만 종료.
4. **maxJobs=0**: 무한 루프 (기본). 프로세스 관리자(Supervisor 등) 권장.
5. **failed 저장**: Redis는 `queue:failed` 리스트, DB는 `queue_failed_jobs` 테이블.

---

## 연관 도구

- [Redis](Redis.md) — Redis 드라이버 백엔드
- [DB](DB.md) — DB 드라이버 백엔드
- [Mail](Mail.md) — 이메일 발송 작업
- [Log](Log.md) — 작업 실패 로깅
