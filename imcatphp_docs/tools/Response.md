# Response — HTTP 응답 빌더

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Response` |
| 파일 | `catphp/Response.php` (340줄) |
| Shortcut | `response()` |
| 싱글턴 | `getInstance()` — 뮤터블 (전송 후 자동 reset) |
| 의존 확장 | 없음 |

---

## 설정

```php
// config/app.php
'response' => [
    'allowed_hosts' => ['trusted.example.com'],  // 외부 리다이렉트 허용 도메인
],
```

---

## 메서드 레퍼런스

### 상태 코드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `status` | `status(int $code): self` | `self` | HTTP 상태 코드 설정 |
| `getStatus` | `getStatus(): int` | `int` | 현재 상태 코드 |

### 헤더

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `header` | `header(string $name, string $value): self` | `self` | 응답 헤더 설정 (CRLF 인젝션 방어) |
| `withHeaders` | `withHeaders(array $headers): self` | `self` | 여러 헤더 한 번에 설정 |
| `contentType` | `contentType(string $type, string $charset = 'UTF-8'): self` | `self` | Content-Type 설정 |
| `noCache` | `noCache(): self` | `self` | 캐시 비활성화 헤더 3종 |
| `cache` | `cache(int $seconds): self` | `self` | 캐시 활성화 (Cache-Control + Expires) |

### 쿠키

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `cookie` | `cookie(string $name, string $value, int $expires = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true, string $sameSite = 'Lax'): self` | `self` | 응답 쿠키 추가 |
| `forgetCookie` | `forgetCookie(string $name, string $path = '/', string $domain = ''): self` | `self` | 쿠키 삭제 (과거 만료 설정) |

### 응답 본문

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `html` | `html(string $content, int $status = 0): never` | `never` | HTML 응답 전송 + exit |
| `text` | `text(string $content, int $status = 0): never` | `never` | 텍스트 응답 전송 + exit |
| `xml` | `xml(string $content, int $status = 0): never` | `never` | XML 응답 전송 + exit |
| `noContent` | `noContent(): never` | `never` | 204 빈 응답 |

### 리다이렉트

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `redirect` | `redirect(string $url, int $status = 302): never` | `never` | 리다이렉트 (오픈 리다이렉트 방어) |
| `back` | `back(string $fallback = '/'): never` | `never` | 이전 페이지 (Referer) |
| `permanentRedirect` | `permanentRedirect(string $url): never` | `never` | 301 영구 리다이렉트 |

### 다운로드 / 스트리밍

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `download` | `download(string $filePath, ?string $fileName = null, array $headers = []): never` | `never` | 파일 다운로드 (Content-Disposition: attachment) |
| `inline` | `inline(string $filePath, ?string $fileName = null): never` | `never` | 인라인 표시 (브라우저에서 열기) |
| `stream` | `stream(mixed $source, ?string $fileName = null, string $contentType = 'application/octet-stream'): never` | `never` | 스트리밍 응답 (resource 또는 callable) |

### CORS

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `corsPreflightOk` | `corsPreflightOk(): never` | `never` | CORS 프리플라이트 204 응답 |

---

## 사용 예제

### HTML / 텍스트 / XML 응답

```php
response()->html('<h1>Hello</h1>');
response()->text('Plain text response');
response()->xml('<root><item>1</item></root>');

// 상태 코드 지정
response()->status(403)->html('Forbidden');
response()->html('Not Found', 404);
```

### 헤더와 캐시

```php
response()
    ->header('X-Custom', 'value')
    ->noCache()
    ->html($content);

response()
    ->cache(3600)           // 1시간 캐시
    ->contentType('text/css')
    ->html($cssContent);
```

### 쿠키 설정

```php
response()
    ->cookie('theme', 'dark', 86400 * 30)    // 30일
    ->cookie('lang', 'ko', 86400 * 365)      // 1년
    ->html($content);

// 쿠키 삭제
response()->forgetCookie('theme')->html($content);
```

### 리다이렉트 사용

```php
response()->redirect('/dashboard');              // 302
response()->permanentRedirect('/new-url');        // 301
response()->back('/');                             // Referer 또는 폴백

// 외부 URL (allowed_hosts에 등록 필요)
response()->redirect('https://trusted.example.com/path');
// 미등록 외부 URL → '/'로 리다이렉트
```

### 파일 다운로드

```php
// 다운로드
response()->download('/path/to/report.pdf', '보고서.pdf');

