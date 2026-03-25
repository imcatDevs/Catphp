# Http — HTTP 클라이언트

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Http` |
| 파일 | `catphp/Http.php` (246줄) |
| Shortcut | `http()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 확장 | `ext-curl` |
| 추가 클래스 | `Cat\HttpResponse` (값 객체, 같은 파일) |

---

## 설정

별도 config 없음. 기본 타임아웃 30초.

---

## 메서드 레퍼런스

### Http 클래스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `base` | `base(string $url): self` | `self` | 기본 URL 설정 (상대 경로 자동 결합) |
| `header` | `header(string $name, string $value): self` | `self` | 요청 헤더 추가 (CRLF 방어) |
| `timeout` | `timeout(int $seconds): self` | `self` | 타임아웃 설정 (기본 30초) |
| `get` | `get(string $url, array $query = []): HttpResponse` | `HttpResponse` | GET 요청 |
| `post` | `post(string $url, array\|string $data = []): HttpResponse` | `HttpResponse` | POST 요청 |
| `put` | `put(string $url, array\|string $data = []): HttpResponse` | `HttpResponse` | PUT 요청 |
| `delete` | `delete(string $url, array\|string $data = []): HttpResponse` | `HttpResponse` | DELETE 요청 |
| `patch` | `patch(string $url, array\|string $data = []): HttpResponse` | `HttpResponse` | PATCH 요청 |
| `json` | `json(string $url, array $data): HttpResponse` | `HttpResponse` | JSON POST (Content-Type 자동 설정) |
| `upload` | `upload(string $url, string $filePath, string $fieldName = 'file', array $extra = []): HttpResponse` | `HttpResponse` | 파일 업로드 (multipart/form-data) |
| `download` | `download(string $url, string $savePath): bool` | `bool` | 파일 다운로드 저장 |

### HttpResponse 클래스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `status` | `status(): int` | `int` | HTTP 상태 코드 |
| `body` | `body(): string` | `string` | 응답 본문 |
| `json` | `json(): ?array` | `?array` | JSON 디코딩 (실패 시 `null`) |
| `ok` | `ok(): bool` | `bool` | 2xx 성공 여부 |
| `error` | `error(): string` | `string` | cURL 에러 메시지 |
| `headers` | `headers(): array` | `array` | 응답 헤더 파싱 |

---

## 사용 예제

### 기본 GET / POST

```php
// GET
$res = http()->get('https://api.example.com/users');
$users = $res->json();

// GET + 쿼리 파라미터
$res = http()->get('https://api.example.com/search', ['q' => 'CatPHP', 'page' => 1]);

// POST (form-urlencoded)
$res = http()->post('https://api.example.com/users', [
    'name'  => '홍길동',
    'email' => 'hong@example.com',
]);
```

### JSON POST

```php
$res = http()->json('https://api.example.com/webhook', [
    'event' => 'user.created',
    'data'  => ['id' => 123],
]);

if ($res->ok()) {
    $data = $res->json();
}
```

### 기본 URL + 헤더 + 타임아웃

```php
$api = http()
    ->base('https://api.example.com/v1')
    ->header('Authorization', 'Bearer ' . $token)
    ->timeout(10);

$users = $api->get('/users')->json();
$posts = $api->get('/posts')->json();
$res   = $api->json('/events', $payload);
```

### 파일 업로드

```php
$res = http()->upload(
    'https://api.example.com/upload',
    '/path/to/image.jpg',
    'photo',                         // 폼 필드명
    ['title' => '프로필 사진']       // 추가 필드
);

if ($res->ok()) {
    $url = $res->json()['url'];
}
```

### 파일 다운로드

```php
$ok = http()->download(
    'https://example.com/report.pdf',
    '/storage/downloads/report.pdf'
);
// true: 성공, false: 실패 (4xx/5xx 또는 파일 쓰기 실패)
```

### 응답 처리

