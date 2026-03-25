# Session — 세션 관리

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Session` |
| 파일 | `catphp/Session.php` (283줄) |
| Shortcut | `session()` |
| 싱글턴 | `getInstance()` — 생성자에서 자동 `start()` |
| 의존 확장 | 없음 (PHP 네이티브 세션) |

---

## 설정

```php
// config/app.php
'session' => [
    'lifetime' => 7200,        // 세션 쿠키 수명 (초, 기본 2시간)
    'path'     => '/',          // 쿠키 경로
    'secure'   => false,        // HTTPS 전용 쿠키
    'httponly'  => true,        // JavaScript 접근 차단
    'samesite'  => 'Lax',      // SameSite 정책: Lax | Strict | None
],
```

---

## 메서드 레퍼런스

### 기본 CRUD

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `get` | `get(string $key, mixed $default = null): mixed` | `mixed` | 세션 값 읽기 |
| `set` | `set(string $key, mixed $value): self` | `self` | 세션 값 설정 |
| `has` | `has(string $key): bool` | `bool` | 키 존재 확인 |
| `forget` | `forget(string $key): self` | `self` | 키 삭제 |
| `pull` | `pull(string $key, mixed $default = null): mixed` | `mixed` | 읽고 삭제 |
| `all` | `all(): array` | `array` | 전체 세션 데이터 |
| `clear` | `clear(): self` | `self` | 전체 초기화 |

### Flash 데이터

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `flash` | `flash(string $key, mixed $value): self` | `self` | flash 데이터 설정 (다음 요청까지만) |
| `getFlash` | `getFlash(string $key, mixed $default = null): mixed` | `mixed` | flash 데이터 읽기 |
| `hasFlash` | `hasFlash(string $key): bool` | `bool` | flash 데이터 존재 확인 |
| `reflash` | `reflash(): self` | `self` | 현재 flash를 한 번 더 유지 |
| `keep` | `keep(array $keys): self` | `self` | 지정 flash 키만 유지 |

### 세션 관리

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `start` | `start(): self` | `self` | 세션 시작 (자동 호출) |
| `regenerate` | `regenerate(bool $deleteOldSession = true): self` | `self` | 세션 ID 재생성 |
| `destroy` | `destroy(): void` | `void` | 세션 파괴 (쿠키 삭제 포함) |
| `id` | `id(): string` | `string` | 현재 세션 ID |
| `name` | `name(): string` | `string` | 세션 이름 (기본 PHPSESSID) |
| `isStarted` | `isStarted(): bool` | `bool` | 세션 시작 여부 |

### 유틸리티

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `remember` | `remember(string $key, callable $callback): mixed` | `mixed` | 없으면 콜백 실행 후 저장 |
| `increment` | `increment(string $key, int $amount = 1): int` | `int` | 값 증가 |
| `decrement` | `decrement(string $key, int $amount = 1): int` | `int` | 값 감소 |
| `token` | `token(): string` | `string` | CSRF 토큰 (`_token` 키) |

---

## 사용 예제

### 기본 사용

```php
session()->set('user_id', 123);
$userId = session()->get('user_id');

if (session()->has('user_id')) {
    // 로그인 상태
}

session()->forget('user_id');
```

### pull (읽고 삭제)

```php
$message = session()->pull('download_url');
// 1회 사용 후 세션에서 삭제
```

### Flash 메시지

```php
// 설정 (리다이렉트 전)
session()->flash('success', '게시글이 저장되었습니다.');

// 다음 요청에서 읽기
$msg = session()->getFlash('success');
if ($msg) {
    echo "<div class='alert'>{$msg}</div>";
}
// 그 다음 요청에서는 자동 삭제됨
```

### Flash 유지

```php
// 검증 실패 시 flash를 한 번 더 유지
session()->reflash();

// 특정 flash만 유지
session()->keep(['error', 'old_input']);
```

### 카운터

```php
$views = session()->increment('page_views');
$remaining = session()->decrement('credits');
```

### remember 패턴

```php
$cart = session()->remember('cart', fn() => []);
```

### 세션 보안

```php
// 로그인 성공 후 세션 ID 재생성
auth()->login($user);  // 내부에서 session()->regenerate() 호출

// 수동 재생성
session()->regenerate();

// 로그아웃
session()->destroy();
```

---

## 내부 동작

### 자동 시작

`getInstance()` → `new self()` → `start()` 자동 호출. 세션이 이미 활성이면 중복 시작하지 않음.

### CLI 환경 대응

```text
start()
├─ PHP_SAPI === 'cli'?
│   ├─ 예 → $_SESSION = [] 초기화 (session_start 없이)
│   └─ 아니오 → session_set_cookie_params() + session_start()
└─ ageFlash() — 이전 요청 flash 정리
```

### Flash 에이징 시스템

```text
요청 1: session()->flash('msg', 'Hello')
  _flash_new = ['msg']
  _flash_old = []

요청 2: ageFlash() 실행
  _flash_old = ['msg']     ← new → old 이동
  _flash_new = []
  → 'msg' 키 접근 가능

요청 3: ageFlash() 실행
  old의 'msg' 키 삭제      ← 자동 정리
  _flash_old = []
  _flash_new = []
```

### destroy() 상세

```text
destroy()
├─ $_SESSION = []
├─ 세션 쿠키 삭제 (setcookie, 과거 만료)
└─ session_destroy()
```

---

## 보안 고려사항

### 세션 고정 공격 방지

`regenerate()` — 세션 ID를 재생성하여 세션 고정 공격(Session Fixation)을 방지. `auth()->login()` 내부에서 자동 호출.

### 쿠키 보안 설정

| 옵션 | 기본값 | 권장 (운영) | 효과 |
| --- | --- | --- | --- |
| `httponly` | `true` | `true` | JavaScript에서 쿠키 접근 차단 (XSS 방어) |
| `secure` | `false` | `true` | HTTPS에서만 쿠키 전송 |
| `samesite` | `Lax` | `Lax` 또는 `Strict` | CSRF 방어 |

### headers_sent() 체크

세션 시작 전 `headers_sent()` 확인. 이미 출력이 시작된 경우 세션 시작을 건너뜀 (경고 방지).

---

## 주의사항

1. **자동 시작**: `session()` 최초 호출 시 세션이 자동 시작된다. API 전용 서버에서 불필요한 세션 시작을 피하려면 `session()` 호출 자체를 하지 않아야 한다.

2. **Flash 순서**: `flash()`는 `set()` 후 `_flash_new`에 키를 등록한다. `ageFlash()`는 세션 시작 시 호출되므로, 같은 요청 내에서 `flash()`와 `getFlash()`를 모두 사용하면 정상 동작한다.

3. **destroy() 후 세션 접근**: `destroy()` 후 같은 요청 내에서 `$_SESSION`에 접근하면 빈 배열이다.

4. **dot notation 미지원**: `get('user.name')` 같은 중첩 키는 지원하지 않는다. 단순 키만 사용.

5. **GC**: PHP의 기본 세션 GC 설정(`session.gc_probability`, `session.gc_divisor`)에 의존. `gc_maxlifetime`은 config의 `lifetime` 값으로 자동 설정.

---

## 연관 도구

- [Auth](Auth.md) — 세션 로그인/로그아웃 (`login()`, `logout()`)
- [Csrf](Csrf.md) — CSRF 토큰 저장
- [Flash](Flash.md) — Flash 메시지 헬퍼
- [Cookie](Cookie.md) — 쿠키 관리 (세션 쿠키 외)
