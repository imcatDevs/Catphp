# Auth — JWT 인증 + 비밀번호 해싱

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Auth` |
| 파일 | `catphp/Auth.php` (267줄) |
| Shortcut | `auth()` |
| 싱글턴 | `getInstance()` — 빈 시크릿 시 `RuntimeException` |
| 의존 확장 | `ext-json` |
| 연동 | `Cat\Session` (세션 로그인) |

---

## 설정

```php
// config/app.php
'auth' => [
    'secret' => 'your-jwt-secret-key-min-32-bytes',  // 필수, 빈 값이면 RuntimeException
    'ttl'    => 86400,                                  // JWT 유효시간 (초, 기본 24시간)
    'algo'   => 'Argon2id',                             // 비밀번호 해싱: Argon2id | bcrypt | argon2i
],
```

---

## 메서드 레퍼런스

### 비밀번호

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `hashPassword` | `hashPassword(string $password): string` | `string` | 비밀번호 해싱 (Argon2id/Bcrypt) |
| `verifyPassword` | `verifyPassword(string $password, string $hash): bool` | `bool` | 비밀번호 검증 |
| `needsRehash` | `needsRehash(string $hash): bool` | `bool` | 리해시 필요 여부 (알고리즘/옵션 변경 시) |

### JWT

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `createToken` | `createToken(array $payload, ?int $ttl = null): string` | `string` | JWT 토큰 생성 (HS256) |
| `verifyToken` | `verifyToken(string $token): ?array` | `?array` | JWT 검증 → 페이로드 또는 `null` |
| `bearer` | `bearer(): ?string` | `?string` | Authorization Bearer 토큰 추출 |

### API 인증

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `setApiUser` | `setApiUser(array $payload): void` | `void` | API 인증 사용자 페이로드 저장 |
| `apiUser` | `apiUser(): ?array` | `?array` | API 인증 사용자 반환 |

### 세션 인증

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `user` | `user(): ?array` | `?array` | 세션 로그인 사용자 |
| `id` | `id(): int\|string\|null` | `int\|string\|null` | 현재 사용자 ID (API 우선 → 세션 폴백) |
| `check` | `check(): bool` | `bool` | 로그인 상태 (세션 또는 API) |
| `guest` | `guest(): bool` | `bool` | 비로그인 상태 |
| `login` | `login(array $user): void` | `void` | 세션에 사용자 저장 + 세션 ID 재생성 |
| `logout` | `logout(): void` | `void` | 세션 파괴 + API 페이로드 초기화 |

---

## 사용 예제

### 비밀번호 해싱/검증

```php
// 해싱 (회원가입)
$hash = auth()->hashPassword($password);
db()->table('users')->insert(['email' => $email, 'password' => $hash]);

// 검증 (로그인)
$user = db()->table('users')->where('email', $email)->first();
if ($user && auth()->verifyPassword($password, $user['password'])) {
    // 로그인 성공

    // 리해시 필요 시 (알고리즘 업그레이드 후)
    if (auth()->needsRehash($user['password'])) {
        db()->table('users')->where('id', $user['id'])->update([
            'password' => auth()->hashPassword($password),
        ]);
    }
}
```

### JWT API 인증

```php
// 토큰 발급
router()->post('/api/login', function () {
    $user = db()->table('users')->where('email', input('email'))->first();
    if (!$user || !auth()->verifyPassword(input('password'), $user['password'])) {
        json()->fail('인증 실패', code: 401);
        return;
    }
    $token = auth()->createToken(['sub' => $user['id'], 'role' => $user['role']]);
    json()->ok(['token' => $token]);
});

// 토큰 검증
router()->get('/api/profile', function () {
    $token = auth()->bearer();
    if (!$token) {
        json()->fail('토큰 없음', code: 401);
        return;
    }
    $payload = auth()->verifyToken($token);
    if (!$payload) {
        json()->fail('토큰 만료/무효', code: 401);
        return;
    }
    auth()->setApiUser($payload);
    $user = db()->table('users')->where('id', $payload['sub'])->first();
    json()->ok($user);
});
```

### 세션 기반 로그인

```php
// 로그인
auth()->login(['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']]);

// 사용자 확인
if (auth()->check()) {
    $name = auth()->user()['name'];
    $id   = auth()->id();
}

