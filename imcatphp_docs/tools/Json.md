# Json — JSON 응답 (CatUI 통합 포맷)

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Json` |
| 파일 | `catphp/Json.php` (196줄) |
| Shortcut | `json()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | `ext-json` |
| 호환 | CatUI `APIUtil` 표준 응답 형식 |

---

## 설정

별도 config 없음.

---

## 응답 포맷

CatUI `APIUtil`과 호환되는 표준 응답 형식:

```json
{
    "success": true,
    "statusCode": 200,
    "data": { ... },
    "message": "Success",
    "error": null,
    "timestamp": 1711612800
}
```

| 필드 | 타입 | 설명 |
| --- | --- | --- |
| `success` | `bool` | 성공 여부 — 항상 포함 |
| `statusCode` | `int` | HTTP 상태 코드 |
| `data` | `mixed` | 성공 시 데이터 |
| `message` | `string` | 응답 메시지 |
| `error` | `object\|null` | 실패 시 에러 정보 (`message`, `name`, `type`) |
| `timestamp` | `int` | Unix 타임스탬프 |

---

## 메서드 레퍼런스

### 성공 응답

| 메서드 | 시그니처 | 설명 |
| --- | --- | --- |
| `ok` | `ok(mixed $data = null, string $message = 'Success', int $statusCode = 200): never` | 성공 응답 |
| `created` | `created(mixed $data = null, string $message = 'Created'): never` | 201 생성 성공 |
| `noContent` | `noContent(): never` | 204 No Content |

### 실패/에러 응답

| 메서드 | 시그니처 | 설명 |
| --- | --- | --- |
| `fail` | `fail(string $message, int $statusCode = 422, ?array $details = null): never` | 검증 실패 |
| `error` | `error(string $message, int $statusCode = 500, string $type = 'server'): never` | 시스템 에러 |
| `unauthorized` | `unauthorized(string $message = 'Unauthorized'): never` | 401 인증 필요 |
| `forbidden` | `forbidden(string $message = 'Forbidden'): never` | 403 권한 없음 |
| `notFound` | `notFound(string $message = 'Not Found'): never` | 404 찾을 수 없음 |

### 페이지네이션/전송

| 메서드 | 시그니처 | 설명 |
| --- | --- | --- |
| `paginated` | `paginated(array $items, int $page, int $limit, int $total, string $message = 'Success'): never` | 페이지네이션 응답 |
| `send` | `send(int $statusCode, array $payload): never` | 커스텀 JSON 전송 |

---

## 사용 예제

### 성공 응답

```php
// 단순 성공
json()->ok();
// → {"success": true, "statusCode": 200, "data": null, "message": "Success", "error": null, "timestamp": ...}

// 데이터 포함
json()->ok(['user' => ['id' => 1, 'name' => '홍길동']]);
// → {"success": true, "statusCode": 200, "data": {"user": {...}}, "message": "Success", ...}

// 201 Created
json()->created(['id' => 123]);
// → {"success": true, "statusCode": 201, "data": {"id": 123}, "message": "Created", ...}

// 커스텀 메시지
json()->ok($data, '조회 성공');
```

### 실패 응답

```php
// 검증 실패
json()->fail('입력값이 올바르지 않습니다', 422, ['email' => '필수 항목']);
// → {"success": false, "statusCode": 422, "data": null, "message": "입력값이 올바르지 않습니다",
//    "error": {"message": "...", "name": "ValidationError", "type": "validation", "details": {...}}, ...}

// 상세 없이
json()->fail('이미 존재하는 이메일');
```

### 에러 응답

```php
// 편의 메서드
json()->unauthorized();           // 401
json()->forbidden();              // 403
json()->notFound('사용자 없음');   // 404

// 직접 지정
json()->error('Too Many Requests', 429);
json()->error('Internal Server Error');  // 기본 500
```

### 페이지네이션 응답

```php
$posts = db()->table('posts')->limit(20)->offset(0)->all();
$total = db()->table('posts')->count();

json()->paginated($posts, page: 1, limit: 20, total: $total);
// → {"success": true, "statusCode": 200, "data": {
//      "items": [...],
//      "pagination": {"page": 1, "limit": 20, "total": 100, "totalPages": 5, "hasNext": true, "hasPrev": false}
//    }, "message": "Success", ...}
```

### 커스텀 전송

```php
json()->send(200, [
    'success'   => true,
    'statusCode' => 200,
    'data'      => $data,
    'message'   => 'Success',
    'error'     => null,
    'timestamp' => time(),
]);
```

---

## CatUI 통합

CatPHP `json()`과 CatUI `APIUtil`이 동일한 응답 형식을 사용:

```javascript
// CatUI (프론트엔드)
const res = await APIUtil.get('/api/users');
if (res.success) {
    console.log(res.data);      // 서버의 json()->ok($data)
    console.log(res.message);   // "Success"
} else {
    console.log(res.error);     // 서버의 json()->fail() / json()->error()
}

// 페이지네이션
const res = await APIUtil.get('/api/posts?page=1');
if (res.success) {
    const { items, pagination } = res.data;
    console.log(pagination.totalPages, pagination.hasNext);
}
```

---

## 에러 이름 매핑

| statusCode | error.name |
| --- | --- |
| 400 | `BadRequest` |
| 401 | `Unauthorized` |
| 403 | `Forbidden` |
| 404 | `NotFound` |
| 408 | `RequestTimeout` |
| 422 | `ValidationError` |
| 429 | `TooManyRequests` |
| 500 | `InternalServerError` |
| 502 | `BadGateway` |
| 503 | `ServiceUnavailable` |

---

## 내부 동작

### 전송 흐름

```text
json()->ok($data)
├─ send(200, {success, statusCode, data, message, error, timestamp})
│   ├─ http_response_code(200)
│   ├─ header('Content-Type: application/json; charset=utf-8')
│   ├─ echo json_encode($payload, UNESCAPED_UNICODE | UNESCAPED_SLASHES)
│   └─ exit
```

### JSON 인코딩 옵션

| 옵션 | 효과 |
| --- | --- |
| `JSON_UNESCAPED_UNICODE` | 한글 등 유니코드 문자 그대로 출력 |
| `JSON_UNESCAPED_SLASHES` | 슬래시 이스케이프 안 함 |

---

## 주의사항

1. **모든 메서드는 `never` 반환**: 내부에서 `exit` 호출. 이후 코드 실행 불가.

2. **`fail()` vs `error()`**: `fail()`은 검증/비즈니스 오류(422)용, `error()`는 시스템 오류(500/401/403)용.

3. **`fail()` 매개변수 순서**: `fail($message, $statusCode, $details)` — details는 마지막.

4. **`send()`는 public**: 커스텀 페이로드 전송 가능하지만, CatUI 호환을 위해 표준 메서드 사용 권장.

---

## 연관 도구

- [Api](Api.md) — API 미들웨어 번들 (내부에서 `json()->unauthorized()` 등 호출)
- [Response](Response.md) — HTML/파일 응답 빌더
- [Paginate](Paginate.md) — 페이지네이션 (`paginated()` 데이터 소스)
- [Valid](Valid.md) — 검증 실패 시 `json()->fail()` 활용