// 브라우저에서 열기 (PDF 뷰어)
response()->inline('/path/to/document.pdf');
```

### 스트리밍

```php
// 파일 핸들 스트리밍
$fp = fopen('/path/to/big-file.csv', 'r');
response()->stream($fp, 'data.csv', 'text/csv');

// 콜백 스트리밍
response()->stream(function () {
    for ($i = 0; $i < 1000; $i++) {
        echo "data line {$i}\n";
        flush();
    }
}, 'output.txt', 'text/plain');
```

---

## 내부 동작

### 전송 흐름

```text
response()->status(200)->header('X-Key', 'val')->html($content)
│
├─ status(200)       → $this->statusCode = 200
├─ header(...)       → $this->headers['X-Key'] = 'val'
├─ html($content)    → contentType('text/html') → send($content)
│   │
│   ├─ applyHeaders()
│   │   ├─ http_response_code(200)
│   │   ├─ header(): 각 헤더 전송
│   │   ├─ setcookie(): 각 쿠키 전송
│   │   └─ reset(): statusCode=200, headers=[], cookies=[]
│   │
│   ├─ echo $content
│   └─ exit
```

### 싱글턴 자동 리셋

모든 응답 전송 메서드(`html`, `text`, `redirect` 등)는 `applyHeaders()` 내부에서 `reset()`을 호출하여 상태를 초기화한다. 싱글턴이므로 이전 응답의 헤더/쿠키가 다음 요청에 잔존하지 않는다.

> Swoole 환경에서 특히 중요 — 요청 간 상태 격리.

### 파일명 인코딩

`download()`와 `inline()`은 `rawurlencode()`로 파일명을 인코딩한다. 한글 파일명도 안전하게 처리된다.

```text
Content-Disposition: attachment; filename="보고서.pdf"
→ filename="%EB%B3%B4%EA%B3%A0%EC%84%9C.pdf"
```

---

## 보안 고려사항

### CRLF 인젝션 방어

`header()` 메서드에서 `\r`, `\n`을 제거한다. HTTP Response Splitting 공격을 방지.

```php
// 이 공격 시도는 개행이 제거되어 무효화
response()->header("X-Custom\r\nEvil: header", "value");
```

### 오픈 리다이렉트 방어

`redirect()`에서 외부 URL을 자동 차단한다:

1. `http://` / `https://`로 시작하는 URL 감지
2. 현재 호스트(`$_SERVER['HTTP_HOST']`)와 대상 호스트 비교
3. 다르면 `config('response.allowed_hosts')` 확인
4. 미등록 → `/`로 강제 리다이렉트

```php
// config: allowed_hosts = ['api.example.com']
response()->redirect('https://evil.com/steal');     // → '/' 리다이렉트
response()->redirect('https://api.example.com/ok');  // → 정상 리다이렉트
```

### back() 보안

`back()`도 `redirect()`를 경유하므로 동일한 오픈 리다이렉트 방어가 적용된다. 악의적인 `Referer` 헤더도 차단.

---

## 주의사항

1. **모든 응답 메서드는 `never` 반환**: `html()`, `redirect()`, `download()` 등은 내부에서 `exit`를 호출한다. 이후 코드는 실행되지 않는다.

2. **`headers_sent()` 체크**: 이미 출력이 시작된 경우(예: echo 호출 후) 헤더가 전송되지 않는다. `applyHeaders()`에서 `headers_sent()` 확인.

3. **`download()` 파일 존재 확인**: 파일이 없으면 404 응답. `is_file()` 검증.

4. **`stream()` 메모리**: 대용량 파일은 `fread(8192)` 청크 단위로 전송하여 메모리 효율적. 콜백 모드에서는 개발자가 `flush()`를 명시 호출해야 한다.

5. **쿠키 `expires` 파라미터**: 초 단위 **기간**이 아니라 **지금부터의 기간**이다. 내부에서 `time() + $expires`로 변환.

---

## 연관 도구

- [Request](Request.md) — HTTP 요청 추상화 (요청-응답 쌍)
- [Router](Router.md) — 핸들러 실행 후 응답 출력
- [Json](Json.md) — JSON 응답 (`json()->ok()` / `json()->fail()`)
- [Cors](Cors.md) — CORS 헤더 처리
