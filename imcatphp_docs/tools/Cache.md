# Cache — 파일 캐시

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Cache` |
| 파일 | `catphp/Cache.php` (129줄) |
| Shortcut | `cache()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | 없음 |
| 저장 방식 | 파일 (`serialize` + `LOCK_EX`) |

---

## 설정

```php
// config/app.php
'cache' => [
    'path' => __DIR__ . '/../storage/cache',   // 캐시 디렉토리
    'ttl'  => 3600,                              // 기본 TTL (초, 기본 1시간)
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `get` | `get(string $key, mixed $default = null): mixed` | `mixed` | 캐시 읽기 (만료 시 자동 삭제) |
| `set` | `set(string $key, mixed $value, ?int $ttl = null): bool` | `bool` | 캐시 쓰기 (TTL 0 = 영구) |
| `del` | `del(string $key): bool` | `bool` | 캐시 삭제 |
| `has` | `has(string $key): bool` | `bool` | 캐시 존재 확인 (만료 고려) |
| `clear` | `clear(): bool` | `bool` | 전체 캐시 삭제 |
| `remember` | `remember(string $key, callable $callback, ?int $ttl = null): mixed` | `mixed` | 없으면 콜백 실행 후 저장 |

---

## CLI 명령어

```bash
php cli.php cache:clear    # 전체 캐시 삭제
```

---

## 사용 예제

### 기본 읽기/쓰기

```php
cache()->set('user:1', ['name' => '홍길동'], 3600);  // 1시간
$user = cache()->get('user:1');                         // 배열 반환
$missing = cache()->get('nonexistent', 'default');       // 'default'
```

### remember 패턴

```php
// 캐시에 없으면 DB 조회 후 30분간 캐싱
$posts = cache()->remember('popular_posts', function () {
    return db()->table('posts')->orderByDesc('views')->limit(10)->all();
}, 1800);
```

### 캐시 무효화

```php
cache()->del('user:1');       // 단일 삭제
cache()->clear();              // 전체 삭제
```

### 존재 확인

```php
if (cache()->has('settings')) {
    $settings = cache()->get('settings');
}
```

### 영구 캐시

```php
cache()->set('app_version', '1.0.0', 0);  // TTL 0 = 만료 없음
```

---

## 내부 동작

### 파일 구조

```text
storage/cache/{xxh3_hash}.cache
```

- 파일명: `hash('xxh3', $key)` — 빠른 해시 알고리즘
- 내용: `serialize(['expires' => timestamp, 'value' => mixed])`

### 읽기 흐름

```text
get($key)
├─ 파일 존재? → 아니면 $default
├─ file_get_contents()
├─ unserialize(['allowed_classes' => false])  ← 보안!
├─ expires > 0 && expires < time()? → 만료 → 삭제 → $default
└─ $data['value'] 반환
```

### 쓰기 흐름

```text
set($key, $value, $ttl)
├─ ensureDir() — 디렉토리 없으면 생성
├─ expires = $ttl > 0 ? time() + $ttl : 0
├─ serialize(['expires' => ..., 'value' => ...])
└─ file_put_contents($file, $data, LOCK_EX)
```

### has() 구현

```php
return $this->get($key, $this) !== $this;
```

`$this`(Cache 인스턴스 자체)를 센티넬로 사용하여 `null` 값과 "키 없음"을 구분한다. `null` 캐시도 정상 감지.

### remember() 구현

`has()` 대신 센티넬 기반 `get()`으로 `null` 값도 캐싱 가능:

```php
$sentinel = new \stdClass();
$value = $this->get($key, $sentinel);
if ($value !== $sentinel) return $value;  // null 캐시도 히트
```

---

## 보안 고려사항

### 역직렬화 보안

```php
unserialize($content, ['allowed_classes' => false])
```

`allowed_classes => false` — 객체 인스턴스화를 차단하여 PHP 역직렬화 공격(RCE) 방지. 모든 객체는 `__PHP_Incomplete_Class`로 변환된다.

### 파일 잠금

`file_put_contents(LOCK_EX)` — 동시 쓰기 시 데이터 손상 방지.

---

## 주의사항

1. **파일 I/O 기반**: 대량 캐시(수만 키)나 고빈도 접근에는 Redis 캐시 권장.

2. **만료 삭제 방식**: 만료된 캐시는 `get()` 호출 시에만 삭제된다 (Lazy Expiration). 미접근 만료 파일은 디스크에 잔류. `clear()`나 크론잡으로 정리 필요.

3. **`clear()`는 `.cache` 확장자만 삭제**: 다른 파일은 영향 없음.

4. **`null` 캐싱 가능**: `set('key', null, 60)` — null 값도 캐싱된다. `has()`로 존재 여부 확인 가능.

5. **TTL 0 = 영구**: `set($key, $value, 0)` — 만료되지 않는 영구 캐시. 수동 삭제 필요.

6. **xxh3 해시**: PHP 8.1+에서 사용 가능. 이전 버전에서는 `hash('xxh3', ...)` 호출 시 에러.

---

## 연관 도구

- [Redis](Redis.md) — Redis 캐시 (고성능 대안)
- [Ip](Ip.md) — GeoIP 캐시 (`cache()->get('geoip:{ip}')`)
- [Sitemap](Sitemap.md) — 사이트맵 캐시
