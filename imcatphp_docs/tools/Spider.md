# Spider — 웹 스크래핑 / 콘텐츠 파서

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Spider` |
| 파일 | `catphp/Spider.php` (698줄) |
| Shortcut | `spider()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Http` (cURL 요청), `Cat\Guard` (XSS 살균) |

---

## 설정

```php
// config/app.php
'spider' => [
    'user_agent' => 'CatPHP Spider/1.0',
    'timeout'    => 30,  // 초
],
```

---

## 메서드 레퍼런스

### 패턴 설정 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `pattern` | `pattern(string $field, string $start, string $end, string\|array $remove = ''): self` | `self` | 토큰 패턴 등록 |
| `regex` | `regex(string $field, string $regex, int $group = 0): self` | `self` | 정규식 패턴 등록 |
| `skipAfter` | `skipAfter(string $field, string $token): self` | `self` | 필드 파싱 후 건너뛰기 |
| `startAt` | `startAt(string $token): self` | `self` | 파싱 시작점 설정 |

### HTTP 설정 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `cookie` | `cookie(string $name, string $value): self` | `self` | 쿠키 추가 |
| `referer` | `referer(string $url): self` | `self` | Referer 헤더 |
| `userAgent` | `userAgent(string $ua): self` | `self` | User-Agent |
| `header` | `header(string $name, string $value): self` | `self` | 커스텀 헤더 |
| `timeout` | `timeout(int $seconds): self` | `self` | 타임아웃 |
| `delay` | `delay(int $seconds): self` | `self` | 페이지 간 대기 |
| `sanitize` | `sanitize(bool $enabled): self` | `self` | Guard 살균 on/off |
| `encoding` | `encoding(string $from, string $to = 'UTF-8'): self` | `self` | 인코딩 변환 |

### 페이지네이션

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `paginate` | `paginate(string $param, int $start = 1, ?int $maxPages = null): self` | `self` | 페이지네이션 설정 |
| `each` | `each(\Closure $callback): self` | `self` | 페이지 단위 콜백 |

### 실행

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `fetch` | `fetch(string $url, string $method = 'GET', array $data = []): string` | `string` | 원본 콘텐츠 가져오기 |
| `parse` | `parse(string $url, string $method = 'GET', array $data = []): array` | `array` | 패턴 기반 파싱 |
| `parseContent` | `parseContent(string $content): array` | `array` | 문자열에서 직접 파싱 |
| `find` | `find(string $content, string $start, string $end, string\|array $remove = ''): ?string` | `?string` | 단일 값 빠른 추출 |

### 응답 정보

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `status` | `status(): int` | `int` | 마지막 HTTP 상태 코드 |
| `responseHeaders` | `responseHeaders(): string` | `string` | 마지막 응답 헤더 |
| `body` | `body(): string` | `string` | 마지막 응답 본문 |

---

## 사용 예제

### 토큰 패턴 파싱

```php
$items = spider()
    ->pattern('title', '<h2 class="name">', '</h2>')
    ->pattern('price', '<span class="price">', '</span>', ['$', ','])
    ->pattern('link', '<a href="', '"')
    ->startAt('<div class="product-list">')
    ->parse('https://example.com/products');

// [['title' => '상품A', 'price' => '29900', 'link' => '/product/1'], ...]
```

### 정규식 패턴

```php
$emails = spider()
    ->regex('email', '/[\w.+-]+@[\w-]+\.[\w.]+/')
    ->parse('https://example.com/contacts');

// [['email' => 'info@example.com'], ['email' => 'support@example.com'], ...]
```

### 혼합 패턴 (토큰 + 정규식)

```php
$items = spider()
    ->pattern('title', '<h2>', '</h2>')
    ->regex('price', '/\d{1,3}(,\d{3})*원/')
    ->parse('https://example.com/shop');
```

### 페이지네이션 파싱

```php
$allItems = spider()
    ->pattern('title', '<h2>', '</h2>')
    ->pattern('url', '<a href="', '"')
    ->paginate('page', 1, 10)  // ?page=1 ~ ?page=10
    ->delay(2)                  // 페이지 간 2초 대기
    ->parse('https://example.com/list');