// 로그아웃
auth()->logout();
```

### 커스텀 TTL 토큰

```php
// 7일 유효 (Remember Me)
$token = auth()->createToken(['sub' => $userId], 86400 * 7);

// 5분 유효 (이메일 인증 등)
$token = auth()->createToken(['sub' => $userId, 'purpose' => 'verify'], 300);
```

---

## 내부 동작

### JWT 구조

```text
header.payload.signature

header:    {"typ": "JWT", "alg": "HS256"}  → base64url
payload:   {"sub": 1, "iat": ..., "exp": ...}  → base64url
signature: HMAC-SHA256(header.payload, secret)  → base64url
```

### 토큰 검증 흐름

```text
verifyToken($token)
│
├─ 3파트 분리 (header.payload.signature)
├─ HMAC-SHA256 재계산
├─ hash_equals() 비교 (타이밍 공격 방지)
├─ JSON 디코딩
├─ exp 만료 확인
├─ nbf (not before) 확인
└─ 페이로드 반환 또는 null
```

### Bearer 토큰 추출

```text
bearer()
│
├─ $_SERVER['HTTP_AUTHORIZATION'] 확인
├─ $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 확인 (CGI/FastCGI)
├─ apache_request_headers() 폴백 (대소문자 무관 검색)
└─ "Bearer " 접두사 제거 후 반환
```

### 비밀번호 알고리즘

| 설정값 | PHP 상수 | 특징 |
| --- | --- | --- |
| `Argon2id` (기본) | `PASSWORD_ARGON2ID` | 메모리 하드 해싱, GPU 공격 방어 |
| `bcrypt` | `PASSWORD_BCRYPT` | 72바이트 제한, 널리 지원 |
| `argon2i` | `PASSWORD_ARGON2I` | 사이드채널 공격 방어 |

---

## 보안 고려사항

### 시크릿 키 관리

- `auth.secret`가 비어있으면 **`RuntimeException`** 발생 — 설정 없이 사용 불가
- 최소 32바이트 이상 랜덤 키 권장

### 타이밍 공격 방지

JWT 서명 비교에 `hash_equals()` 사용:

```php
hash_equals($expectedSignature, $signature)
```

### SensitiveParameter

비밀번호·시크릿 파라미터에 `#[\SensitiveParameter]` 속성 적용:

```php
public function hashPassword(#[\SensitiveParameter] string $password): string
```

→ 스택 트레이스에 비밀번호가 노출되지 않음 (PHP 8.2+)

### 세션 고정 공격 방어

`login()` 호출 시 `session()->regenerate()`로 세션 ID 재생성.

### Bcrypt 72바이트 제한

Bcrypt 알고리즘은 72바이트까지만 처리. 초과 시 `InvalidArgumentException`:

```php
// 72바이트 초과 → 예외
auth()->hashPassword(str_repeat('a', 100)); // Bcrypt일 때만 예외
```

---

## 주의사항

1. **시크릿 키 변경 시**: 기존 발급된 모든 JWT가 무효화된다. 키 로테이션 시 이전 키로의 폴백 검증은 미지원.

2. **API vs 세션**: `id()` 메서드는 API 인증(`apiPayload`)을 세션보다 우선한다. 두 인증이 동시에 존재하면 API 사용자가 반환된다.

3. **nbf (Not Before)**: `createToken()`에서 자동 설정되지 않지만, 페이로드에 수동으로 `'nbf' => time() + 60`을 추가하면 검증 시 고려된다.

4. **토큰 블랙리스트**: JWT 특성상 발급 후 강제 무효화 불가. 필요시 Redis/DB 기반 블랙리스트를 별도 구현해야 한다.

5. **logout() 세션 파괴**: `session_destroy()`를 호출하므로 같은 요청 내에서 세션 데이터에 접근할 수 없다.

---

## 연관 도구

- [Session](Session.md) — 세션 관리 (`login()`, `user()` 내부 사용)
- [Request](Request.md) — `bearerToken()` 제공
- [User](User.md) — 사용자 CRUD + `attempt()` 로그인
- [Csrf](Csrf.md) — CSRF 보호 (세션 인증용)
- [Api](Api.md) — API 미들웨어 (JWT 검증 자동화)
