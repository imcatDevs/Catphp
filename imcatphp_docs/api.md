# API — Json · Api · Cors · Rate · Http

CatPHP의 API 개발 계층. JSON 응답, API 미들웨어 번들, CORS, 레이트 리미트, HTTP 클라이언트를 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Json | `json()` | `Cat\Json` | 73 |
| Api | `api()` | `Cat\Api` | 210 |
| Cors | `cors()` | `Cat\Cors` | 119 |
| Rate | `rate()` | `Cat\Rate` | 136 |
| Http | `http()` | `Cat\Http` | 246 |

---

## 목차

1. [Json — JSON 응답](#1-json--json-응답)
2. [Api — API 미들웨어 번들](#2-api--api-미들웨어-번들)
3. [Cors — CORS 관리](#3-cors--cors-관리)
4. [Rate — 레이트 리미트](#4-rate--레이트-리미트)
5. [Http — HTTP 클라이언트](#5-http--http-클라이언트)

---

## 1. Json — JSON 응답

통일된 JSON 응답 포맷. 모든 메서드는 `never` 반환 (내부에서 `exit`).

### Json 응답 구조 (CatUI 통합 포맷)

```json
{
  "success": true,
  "statusCode": 200,
  "data": "...",
  "message": "Success",
  "error": null,
  "timestamp": 1711612800
}
```

### Json 메서드

```php
// 성공 (200)
json()->ok(['id' => 1, 'name' => '홍길동']);

// 성공 (201 Created)
json()->created(['id' => 2]);

// 실패 — 검증 오류 등 (422)
json()->fail('검증 실패', 422, ['name' => '필수 항목입니다']);

// 에러
json()->error('서버 오류');
json()->notFound('Not Found');
json()->unauthorized();
json()->forbidden();

// 페이지네이션
json()->paginated(
    items: $users,
    page: 3,
    limit: 20,
    total: 150
);
// { "success": true, "data": { "items": [...], "pagination": { "page": 3, "limit": 20, "total": 150, "totalPages": 8, "hasNext": true, "hasPrev": true } } }

// 커스텀 페이로드
json()->send(200, ['custom' => 'payload']);
```

### Json 내부 동작

- `Content-Type: application/json; charset=utf-8` 자동 설정
- `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` 옵션
- `exit` 호출로 이후 코드 실행 차단

---

## 2. Api — API 미들웨어 번들

CORS, Rate Limit, Auth, Guard, JSON 검증을 체이닝으로 한 줄에 적용.

### Api 사용법

```php
// 개별 핸들러에서 호출
router()->post('/api/v1/posts', function () {
    api()->cors()->rateLimit(60, 100)->auth()->guard()->jsonOnly()->apply();

    $data = request()->json();
    // ... 처리
    json()->ok($result);
});

// config 기본값 사용 (프리셋)
api()->preset()->apply();
```

### Api 설정

```php
'api' => [
    'rate_window' => 60,     // 기본 윈도우 (초)
    'rate_max'    => 100,    // 기본 최대 횟수
    'cors'        => true,   // CORS 자동 적용
    'guard'       => true,   // Guard 자동 적용
    'json_only'   => true,   // JSON Content-Type 강제
],
```

### Api 체이닝 메서드

| 메서드 | 설명 |
| --- | --- |
| `cors()` | CORS 헤더 + OPTIONS 처리 |
| `rateLimit(window, max, key)` | 레이트 리미트 (엔드포인트별 키 분리 가능) |
| `auth()` | Bearer JWT 인증 |
| `guard()` | 입력 살균 + 요청 크기 제한 |
| `jsonOnly()` | POST/PUT/PATCH에 JSON Content-Type 강제 |
| `preset()` | config 기본값 로드 |
| `apply()` | 설정된 미들웨어 실행 |

### Api apply() 실행 순서

```text
apply()
├─ 1. CORS → cors()->handle()
│     └─ OPTIONS preflight → 204 exit
├─ 2. JSON Content-Type 검증
│     └─ POST/PUT/PATCH + 비JSON → 415 error
├─ 3. Rate Limit → rate()->limit()
│     ├─ 헤더: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset
│     └─ 초과 → 429 error + Retry-After 헤더
├─ 4. Auth → auth()->bearer() + verifyToken()
│     └─ 실패 → 401 error
└─ 5. Guard → 요청 크기 + 입력 살균
      └─ 초과 → 413 error
```

모든 미들웨어는 **이뮤터블 체이닝** (`clone`) — 원본 Api 인스턴스를 오염시키지 않는다.

---

## 3. Cors — CORS 관리

CORS 헤더 자동 전송 + OPTIONS preflight 처리.

### Cors 설정

```php
'cors' => [
    'origins' => ['https://example.com', 'https://app.example.com'],
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
    'max_age' => 86400,  // preflight 캐시 (24시간)
],
```

### Cors 사용법

```php
// 기본 사용 (config 기반)
cors()->handle();

// 체이닝으로 오버라이드
cors()->origins(['https://myapp.com'])->handle();
cors()->methods(['GET', 'POST'])->handle();
cors()->headers(['Content-Type', 'Authorization'])->handle();

// 종합 설정
cors()->allow(
    origins: ['https://myapp.com'],
    methods: ['GET', 'POST'],
    headers: ['Content-Type']
)->handle();
```

### Cors handle() 동작

1. Origin 헤더 검증 (CRLF 인젝션 방어 + URL 형식 체크)
2. `Access-Control-Allow-Origin` 설정 (허용 Origin 또는 `*`)
3. `Access-Control-Allow-Methods/Headers/Max-Age` 전송
4. `Access-Control-Allow-Credentials: true` (와일드카드 Origin이 아닌 경우만)
5. OPTIONS 요청 → 204 빈 응답 + exit

### Cors 보안

- **와일드카드 + Credentials 동시 사용 금지**: CORS 스펙에 따라 `origins: ['*']`일 때 Credentials 헤더를 보내지 않음
- **CRLF 인젝션 방어**: Origin 헤더에서 `\r`, `\n`, `\0` 제거
- **URL 형식 검증**: `https?://` 패턴이 아닌 Origin은 빈 문자열로 처리

---

## 4. Rate — 레이트 리미트

파일 기반 슬라이딩 윈도우 레이트 리미터.

### Rate 메서드

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `limit(key, window, max)` | `bool` | 허용 여부 (카운트 기록) |
| `check(key, window, max)` | `bool` | 허용 여부 (조회만, 기록 안 함) |
| `remaining(key, window, max)` | `int` | 남은 요청 횟수 |
| `reset(key)` | `bool` | 카운트 초기화 |

### Rate 사용 예시

```php
// 60초 동안 최대 10회 (로그인 엔드포인트)
if (!rate()->limit('login', 60, 10)) {
    json()->error('Too Many Requests', 429);
}

// 남은 횟수 확인
$remaining = rate()->remaining('login', 60, 10);

// 기록 없이 조회만
$allowed = rate()->check('login', 60, 10);

// 초기화
rate()->reset('login');
```

### Rate 내부 동작

- **슬라이딩 윈도우**: 각 요청의 타임스탬프를 기록, 윈도우 내 요청만 카운트
- **파일 저장**: `storage/rate/{md5(key:ip)}.json`
- **동시성**: `flock(LOCK_EX)` 배타 락
- **IP 기반**: `ip()->address()`로 클라이언트 식별

---

## 5. Http — HTTP 클라이언트

cURL 기반 HTTP 클라이언트. 이뮤터블 체이닝 API.

### Http 요청

```php
// GET
$response = http()->get('https://api.example.com/users', ['page' => 1]);

// POST (form-data)
$response = http()->post('https://api.example.com/users', ['name' => 'Alice']);

// JSON POST
$response = http()->json('https://api.example.com/users', ['name' => 'Alice']);

// PUT / PATCH / DELETE
$response = http()->put($url, $data);
$response = http()->patch($url, $data);
$response = http()->delete($url, $data);
```

### Http 체이닝

```php
$response = http()
    ->base('https://api.example.com')
    ->header('Authorization', 'Bearer xxx')
    ->header('Accept', 'application/json')
    ->timeout(10)
    ->get('/users');
```

### Http 파일 업로드/다운로드

```php
// 업로드 (multipart/form-data)
$response = http()->upload('https://api.example.com/upload', '/path/to/file.jpg', 'avatar', [
    'user_id' => 1,
]);

// 다운로드
$success = http()->download('https://example.com/file.pdf', '/local/path/file.pdf');
```

### HttpResponse 객체

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `status()` | `int` | HTTP 상태 코드 |
| `body()` | `string` | 응답 본문 |
| `json()` | `?array` | JSON 디코딩 |
| `ok()` | `bool` | 2xx 여부 |
| `error()` | `string` | cURL 에러 메시지 |
| `headers()` | `array` | 응답 헤더 파싱 |

```php
$response = http()->get('https://api.example.com/users/1');

if ($response->ok()) {
    $user = $response->json();
    echo $user['name'];
} else {
    echo "에러: " . $response->status() . " " . $response->error();
}

// 헤더 확인
$headers = $response->headers();
$contentType = $headers['Content-Type'] ?? '';
```

### Http 보안

- **CRLF 인젝션 방어**: 헤더 이름/값에서 `\r`, `\n`, `\0` 제거
- **리디렉트 제한**: `CURLOPT_MAXREDIRS = 5`
- **타임아웃**: 기본 30초 (`timeout()`으로 조정)

---

## 도구 간 연동

```text
Api → Cors (CORS 처리)
Api → Rate (레이트 리미트)
Api → Auth (JWT 인증)
Api → Guard (입력 살균)
Api → Json (에러 응답)
Rate → Ip (클라이언트 IP 감지)
Http → Telegram (API 호출)
Json → Paginate (페이지네이션 응답)
```