```php
$res = http()->get('https://api.example.com/data');

$res->status();     // 200
$res->body();       // 원본 문자열
$res->json();       // 배열 또는 null
$res->ok();         // true (200~299)
$res->error();      // cURL 에러 (성공 시 빈 문자열)
$res->headers();    // ['Content-Type' => 'application/json', ...]
```

### 에러 처리

```php
$res = http()->timeout(5)->get('https://slow-api.example.com/data');

if (!$res->ok()) {
    if ($res->status() === 0) {
        // cURL 에러 (타임아웃, DNS 실패 등)
        logger()->error('HTTP 오류: ' . $res->error());
    } else {
        // 서버 에러 (4xx, 5xx)
        logger()->warning("API {$res->status()}: " . $res->body());
    }
}
```

---

## 내부 동작

### 이뮤터블 체이닝

`base()`, `header()`, `timeout()`은 `clone`으로 새 인스턴스를 반환한다. 싱글턴의 기본 설정이 오염되지 않는다.

```php
$api = http()->base('https://a.com')->header('X-Key', '123');
$other = http()->base('https://b.com');  // $api의 헤더 없음
```

### cURL 옵션

| 옵션 | 값 | 비고 |
| --- | --- | --- |
| `CURLOPT_RETURNTRANSFER` | `true` | 문자열 반환 |
| `CURLOPT_TIMEOUT` | `$timeoutSec` | 기본 30초 |
| `CURLOPT_FOLLOWLOCATION` | `true` | 리다이렉트 추적 |
| `CURLOPT_MAXREDIRS` | `5` | 최대 리다이렉트 횟수 |
| `CURLOPT_HEADER` | `true` | 응답 헤더 포함 |

### POST 데이터 처리

| 입력 타입 | 처리 |
| --- | --- |
| `array` | `http_build_query()` → `application/x-www-form-urlencoded` |
| `string` | 그대로 전송 (JSON 등) |
| `json()` 메서드 | `json_encode()` + Content-Type: application/json |

### upload() 구현

`CURLFile` 객체를 사용하여 multipart/form-data 전송. `mime_content_type()`으로 MIME 자동 감지.

---

## 보안 고려사항

### CRLF 인젝션 방어

`header()` 메서드에서 `\r`, `\n`, `\0`을 제거한다:

```php
$c->headers[str_replace(["\r", "\n", "\0"], '', $name)] = str_replace(["\r", "\n", "\0"], '', $value);
```

### upload() 파일 검증

- `is_file()` 검증 — 파일이 없으면 status 0, 에러 메시지 반환
- 실제 파일만 업로드 가능 (디렉토리, 심볼릭 링크 차단)

---

## 주의사항

1. **`ext-curl` 필수**: cURL 확장이 없으면 `curl_init()` 호출 시 Fatal Error.

2. **리다이렉트 추적**: `FOLLOWLOCATION = true`이므로 리다이렉트된 최종 URL의 응답이 반환된다. 중간 응답이 필요하면 `false`로 변경해야 하지만 현재 설정 불가.

3. **`download()` 메모리**: 전체 응답을 메모리에 로드한 뒤 파일에 저장한다. 매우 큰 파일(수 GB)에는 부적합 — cURL의 `CURLOPT_FILE` 직접 사용 필요.

4. **`json()` 메서드 이름 충돌**: `Http::json()`은 JSON POST 메서드이고, `HttpResponse::json()`은 응답 파싱 메서드이다. 혼동 주의.

5. **SSL 인증서**: cURL의 기본 CA 번들을 사용한다. 자체 서명 인증서 환경에서는 별도 설정 필요.

6. **동시 요청**: 순차 실행만 지원. 병렬 HTTP 요청이 필요하면 `curl_multi_*` 또는 Swoole 코루틴 사용.

---

## 연관 도구

- [Webhook](Webhook.md) — Webhook 발송 (`http()` 대신 HMAC 서명 포함)
- [Telegram](Telegram.md) — 텔레그램 Bot API (`http()` 내부 사용)
- [Spider](Spider.md) — 웹 크롤러 (`http()` 내부 사용)
- [Api](Api.md) — API 미들웨어 (수신 측)
