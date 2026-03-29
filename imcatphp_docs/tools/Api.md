# Api — API 미들웨어 번들

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Api` |
| 파일 | `catphp/Api.php` (210줄) |
| Shortcut | `api()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Cors`, `Cat\Rate`, `Cat\Auth`, `Cat\Guard`, `Cat\Json` |

---

## 설정

```php
// config/app.php
'api' => [
    'rate_window' => 60,       // 기본 Rate Limit 윈도우 (초)
    'rate_max'    => 100,      // 기본 최대 요청 횟수
    'cors'        => true,     // 기본 CORS 적용
    'guard'       => true,     // 기본 Guard 적용
    'json_only'   => false,    // POST/PUT/PATCH에 JSON 강제
],
```

---

## 메서드 레퍼런스

### 빌더 메서드 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `cors` | `cors(): self` | `self` | CORS 헤더 적용 |
| `rateLimit` | `rateLimit(int $window, int $max, string $key = 'api'): self` | `self` | 속도 제한 (키 분리 가능) |
| `auth` | `auth(): self` | `self` | JWT 인증 강제 |
| `guard` | `guard(): self` | `self` | 입력 살균 + 요청 크기 제한 |
| `jsonOnly` | `jsonOnly(): self` | `self` | POST/PUT/PATCH에 JSON Content-Type 강제 |
| `preset` | `preset(): self` | `self` | config 기본값 로드 |

### 실행

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `apply` | `apply(): void` | `void` | 설정된 미들웨어 모두 실행 |

---

## 사용 예제

### 기본 사용

```php
router()->group('/api', function () {
    // 모든 API에 CORS + Rate Limit + Guard 적용
    router()->get('/users', function () {
        api()->cors()->rateLimit(60, 100)->guard()->apply();
        json()->ok(db()->table('users')->all());
    });
});
```

### 인증 필수 API

```php
router()->post('/api/posts', function () {
    api()->cors()->auth()->jsonOnly()->guard()->apply();
    // auth()->apiUser()로 인증 사용자 접근 가능
    $userId = auth()->id();
    $id = db()->table('posts')->insert([
        'user_id' => $userId,
        'title'   => input('title'),
        'body'    => input('body'),
    ]);
    json()->created(['id' => $id]);
});
```

### 엔드포인트별 Rate Limit

```php
// 로그인: 5분에 10회
router()->post('/api/login', function () {
    api()->cors()->rateLimit(300, 10, 'login')->apply();
    // ...
});

// 일반 API: 1분에 100회
router()->get('/api/data', function () {
    api()->cors()->rateLimit(60, 100, 'data')->apply();
    // ...
});
```

### config 프리셋

```php
// config 값 기반 자동 설정
router()->get('/api/items', function () {
    api()->preset()->apply();
    json()->ok(db()->table('items')->all());
});
```

---

## 내부 동작

### apply() 실행 순서

```text
apply()
│
├─ 1. CORS (applyCors)
│   └─ cors()->handle() → OPTIONS 시 204 + exit
│
├─ 2. JSON Content-Type 강제 (applyJsonOnly)
│   └─ POST/PUT/PATCH만 검사 → 비JSON 시 415 + exit
│
├─ 3. Rate Limit (rateWindow + rateMax)
│   ├─ rate()->limit($key, $window, $max)
│   ├─ 표준 헤더 전송:
│   │   ├─ X-RateLimit-Limit: {max}
│   │   ├─ X-RateLimit-Remaining: {remaining}
│   │   └─ X-RateLimit-Reset: {window}
│   └─ 초과 시 429 + Retry-After + exit
│
├─ 4. Auth (applyAuth)
│   ├─ auth()->bearer() → 없으면 401 + exit
│   ├─ auth()->verifyToken() → 실패 시 401 + exit
│   └─ auth()->setApiUser($payload)
│
└─ 5. Guard (applyGuard)
    ├─ guard()->maxBodySize() → 초과 시 413 + exit
    ├─ guard()->all() → 전체 입력 살균
    └─ input(data: $sanitized)
```

### 이뮤터블 체이닝

모든 빌더 메서드가 `clone`을 사용. 싱글턴 상태 오염 없이 호출마다 독립 인스턴스:

```php
$base = api()->cors()->guard();
$public = $base->rateLimit(60, 200);       // cors + guard + rate(200)
$private = $base->auth()->rateLimit(60, 50); // cors + guard + auth + rate(50)
```

### Rate Limit 표준 헤더

RFC 6585 + draft-ietf-httpapi-ratelimit-headers 준수:

| 헤더 | 설명 |
| --- | --- |
| `X-RateLimit-Limit` | 최대 허용 횟수 |
| `X-RateLimit-Remaining` | 남은 횟수 |
| `X-RateLimit-Reset` | 윈도우 리셋 시간 (초) |
| `Retry-After` | 429 응답 시 재시도 대기 (초) |

---

## 보안 고려사항

- **실행 순서 최적화**: CORS → JSON 검증 → Rate Limit → Auth → Guard 순서로, 비용이 적은 검사를 먼저 수행
- **OPTIONS 조기 종료**: CORS preflight는 다른 미들웨어 없이 즉시 응답
- **429 응답**: Rate Limit 초과 시 `json()->error()` 통일 포맷으로 응답

---

## 주의사항

1. **핸들러 내부에서 호출**: `apply()`는 라우트 핸들러 **내부**에서 호출해야 한다. 미들웨어 체인과 달리, 각 핸들러가 필요한 미들웨어를 선택적으로 적용하는 패턴.

2. **`apply()`는 void**: 정상 통과 시 아무것도 반환하지 않는다. 실패 시 내부에서 `json()->error()` + `exit`.

3. **`preset()`과 수동 설정 결합**: `preset()` 후 추가 빌더 호출로 config 기본값을 오버라이드 가능:

   ```php
   api()->preset()->rateLimit(30, 50)->apply();  // preset + 커스텀 rate
   ```

4. **`jsonOnly()` 적용 범위**: GET/HEAD/OPTIONS/DELETE에는 적용되지 않는다 (body가 없는 요청).

---

## 연관 도구

- [Json](Json.md) — JSON 응답 포맷
- [Cors](Cors.md) — CORS 헤더 (`api()->cors()` 내부 사용)
- [Rate](Rate.md) — 속도 제한 (`api()->rateLimit()` 내부 사용)
- [Auth](Auth.md) — JWT 인증 (`api()->auth()` 내부 사용)
- [Guard](Guard.md) — 입력 살균 (`api()->guard()` 내부 사용)
