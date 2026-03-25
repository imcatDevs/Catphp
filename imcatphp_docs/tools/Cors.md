# Cors — CORS 헤더 관리

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Cors` |
| 파일 | `catphp/Cors.php` (119줄) |
| Shortcut | `cors()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`new self(...)`) |
| 의존 확장 | 없음 |

---

## 설정

```php
// config/app.php
'cors' => [
    'origins' => ['*'],                  // 허용 오리진 (기본 전체)
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
    'max_age' => 86400,                  // Preflight 캐시 (초, 기본 24시간)
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `handle` | `handle(): void` | `void` | CORS 헤더 전송 + OPTIONS 프리플라이트 자동 처리 |
| `origins` | `origins(array $origins): self` | `self` | 허용 오리진 설정 (이뮤터블) |
| `methods` | `methods(array $methods): self` | `self` | 허용 메서드 설정 |
| `headers` | `headers(array $headers): self` | `self` | 허용 헤더 설정 |
| `allow` | `allow(array $origins = [], array $methods = [], array $headers = []): self` | `self` | 종합 설정 (빈 배열은 기존값 유지) |

---

## 사용 예제

### 기본 사용 (config 기반)

```php
// 라우트 최상단 또는 미들웨어에서
cors()->handle();
```

### 특정 오리진만 허용

```php
cors()
    ->origins(['https://app.example.com', 'https://admin.example.com'])
    ->handle();
```

### API 전용 설정

```php
cors()
    ->origins(['https://frontend.example.com'])
    ->methods(['GET', 'POST'])
    ->headers(['Content-Type', 'Authorization'])
    ->handle();
```

### 종합 설정

```php
cors()->allow(
    origins: ['https://app.example.com'],
    methods: ['GET', 'POST', 'PUT'],
    headers: ['Content-Type', 'Authorization', 'X-Custom'],
)->handle();
```

### Api 도구와 연동

```php
// api()->cors()가 내부적으로 cors()->handle() 호출
api()->cors()->rateLimit(60, 100)->guard()->apply();
```

---

## 내부 동작

### handle() 처리 흐름

```text
handle() 호출
│
├─ Origin 헤더 파싱
│   ├─ CRLF 인젝션 방어 (\r, \n, \0 제거)
│   └─ URL 형식 검증 (http:// 또는 https:// 시작만 허용)
│
├─ 오리진 허용 확인
│   ├─ 허용 목록에 '*' 포함 → Access-Control-Allow-Origin: *
│   └─ 특정 오리진 매칭 → Access-Control-Allow-Origin: {origin}
│
├─ 공통 헤더 전송
│   ├─ Access-Control-Allow-Methods: ...
│   ├─ Access-Control-Allow-Headers: ...
│   └─ Access-Control-Max-Age: ...
│
├─ Credentials 헤더 (조건부)
│   └─ origins에 '*'가 없고 + 오리진이 있으면 → Allow-Credentials: true
│
└─ OPTIONS 요청?
    ├─ 예 → 204 응답 + Content-Length: 0 + exit
    └─ 아니오 → 계속 진행
```

### 이뮤터블 체이닝

`origins()`, `methods()`, `headers()`는 내부적으로 `cloneWith()`를 사용하여 새 인스턴스를 반환한다. readonly 프로퍼티이므로 `clone` 대신 `new self()`로 생성.

---

## 보안 고려사항

### Credentials와 와일드카드 제한

CORS 스펙에 따라 `Access-Control-Allow-Credentials: true`와 `Access-Control-Allow-Origin: *`는 **동시 사용 불가**하다. 이 도구는 이를 자동으로 처리:

```php
// origins에 '*' 포함 → Credentials 헤더 생략
// origins에 특정 도메인만 → Credentials: true 전송
```

### CRLF 인젝션 방어

`HTTP_ORIGIN` 헤더에서 `\r`, `\n`, `\0`을 제거하고 URL 형식 검증:

```php
if ($origin !== '' && !preg_match('#^https?://#i', $origin)) {
    $origin = '';  // 비정상 오리진 무시
}
```

### Preflight 캐시

`Access-Control-Max-Age` 헤더로 브라우저가 프리플라이트 결과를 캐싱한다. 기본 86400초(24시간). 개발 중에는 짧게 설정 권장.

---

## 주의사항

1. **`handle()` 호출 위치**: 라우트 핸들러 최상단 또는 미들웨어에서 호출. 다른 헤더 전송 전에 호출해야 함.

2. **OPTIONS 요청 자동 종료**: `handle()`에서 OPTIONS 요청은 204 + `exit`으로 즉시 종료된다. 핸들러 코드까지 도달하지 않음.

3. **와일드카드 `*`의 제약**: `origins: ['*']`로 설정하면 모든 오리진을 허용하지만, 쿠키/인증 정보를 포함한 요청(`withCredentials: true`)은 브라우저가 차단한다.

4. **다중 오리진**: CORS 스펙상 `Allow-Origin`은 단일 값만 허용. 이 도구는 `HTTP_ORIGIN`과 허용 목록을 비교하여 해당 오리진만 응답에 포함한다 (동적 오리진).

5. **Vary 헤더**: 동적 오리진 사용 시 `Vary: Origin` 헤더를 CDN/프록시에 설정해야 캐시 문제를 방지한다. 현재 자동 추가되지 않으므로 필요시 수동 추가.

---

## 연관 도구

- [Api](Api.md) — API 미들웨어 (`api()->cors()` 내부 호출)
- [Response](Response.md) — HTTP 응답 (`corsPreflightOk()`)
- [Router](Router.md) — 라우트 미들웨어에서 CORS 처리
