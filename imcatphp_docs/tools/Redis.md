# Redis — Redis 클라이언트 래퍼

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Redis` |
| 파일 | `catphp/Redis.php` (304줄) |
| Shortcut | `redis()` |
| 싱글턴 | `getInstance()` — 지연 연결 |
| 의존 확장 | `ext-redis` (phpredis) |

---

## 설정

```php
// config/app.php
'redis' => [
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'password' => '',           // 비밀번호 (없으면 빈 문자열)
    'database' => 0,            // DB 번호 (0~15)
    'prefix'   => 'catphp:',   // 키 접두사
    'timeout'  => 2.0,          // 연결 타임아웃 (초)
],
```

---

## 메서드 레퍼런스

### String 명령어

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `set` | `set(string $key, mixed $value, int $ttl = 0): bool` | `bool` | 값 설정 (TTL 0 = 영구) |
| `get` | `get(string $key, mixed $default = null): mixed` | `mixed` | 값 읽기 |
| `del` | `del(string ...$keys): int` | `int` | 키 삭제 |
| `exists` | `exists(string $key): bool` | `bool` | 키 존재 여부 |
| `expire` | `expire(string $key, int $ttl): bool` | `bool` | TTL 설정 |
| `ttl` | `ttl(string $key): int` | `int` | 남은 TTL (초) |
| `incr` | `incr(string $key, int $by = 1): int` | `int` | 정수 증가 |
| `decr` | `decr(string $key, int $by = 1): int` | `int` | 정수 감소 |

### Hash 명령어

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `hSet` | `hSet(string $key, string $field, mixed $value): bool\|int` | `bool\|int` | 해시 필드 설정 |
| `hGet` | `hGet(string $key, string $field, mixed $default = null): mixed` | `mixed` | 해시 필드 읽기 |
| `hGetAll` | `hGetAll(string $key): array` | `array` | 해시 전체 필드 |
| `hDel` | `hDel(string $key, string ...$fields): int` | `int` | 해시 필드 삭제 |
| `hExists` | `hExists(string $key, string $field): bool` | `bool` | 해시 필드 존재 |

### List 명령어

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `rPush` | `rPush(string $key, mixed ...$values): int` | `int` | 오른쪽 추가 (큐 입력) |
| `lPop` | `lPop(string $key): mixed` | `mixed` | 왼쪽 꺼내기 (큐 소비) |
| `blPop` | `blPop(string $key, int $timeout = 0): mixed` | `mixed` | 블로킹 왼쪽 꺼내기 |
| `lLen` | `lLen(string $key): int` | `int` | 리스트 길이 |
| `lRange` | `lRange(string $key, int $start = 0, int $end = -1): array` | `array` | 리스트 범위 조회 |

### Set 명령어

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `sAdd` | `sAdd(string $key, mixed ...$members): int` | `int` | 멤버 추가 |
| `sMembers` | `sMembers(string $key): array` | `array` | 전체 멤버 |
| `sIsMember` | `sIsMember(string $key, mixed $member): bool` | `bool` | 멤버 존재 확인 |
| `sRem` | `sRem(string $key, mixed ...$members): int` | `int` | 멤버 제거 |

### Sorted Set 명령어

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `zAdd` | `zAdd(string $key, float $score, mixed $member): int` | `int` | 멤버 추가 (점수 포함) |
| `zRange` | `zRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array` | `array` | 범위 조회 |
| `zRem` | `zRem(string $key, mixed ...$members): int` | `int` | 멤버 제거 |
| `zScore` | `zScore(string $key, mixed $member): float\|false` | `float\|false` | 멤버 점수 |

### Pub/Sub

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `publish` | `publish(string $channel, mixed $message): int` | `int` | 메시지 발행 |

### 유틸리티

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `keys` | `keys(string $pattern = '*'): array` | `array` | 패턴 키 검색 (**프로덕션 주의**) |
| `flush` | `flush(): bool` | `bool` | 현재 DB 전체 삭제 |
| `ping` | `ping(): bool` | `bool` | 연결 확인 |
| `remember` | `remember(string $key, callable $callback, int $ttl = 3600): mixed` | `mixed` | 캐시 기억 패턴 |
| `raw` | `raw(): \Redis` | `\Redis` | 원시 phpredis 인스턴스 |

---

## 사용 예제

### 기본 캐시

```php
redis()->set('user:1', ['name' => '홍길동', 'role' => 'admin'], 3600);
$user = redis()->get('user:1');

