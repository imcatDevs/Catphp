# Request — HTTP 요청 추상화

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Request` |
| 파일 | `catphp/Request.php` (464줄) |
| Shortcut | `request()` |
| 싱글턴 | `getInstance()` — 뮤터블 (`override()`, `refresh()`) |
| 의존 확장 | 없음 |
| 연동 | `Cat\Ip` (로드 시 `ip()` 위임) |

---

## 설정

별도 config 없음. `$_GET`, `$_POST`, `$_FILES`, `$_SERVER`, `$_COOKIE`, `php://input`을 직접 래핑한다.

---

## 메서드 레퍼런스

### 입력 데이터

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `input` | `input(string $key, mixed $default = null): mixed` | `mixed` | GET+POST+JSON body 통합 조회 |
| `query` | `query(string $key, mixed $default = null): mixed` | `mixed` | GET 파라미터만 |
| `post` | `post(string $key, mixed $default = null): mixed` | `mixed` | POST 파라미터만 |
| `json` | `json(?string $key = null, mixed $default = null): mixed` | `mixed` | JSON body 파싱. 키 없으면 전체 배열 |
| `all` | `all(): array` | `array` | 모든 입력 (GET+POST+JSON) |
| `only` | `only(array $keys): array` | `array` | 지정 키만 추출 |
| `except` | `except(array $keys): array` | `array` | 지정 키 제외 |
| `has` | `has(string $key): bool` | `bool` | 키 존재 확인 |
| `filled` | `filled(string $key): bool` | `bool` | 값이 비어있지 않은지 (`null`, `''`, `[]` 제외) |
| `raw` | `raw(): string` | `string` | `php://input` 원본 body |

### 파일

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `file` | `file(string $key): ?array` | `?array` | 업로드 파일 정보 (`$_FILES[$key]`) |
| `hasFile` | `hasFile(string $key): bool` | `bool` | 파일 존재 + 에러 아님 확인 |

### HTTP 메서드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `method` | `method(): string` | `string` | HTTP 메서드 (대문자). `_method` 오버라이드 지원 |
| `isMethod` | `isMethod(string $method): bool` | `bool` | 특정 메서드인지 |
| `isGet` | `isGet(): bool` | `bool` | GET 요청인지 |
| `isPost` | `isPost(): bool` | `bool` | POST 요청인지 |

### URL 정보

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `path` | `path(): string` | `string` | 요청 경로 (쿼리스트링 제외) |
| `url` | `url(): string` | `string` | 전체 URL (쿼리스트링 제외) |
| `fullUrl` | `fullUrl(): string` | `string` | 전체 URL + 쿼리스트링 |
| `queryString` | `queryString(): string` | `string` | 쿼리스트링 |

### 헤더

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `header` | `header(string $name, ?string $default = null): ?string` | `?string` | 요청 헤더 값 |
| `headers` | `headers(): array` | `array<string,string>` | 모든 요청 헤더 |
| `bearerToken` | `bearerToken(): ?string` | `?string` | `Authorization: Bearer ...` 토큰 추출 |
| `contentType` | `contentType(): ?string` | `?string` | Content-Type 헤더 |

### 요청 유형 감지

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `isJson` | `isJson(): bool` | `bool` | Content-Type에 `json` 포함 |
| `isAjax` | `isAjax(): bool` | `bool` | `X-Requested-With: XMLHttpRequest` |
| `isSecure` | `isSecure(): bool` | `bool` | HTTPS 요청 (프록시 `X-Forwarded-Proto` 포함) |
| `accepts` | `accepts(string $type): bool` | `bool` | Accept 헤더에 특정 타입 포함 |
| `wantsJson` | `wantsJson(): bool` | `bool` | `application/json` 응답을 원하는지 |

### 클라이언트 정보

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `ip` | `ip(): string` | `string` | 클라이언트 IP (Ip 도구 위임 또는 폴백) |
| `userAgent` | `userAgent(): string` | `string` | User-Agent |
| `referer` | `referer(): ?string` | `?string` | Referer 헤더 |

### 쿠키

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `cookie` | `cookie(string $key, ?string $default = null): ?string` | `?string` | 쿠키 값 |
| `hasCookie` | `hasCookie(string $key): bool` | `bool` | 쿠키 존재 확인 |

### 서버 정보

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `server` | `server(string $key, ?string $default = null): ?string` | `?string` | `$_SERVER` 변수 |
| `port` | `port(): int` | `int` | 서버 포트 |
| `host` | `host(): string` | `string` | 호스트명 |
| `scheme` | `scheme(): string` | `string` | 스키마 (`http` / `https`) |

