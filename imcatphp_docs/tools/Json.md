# Json — JSON 응답 (통일 포맷)

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Json` |
| 파일 | `catphp/Json.php` (73줄) |
| Shortcut | `json()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | `ext-json` |

---

## 설정

별도 config 없음.

---

## 응답 포맷

모든 JSON 응답은 다음 구조를 따른다:

```json
{
    "ok": true,
    "data": { ... },
    "error": { "message": "...", "code": 422, "details": { ... } },
    "meta": { "total": 100, "page": 1, "per_page": 20, "last_page": 5 }
}
```

- **`ok`**: `true` (성공) / `false` (실패) — 항상 포함
- **`data`**: 성공 시 데이터 — `ok()`, `paginated()`에서 사용
- **`error`**: 실패 시 에러 정보 — `fail()`, `error()`에서 사용
- **`meta`**: 페이지네이션 메타 — `paginated()`에서 사용

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `ok` | `ok(mixed $data = null, int $code = 200): never` | `never` | 성공 응답 |
| `fail` | `fail(string $message, mixed $details = null, int $code = 422): never` | `never` | 실패 응답 (검증 오류 등) |
| `error` | `error(string $message, int $code = 500): never` | `never` | 에러 응답 (서버 에러 등) |
| `paginated` | `paginated(array $data, int $total, int $page, int $perPage, int $code = 200): never` | `never` | 페이지네이션 응답 |
| `send` | `send(int $code, array $payload): never` | `never` | 커스텀 JSON 전송 |

---

## 사용 예제

### 성공 응답

```php
// 단순 성공
json()->ok();
// → {"ok": true, "data": null}

// 데이터 포함
json()->ok(['user' => ['id' => 1, 'name' => '홍길동']]);
// → {"ok": true, "data": {"user": {"id": 1, "name": "홍길동"}}}

// 201 Created
json()->ok(['id' => 123], 201);
```

### 실패 응답

```php
// 검증 실패
json()->fail('입력값이 올바르지 않습니다', ['email' => '필수 항목']);
// → {"ok": false, "error": {"message": "입력값이 올바르지 않습니다", "details": {"email": "필수 항목"}}}

// 상세 없이
json()->fail('이미 존재하는 이메일');
// → {"ok": false, "error": {"message": "이미 존재하는 이메일"}}
```

### 에러 응답

```php
json()->error('Unauthorized', 401);
// → {"ok": false, "error": {"message": "Unauthorized", "code": 401}}

json()->error('Internal Server Error', 500);
// → {"ok": false, "error": {"message": "Internal Server Error", "code": 500}}
```

### 페이지네이션 응답

```php
$posts = db()->table('posts')->limit(20)->offset(0)->all();
$total = db()->table('posts')->count();

json()->paginated($posts, $total, page: 1, perPage: 20);
// → {"ok": true, "data": [...], "meta": {"total": 100, "page": 1, "per_page": 20, "last_page": 5}}
```

### 커스텀 전송

```php
json()->send(200, [
    'ok'      => true,
    'data'    => $data,
    'version' => '1.0',
]);
```

---

## 내부 동작

### 전송 흐름

```text
json()->ok($data)
├─ send(200, ['ok' => true, 'data' => $data])
│   ├─ http_response_code(200)
│   ├─ header('Content-Type: application/json; charset=utf-8')
│   ├─ echo json_encode($payload, UNESCAPED_UNICODE | UNESCAPED_SLASHES)
│   └─ exit
```

### JSON 인코딩 옵션

| 옵션 | 효과 |
| --- | --- |
| `JSON_UNESCAPED_UNICODE` | 한글 등 유니코드 문자 그대로 출력 (`\uD55C` 대신 `한`) |
| `JSON_UNESCAPED_SLASHES` | 슬래시 이스케이프 안 함 (`\/` 대신 `/`) |

### last_page 계산

```php
$lastPage = (int) ceil($total / max($perPage, 1));
```

`max($perPage, 1)` — 0으로 나누기 방지.

---

## 주의사항

1. **모든 메서드는 `never` 반환**: `ok()`, `fail()`, `error()`, `paginated()`, `send()` 모두 내부에서 `exit` 호출. 이후 코드 실행 불가.

2. **`headers_sent()` 체크**: 이미 출력이 시작된 경우 Content-Type 헤더가 전송되지 않을 수 있다.

3. **`fail()` vs `error()`**: `fail()`은 검증/비즈니스 오류(422)용, `error()`는 시스템 오류(500/401/403)용. `fail()`에는 `details` 추가 가능.

4. **`send()`는 public**: 커스텀 페이로드 전송에 사용할 수 있지만, 통일 포맷을 위해 `ok()`/`fail()`/`error()` 사용 권장.

---

## 연관 도구

- [Api](Api.md) — API 미들웨어 번들 (내부에서 `json()->error()` 호출)
- [Response](Response.md) — HTML/파일 응답 빌더
- [Paginate](Paginate.md) — 페이지네이션 (`paginated()` 데이터 소스)
- [Valid](Valid.md) — 검증 실패 시 `json()->fail()` 활용