redis()->del('user:1');
```

### remember 패턴

```php
$settings = redis()->remember('app:settings', function () {
    return db()->table('settings')->all();
}, 1800);
```

### 카운터

```php
redis()->incr('page:views', 1);
$views = redis()->get('page:views');  // 누적 조회수
```

### 해시 (사용자 프로필)

```php
redis()->hSet('user:1', 'name', '홍길동');
redis()->hSet('user:1', 'email', 'hong@example.com');

$name = redis()->hGet('user:1', 'name');
$all  = redis()->hGetAll('user:1');
```

### 큐 (리스트)

```php
// 생산자
redis()->rPush('jobs', ['type' => 'email', 'to' => 'user@example.com']);

// 소비자
$job = redis()->lPop('jobs');
// 블로킹 소비 (5초 대기)
$job = redis()->blPop('jobs', 5);
```

### Sorted Set (리더보드)

```php
redis()->zAdd('leaderboard', 1500, 'player1');
redis()->zAdd('leaderboard', 2300, 'player2');
$top = redis()->zRange('leaderboard', 0, 9, withScores: true);
```

### 메시지 발행

```php
redis()->publish('notifications', ['type' => 'alert', 'message' => '서버 점검']);
```

### 원시 인스턴스

```php
$raw = redis()->raw();
$raw->multi();
$raw->set('a', 1);
$raw->set('b', 2);
$raw->exec();
```

---

## 내부 동작

### 지연 연결

`redis()` 호출 시 Redis 연결이 생성되지 않는다. 실제 명령어(`set`, `get` 등) 호출 시점에 `connection()`이 최초 1회 연결.

### 연결 설정

```text
connection()
├─ ext-redis 확장 확인 → 없으면 RuntimeException
├─ connect($host, $port, $timeout)
├─ auth($password) — 비밀번호 있으면
├─ select($database) — 0번이 아니면
├─ setOption(OPT_PREFIX, $prefix) — 접두사 설정
└─ setOption(OPT_SERIALIZER, SERIALIZER_JSON) — JSON 직렬화
```

### JSON 직렬화

```php
$this->conn->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);
```

모든 값이 자동으로 JSON 직렬화/역직렬화된다. 배열, 객체 등을 직접 저장/읽기 가능.

### 소멸자

```php
public function __destruct() {
    $this->conn->close();
}
```

스크립트 종료 시 연결 자동 정리.

---

## 보안 고려사항

- **인증 실패**: 비밀번호가 틀리면 `RuntimeException` — 연결 즉시 실패
- **ext-redis 미설치**: `extension_loaded('redis')` 체크 → 명확한 에러 메시지
- **키 접두사**: `catphp:` 접두사로 다른 애플리케이션과 키 충돌 방지

---

## 주의사항

1. **`ext-redis` 필수**: phpredis 확장이 없으면 Fatal Error. `pecl install redis`로 설치.

2. **`keys()` 프로덕션 금지**: `KEYS *`는 전체 키를 스캔하므로 Redis를 블로킹한다. 프로덕션에서는 `SCAN` 사용 (`raw()->scan()` 활용).

3. **`flush()` 위험**: 현재 DB의 **모든 키**를 삭제한다. 운영 환경 사용 금지.

4. **JSON 직렬화**: `SERIALIZER_JSON` 설정으로 모든 값이 JSON으로 저장된다. 바이너리 데이터 저장 시 `raw()`로 원시 인스턴스 사용.

5. **`publish()` 메시지 타입**: 문자열이 아닌 값은 자동으로 `json_encode()` 처리.

6. **연결 풀**: 싱글턴이므로 요청당 1개 연결. Swoole 환경에서는 `Swoole::createRedisPool()` 사용.

---

## 연관 도구

- [Cache](Cache.md) — 파일 캐시 (Redis 없는 환경용)
- [Queue](Queue.md) — Redis 기반 큐 (내부 사용)
- [Session](Session.md) — Redis 세션 핸들러 (별도 설정)
- [Swoole](Swoole.md) — Redis 연결 풀