### 상태 관리

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `override` | `override(array $data): self` | `self` | 입력 캐시 강제 교체 (테스트/모킹) |
| `refresh` | `refresh(): self` | `self` | `$_GET`+`$_POST`에서 입력 캐시 재구성 |

---

## 사용 예제

### 입력값 읽기

```php
$name  = request()->input('name');
$page  = request()->query('page', '1');
$email = request()->post('email');

// JSON body
$data = request()->json();         // 전체
$title = request()->json('title'); // 특정 키
```

### 필터링

```php
$data = request()->only(['name', 'email', 'password']);
$rest = request()->except(['_token', '_method']);

if (request()->filled('search')) {
    // 검색어가 비어있지 않음
}
```

### HTTP 메서드 오버라이드

```php
// HTML 폼에서 PUT/DELETE 사용
// <input type="hidden" name="_method" value="DELETE">
$method = request()->method();  // 'DELETE' (POST에서 오버라이드)
```

### 헤더 및 토큰

```php
$token = request()->bearerToken();
$ct    = request()->contentType();
$auth  = request()->header('Authorization');

$allHeaders = request()->headers();
```

### 요청 유형 판별

```php
if (request()->isAjax()) { /* AJAX 요청 */ }
if (request()->isJson()) { /* JSON body */ }
if (request()->wantsJson()) { /* JSON 응답 원함 */ }
if (request()->isSecure()) { /* HTTPS */ }
```

### 파일 업로드

```php
if (request()->hasFile('avatar')) {
    $file = request()->file('avatar');
    // $file['name'], $file['tmp_name'], $file['size'], ...
}
```

---

## 내부 동작

### 입력 데이터 병합 순서

```text
inputCache = $_GET + $_POST   (생성자에서 병합)
input('key')
  → inputCache['key'] 존재? → 반환
  → 없으면 → json('key') 시도 (php://input 파싱)
```

### JSON body 파싱

- `json()` 최초 호출 시 `php://input`을 `json_decode` → `jsonCache`에 캐싱
- 이후 호출은 캐시에서 반환
- `Content-Type` 검사 없이 항상 파싱 시도 (잘못된 JSON은 빈 배열)

### 헤더 변환

```text
header('X-Custom-Header')
→ HTTP_X_CUSTOM_HEADER ($_SERVER 키)

특별 처리:
  Content-Type   → $_SERVER['CONTENT_TYPE']
  Content-Length → $_SERVER['CONTENT_LENGTH']
```

### IP 해석 위임

`ip()` 호출 시 `Cat\Ip`가 이미 오토로드되어 있으면 `\ip()->address()`로 위임한다 (trusted_proxies 설정 존중). 미로드 시 자체 폴백 로직으로 `X-Forwarded-For`, `X-Real-IP`, `Client-IP`, `REMOTE_ADDR` 순서로 탐색한다.

---

## 보안 고려사항

- **메서드 오버라이드**: POST 요청에서만 `_method` 필드 및 `X-HTTP-Method-Override` 헤더를 허용. GET 요청에서는 무시.
- **HTTPS 감지**: `$_SERVER['HTTPS']`, `X-Forwarded-Proto`, 포트 443 세 가지 조건으로 판별. 리버스 프록시 환경 지원.

---

## 주의사항

1. **`input()` vs 코어 `input()`**: `request()->input()` 메서드와 코어 헬퍼 `input()` 함수는 유사하지만 별개. 코어 `input()`은 Guard 살균 결과를 주입받을 수 있다.

2. **`raw()` 1회 읽기**: `php://input`은 PHP에서 1회만 읽을 수 있다 (일부 SAPI). `raw()`가 캐싱하지만, 외부에서 먼저 `file_get_contents('php://input')`을 호출하면 빈 문자열이 될 수 있다.

3. **`override()`는 테스트 전용**: 운영 코드에서 입력을 강제 교체하면 보안 위험. Swoole 환경에서는 `refresh()`로 슈퍼글로벌 동기화.

4. **`all()` 키 충돌**: GET과 POST에 같은 키가 있으면 POST가 우선 (array_merge 순서). JSON body는 그 뒤에 병합.

---

## 연관 도구

- [Response](Response.md) — HTTP 응답 빌더
- [Router](Router.md) — URL 라우팅 (dispatch 시 Request 활용)
- [Guard](Guard.md) — XSS 살균 미들웨어 (`input()` 데이터 살균)
- [Ip](Ip.md) — IP 분석 (`request()->ip()` 위임)
- [Auth](Auth.md) — JWT 인증 (`bearerToken()` 활용)
