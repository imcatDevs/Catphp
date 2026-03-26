# 보안 — Auth · Csrf · Encrypt · Firewall · Ip · Guard

CatPHP의 보안 계층. 인증, CSRF 보호, 암호화, IP 차단, 입력 살균을 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Auth | `auth()` | `Cat\Auth` | 265 |
| Csrf | `csrf()` | `Cat\Csrf` | 79 |
| Encrypt | `encrypt()` | `Cat\Encrypt` | 85 |
| Firewall | `firewall()` | `Cat\Firewall` | 211 |
| Ip | `ip()` | `Cat\Ip` | 195 |
| Guard | `guard()` | `Cat\Guard` | 521 |

---

## 목차

1. [Auth — JWT + 비밀번호 인증](#1-auth--jwt--비밀번호-인증)
2. [Csrf — CSRF 보호](#2-csrf--csrf-보호)
3. [Encrypt — Sodium 암호화](#3-encrypt--sodium-암호화)
4. [Firewall — IP 차단/허용](#4-firewall--ip-차단허용)
5. [Ip — IP 감지 + GeoIP](#5-ip--ip-감지--geoip)
6. [Guard — 입력 살균/공격 차단](#6-guard--입력-살균공격-차단)

---

## 1. Auth — JWT + 비밀번호 인증

JWT 토큰 기반 API 인증과 세션 기반 웹 인증을 모두 지원.

### Auth 설정

```php
'auth' => [
    'secret' => 'your-jwt-secret-key',   // JWT 서명 비밀키 (필수)
    'ttl'    => 86400,                     // 토큰 유효시간 (초, 기본 24시간)
    'algo'   => 'argon2id',               // 비밀번호 해싱: argon2id | bcrypt | argon2i
],
```

> **주의**: `auth.secret`이 비어있으면 `RuntimeException`을 던진다.

### 비밀번호 해싱

```php
// 해싱
$hash = auth()->hashPassword('mypassword');

// 검증
$valid = auth()->verifyPassword('mypassword', $hash);  // true

// 리해시 필요 여부 (알고리즘/옵션 변경 시)
if (auth()->needsRehash($hash)) {
    $newHash = auth()->hashPassword($password);
    // DB에 새 해시 저장
}
```

지원 알고리즘:

| 알고리즘 | 특징 |
| --- | --- |
| `argon2id` (기본) | 메모리 하드, GPU 공격 방어 최강 |
| `argon2i` | 사이드 채널 공격 방어 |
| `bcrypt` | 72바이트 제한 주의 (초과 시 예외) |

### JWT 토큰

```php
// 토큰 생성
$token = auth()->createToken(['sub' => $userId, 'role' => 'admin']);
// TTL 커스텀 (2시간)
$token = auth()->createToken(['sub' => $userId], 7200);

// 토큰 검증
$payload = auth()->verifyToken($token);
// ['sub' => 1, 'role' => 'admin', 'iat' => 1711100000, 'exp' => 1711186400]
// 실패 시 null
```

JWT 내부 동작:
- **알고리즘**: HS256 (HMAC-SHA256)
- **서명 검증**: `hash_equals()`로 타이밍 공격 방어
- **만료 확인**: `exp` 클레임 자동 검증
- **nbf 지원**: `nbf` (not before) 클레임도 자동 검증

### Bearer 토큰 추출

```php
$token = auth()->bearer();
// Authorization: Bearer eyJhbGciOi... → 'eyJhbGciOi...'

// Apache CGI/FastCGI 환경도 자동 감지
// REDIRECT_HTTP_AUTHORIZATION, apache_request_headers() 폴백
```

### 세션 인증

```php
// 로그인 (세션에 사용자 정보 저장 + 세션 ID 재생성)
auth()->login(['id' => 1, 'name' => '홍길동', 'role' => 'admin']);

// 현재 사용자
$user = auth()->user();       // ['id' => 1, 'name' => '홍길동', ...] 또는 null

// 사용자 ID
$id = auth()->id();           // 1 (세션 또는 API, API 우선)

// 로그인 상태 확인
auth()->check();              // true
auth()->guest();              // false

// 로그아웃 (세션 파괴)
auth()->logout();
```

### API 인증 흐름

```php
// API 미들웨어에서 사용
$token = auth()->bearer();
if ($token === null) {
    json()->fail('인증 필요', 401);
}

$payload = auth()->verifyToken($token);
if ($payload === null) {
    json()->fail('유효하지 않은 토큰', 401);
}

auth()->setApiUser($payload);

// 이후 핸들러에서
$userId = auth()->id();            // JWT sub 클레임
$payload = auth()->apiUser();      // 전체 페이로드
```

### Auth 보안 요약

| 항목 | 방어 |
| --- | --- |
| 비밀번호 | Argon2id/Bcrypt 해싱, `#[\SensitiveParameter]` |
| JWT 서명 | `hash_equals()` 타이밍 공격 방어 |
| 세션 | 로그인 시 `session_regenerate_id()` |
| Bcrypt | 72바이트 제한 검증 |
| 빈 시크릿 | `RuntimeException` throw |

---

## 2. Csrf — CSRF 보호

세션 기반 CSRF 토큰 생성/검증.

### Csrf 사용법

```php
// 토큰 생성/가져오기 (세션에 저장)
$token = csrf()->token();

// 폼에 hidden input 삽입
echo csrf()->field();
// <input type="hidden" name="_csrf_token" value="abc123...">

// 수동 검증
if (!csrf()->verify()) {
    // CSRF 토큰 불일치
}
```

### Csrf 미들웨어

```php
// 글로벌 미들웨어로 등록
router()->use(csrf()->middleware());
```

미들웨어 동작:
- **GET/HEAD/OPTIONS**: 검증 건너뜀
- **POST/PUT/PATCH/DELETE**: `_csrf_token` POST 필드 또는 `X-CSRF-TOKEN` 헤더에서 토큰 검증
- **실패 시**: API → JSON 403, 웹 → HTML 403

### Csrf 내부 동작

- 토큰 생성: `bin2hex(random_bytes(32))` — 64자 hex
- 저장: `$_SESSION['_csrf_token']`
- 검증: `hash_equals()` 타이밍 공격 방어
- 토큰은 세션 유지 동안 재사용 (새로고침 시 유지)

---

## 3. Encrypt — Sodium 암호화

libsodium 기반 대칭키 암호화/HMAC 서명.

### Encrypt 설정

```php
'encrypt' => [
    'key' => 'base64:YOUR_BASE64_ENCODED_KEY',
],
```

> **주의**: `encrypt.key`가 비어있으면 `RuntimeException`을 던진다.

키 처리:
- `base64:` 접두사 → base64 디코딩
- 키 길이가 `SODIUM_CRYPTO_SECRETBOX_KEYBYTES`(32)가 아니면 `sodium_crypto_generichash()`로 조정

### Encrypt 메서드

```php
// 암호화 (nonce + ciphertext → base64)
$encrypted = encrypt()->seal('비밀 데이터');

// 복호화
$plain = encrypt()->open($encrypted);  // '비밀 데이터' 또는 null (실패 시)

// HMAC 서명
$signature = encrypt()->sign('메시지');

// HMAC 서명 검증
$valid = encrypt()->verify('메시지', $signature);  // true/false
```

### Encrypt 보안

| 항목 | 구현 |
| --- | --- |
| 알고리즘 | XSalsa20-Poly1305 (`sodium_crypto_secretbox`) |
| 논스 | 매 암호화마다 `random_bytes()` 생성 |
| 키 보호 | `#[\SensitiveParameter]`, 소멸자에서 `sodium_memzero()` |
| HMAC | `sodium_crypto_auth` (Poly1305) |

---

## 4. Firewall — IP 차단/허용

파일 기반 IP 차단 목록 관리.

### Firewall 설정

```php
'firewall' => [
    'path'     => storage_path('firewall'),  // 차단 목록 저장 경로
    'auto_ban' => true,                       // Guard와 연동 자동 차단
],
```

### Firewall 메서드

```php
// IP 차단
firewall()->ban('192.168.1.100');

// IP 차단 해제
firewall()->unban('192.168.1.100');

// IP/CIDR 허용 (허용 목록에 있으면 차단 무시)
firewall()->allow('10.0.0.0/8');
firewall()->allow('192.168.1.1');

// 차단 여부 확인
firewall()->isDenied('192.168.1.100');   // true
firewall()->isAllowed('192.168.1.100');  // false

// 차단 목록
$list = firewall()->bannedList();
// [['ip' => '192.168.1.100', 'banned_at' => '2024-01-15 10:30:00']]
```

### Firewall 미들웨어

```php
router()->use(firewall()->middleware());
// 차단된 IP → 403 JSON 응답 + exit
```

### Firewall 내부 동작

- **저장**: `storage/firewall/banned.json` (JSON)
- **지연 로딩**: 첫 조회 시에만 파일 로드
- **파일 락**: 읽기 시 `LOCK_SH` 공유 락, 쓰기 시 `LOCK_EX` 배타 락
- **CIDR 지원**: `inet_pton()` 기반 IPv4+IPv6 CIDR 범위 확인
- **Guard 연동**: `guard.auto_ban` 활성화 시 공격 감지 → 자동 차단

---

## 5. Ip — IP 감지 + GeoIP

클라이언트 IP 감지, CIDR 범위 확인, GeoIP 조회.

### Ip 설정

```php
'ip' => [
    'provider'        => 'api',           // 'api' | 'mmdb'
    'mmdb_path'       => null,            // MaxMind .mmdb 경로
    'cache_ttl'       => 86400,           // GeoIP 캐시 TTL
    'trusted_proxies' => ['10.0.0.1'],    // 신뢰 프록시 목록 (빈 배열 = 모두 신뢰)
],
```

### Ip 클라이언트 감지

```php
$ip = ip()->address();  // '203.0.113.50'
```

감지 우선순위 (신뢰 프록시에서만):

1. `HTTP_CF_CONNECTING_IP` (CloudFlare)
2. `HTTP_X_FORWARDED_FOR` (첫 번째 IP)
3. `HTTP_X_REAL_IP` (nginx)
4. `REMOTE_ADDR` (폴백)

> **보안**: `trusted_proxies` 설정이 비어있지 않으면, 목록에 있는 프록시에서 온 요청만 포워딩 헤더를 신뢰한다. 모든 IP는 `filter_var(FILTER_VALIDATE_IP)`로 검증되어 XSS/로그 인젝션을 차단한다.

### GeoIP 조회

```php
// 국가 코드
ip()->country();                   // 'KR' (현재 IP)
ip()->country('8.8.8.8');          // 'US' (지정 IP)

// 도시
ip()->city();                      // '서울'

// 위치 (위도/경도)
ip()->location();                  // ['lat' => 37.5665, 'lon' => 126.978]

// 전체 정보
ip()->info();
// ['ip' => '203.0.113.50', 'country' => 'KR', 'city' => '서울', 'lat' => 37.5665, 'lon' => 126.978]
```

- **캐시**: `Cat\Cache` 존재 시 `geoip:{ip}` 키로 캐시
- **프라이빗 IP**: 내부 IP는 GeoIP 조회를 건너뜀
- **API**: ip-api.com 무료 API (프로덕션에서는 MMDB 권장)

### Ip CIDR 범위 확인

```php
ip()->isInRange('192.168.1.50', '192.168.1.0/24');  // true
ip()->isInRange('10.0.0.1', '192.168.0.0/16');      // false

// IPv6도 지원
ip()->isInRange('::1', '::1/128');                    // true
```

---

## 6. Guard — 입력 살균/공격 차단

XSS, 경로 트래버설, SQL 인젝션 탐지, 파일명 살균 등 종합 입력 보안.

### Guard 설정

```php
'guard' => [
    'auto_ban'      => false,    // 공격 감지 시 자동 IP 차단
    'max_body_size' => '10M',    // 최대 요청 크기
],
```

### Guard XSS 방지

```php
$safe = guard()->xss($userInput);
```

살균 대상:
- `<script>` 태그 제거
- 위험 태그: `iframe`, `object`, `embed`, `svg`, `math`, `base`, `style` 등
- 이벤트 핸들러: `onclick`, `onload`, `onerror` 등 (`<svg/onload=...` 바이패스 방어 포함)
- `style` 속성 내 `javascript:`, `expression()`
- 위험 프로토콜: `javascript:`, `vbscript:`, `data:` URI
- CSS `-moz-binding`, `expression()`
- HTML 엔티티 디코딩 후 재검사 (`&#106;avascript:` 우회 방어)
- 제로폭/불가시 유니코드 문자 제거

### Guard 경로 트래버설 차단

```php
$safe = guard()->path($input);
```

차단 패턴: `../`, `..\\`, URL 인코딩 (`%2e%2e`), 이중 인코딩 (`%252e`), 유니코드 오버롱 인코딩, Tomcat-style (`..;/`), null 바이트, 슬래시 없는 `..` 세그먼트 (`test/..` 등).

### Guard SQL 인젝션 탐지

```php
$result = guard()->sql($input);
// 경고 로그만 기록, 값은 변경하지 않음 (실제 방어는 PDO prepared statement)
```

탐지 패턴: `UNION SELECT`, `DROP TABLE`, `SLEEP()`, `BENCHMARK()`, `LOAD_FILE()`, `INFORMATION_SCHEMA`, SQL 주석 등.

### Guard 종합 살균

```php
// 단일 값
$safe = guard()->clean($input);
// 1) 제어문자 제거 → 2) CRLF 방어 → 3) XSS 살균 → 4) SQL 탐지

// 배열 (재귀)
$safeData = guard()->cleanArray($data, except: ['password']);
```

### Guard 파일명 살균

```php
$safe = guard()->filename('../../etc/passwd.php.jpg');
// 위험 확장자(.php) 탐지 → 'etcpasswd.php.jpg.blocked'
```

차단 확장자: `php`, `phtml`, `exe`, `sh`, `bat`, `jsp`, `asp`, `htaccess`, `svg` 등.

### Guard 기타 메서드

```php
// 헤더 인젝션 차단
$safe = guard()->header($input);

// Content-Type 검증
guard()->contentType(['application/json']);

// 요청 크기 확인
guard()->maxBodySize();        // config 기반
guard()->maxBodySize('5M');    // 직접 지정

// 공격 감지 콜백
guard()->onAttack(function (string $type, string $ip) {
    // 알림 전송, 추가 로깅 등
});
```

### Guard 미들웨어

```php
router()->use(guard()->middleware());
```

미들웨어 동작:

1. 요청 크기 확인 → 초과 시 413
2. `guard()->all()`로 전체 입력 살균
3. 살균 결과를 `input()` 캐시에 주입

### Guard 공격 보고 흐름

```text
공격 감지
├─ Cat\Log 경고 로그 기록
├─ 콜백 호출 (onAttack)
└─ auto_ban=true → Cat\Firewall 자동 차단
```

---

## 도구 간 연동

```text
Auth → Session (세션 인증)
Csrf → Session (토큰 저장)
Guard → Firewall (auto_ban)
Guard → Ip (공격 IP 감지)
Guard → Log (공격 로깅)
Guard → input() (살균 결과 주입)
Firewall → Ip (미들웨어에서 IP 감지)
Request → Ip (ip() 메서드 위임)
```