```

### 페이지 단위 콜백 (메모리 절약)

```php
spider()
    ->pattern('title', '<h2>', '</h2>')
    ->pattern('content', '<div class="body">', '</div>')
    ->paginate('page', 1)
    ->each(function (array $pageItems, int $pageNo) {
        foreach ($pageItems as $item) {
            db()->table('articles')->insert($item);
        }
        logger()->info("페이지 {$pageNo}: " . count($pageItems) . "건 저장");
    })
    ->parse('https://example.com/articles');
```

### EUC-KR 인코딩 변환

```php
$items = spider()
    ->encoding('EUC-KR', 'UTF-8')
    ->pattern('title', '<td class="title">', '</td>')
    ->parse('https://legacy-site.kr/board');
```

### 커스텀 HTTP 설정

```php
$items = spider()
    ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
    ->referer('https://example.com/')
    ->cookie('session', 'abc123')
    ->header('Accept-Language', 'ko-KR')
    ->timeout(60)
    ->pattern('data', '<td>', '</td>')
    ->parse('https://example.com/protected');
```

### 문자열 직접 파싱

```php
$html = file_get_contents('page.html');
$items = spider()
    ->pattern('title', '<h1>', '</h1>')
    ->parseContent($html);
```

### 단일 값 추출

```php
$html = spider()->fetch('https://example.com');
$title = spider()->find($html, '<title>', '</title>');
```

---

## 내부 동작

### 토큰 파싱 알고리즘

```text
extractByToken($content)
├─ startToken 있으면 해당 위치로 offset 이동
├─ 반복 (최대 100,000회 안전장치):
│   ├─ 각 필드 순서대로:
│   │   ├─ strpos($content, $start, $offset) → 시작 위치
│   │   ├─ strpos($content, $end, $startPos) → 끝 위치
│   │   ├─ substr() → 값 추출
│   │   ├─ $remove 문자 제거
│   │   └─ skipToken 있으면 offset 추가 이동
│   ├─ 필드 하나라도 매칭 실패 → 루프 종료 (불완전 행 무시)
│   └─ Guard 살균 (doSanitize=true)
└─ 결과 배열 반환
```

### 정규식 파싱

```text
extractByRegex($content)
├─ 각 regex 필드: preg_match_all()
├─ 필드별 매치 배열 수집
├─ 최대 매치 수 기준 행 생성
└─ Guard 살균
```

### 혼합 모드

토큰 결과와 정규식 결과를 행 단위 병합. 행 수가 적은 쪽 기준으로 잘림.

### 페이지네이션 흐름

```text
parsePages($url)
├─ pageNo = pageStart
├─ 루프:
│   ├─ URL + ?param=pageNo
│   ├─ fetch() → extractAll()
│   ├─ 빈 결과 → break
│   ├─ each 콜백 있으면 즉시 처리
│   ├─ maxPages 도달 → break
│   └─ delay 대기
└─ 전체 결과 반환 (콜백 없는 경우)
```

---

## 보안 고려사항

- **Guard 살균**: 기본 활성 (`doSanitize = true`). 추출된 모든 값에 `guard()->clean()` / `guard()->cleanArray()` 적용하여 XSS 방지.
- **sanitize(false)**: 원본 HTML이 필요한 경우에만 비활성화. DB 저장 전 반드시 별도 살균 필요.
- **무한 루프 방지**: 토큰 파싱 `maxIterations = 100,000`으로 제한.

---

## 주의사항

1. **패턴 필수**: `parse()` 호출 전 `pattern()` 또는 `regex()` 최소 1개 등록 필요. 없으면 `RuntimeException`.
2. **이뮤터블**: 모든 설정 메서드가 `clone` 반환. 체이닝 결과를 변수에 저장해야 `status()` 등 사용 가능.
3. **delay() 준수**: 대상 서버에 과도한 요청을 보내지 않도록 적절한 대기 시간 설정.
4. **robots.txt**: Spider는 robots.txt를 자동 확인하지 않음. 사용자가 직접 확인 필요.
5. **불완전 행**: 토큰 파싱에서 필드 하나라도 매칭 실패하면 해당 행을 버리고 파싱 종료.
6. **인코딩**: `encoding()` 설정 시 `fetch()`와 `parseContent()` 모두에 적용.

---

## 연관 도구

- [Http](Http.md) — HTTP 클라이언트 (내부 사용)
- [Guard](Guard.md) — XSS 살균
- [DB](DB.md) — 파싱 결과 저장
