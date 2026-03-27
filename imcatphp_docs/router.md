# HTTP 라우팅 — Router · Request · Response

CatPHP의 HTTP 요청/응답 처리 계층. 라우팅, 요청 파싱, 응답 빌더를 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| Router | `router()` | `Cat\Router` | 394 |
| Request | `request()` | `Cat\Request` | 464 |
| Response | `response()` | `Cat\Response` | 340 |

---

## 목차

1. [Router — URL 라우팅](#1-router--url-라우팅)
2. [Request — HTTP 요청 추상화](#2-request--http-요청-추상화)
3. [Response — HTTP 응답 빌더](#3-response--http-응답-빌더)

---

## 1. Router — URL 라우팅

해시 O(1) 정확 매칭 우선, regex 폴백 방식의 고성능 라우터.

### 라우트 등록

```php
// 기본 HTTP 메서드
router()->get('/users', fn() => '사용자 목록');
router()->post('/users', fn() => '사용자 생성');
router()->put('/users/{id}', fn(int $id) => "수정: {$id}");
router()->patch('/users/{id}', fn(int $id) => "부분 수정: {$id}");
router()->delete('/users/{id}', fn(int $id) => "삭제: {$id}");
router()->options('/users', fn() => '');

// 복수 메서드
router()->match(['GET', 'POST'], '/search', fn() => '검색');

// 모든 메서드
router()->any('/webhook', fn() => '웹훅 수신');

// 리디렉트 라우트
router()->redirect('/old-page', '/new-page', 301);
```

### 동적 파라미터 & 타입 제약

`{param}` 문법으로 URL 파라미터를 캡처하고, `{param:type}`으로 타입 제약을 건다.

```php
// 기본 — 모든 문자 매칭
router()->get('/users/{id}', fn(string $id) => "User: {$id}");

// 숫자만 (자동 int 캐스팅)
router()->get('/users/{id:int}', fn(int $id) => "User: {$id}");

// URL 슬러그
router()->get('/posts/{slug:slug}', fn(string $slug) => "Post: {$slug}");

// UUID
router()->get('/items/{uuid:uuid}', fn(string $uuid) => "Item: {$uuid}");

// 날짜
router()->get('/archive/{date:date}', fn(string $date) => "Archive: {$date}");

// 불리언 (자동 bool 캐스팅)
router()->get('/feature/{flag:bool}', fn(bool $flag) => $flag ? 'ON' : 'OFF');

// 커스텀 정규식
router()->get('/code/{code:regex(\d{2,4})}', fn(string $code) => "Code: {$code}");
```

#### 내장 타입 목록

| 타입 | 정규식 | 캐스팅 | 예시 |
| --- | --- | --- | --- |
| `int` | `\d+` | `(int)` | `/users/42` |
| `alpha` | `[a-zA-Z0-9_\-]+` | — | `/tag/php-8` |
| `slug` | `[a-zA-Z0-9\-]+` | — | `/post/hello-world` |
| `uuid` | UUID v4 패턴 | — | `/item/550e8400-...` |
| `date` | `YYYY-MM-DD` | — | `/archive/2024-01-15` |
| `year` | `\d{4}` | — | `/year/2024` |
| `month` | `0[1-9]\|1[0-2]` | — | `/month/03` |
| `phone` | `\+?[\d\-]{7,15}` | — | `/call/010-1234-5678` |
| `zip` | `\d{5}(-\d{4})?` | — | `/area/06100` |
| `email` | 이메일 패턴 | — | `/user/a@b.com` |
| `ip` | IPv4 패턴 | — | `/block/192.168.1.1` |
| `hex` | `[0-9a-fA-F]+` | — | `/color/ff5733` |
| `korean` | 한글+영숫자+공백 | — | `/search/서울 맛집` |
| `bool` | `true\|false\|1\|0` | `(bool)` | `/flag/true` |
| `regex(...)` | 커스텀 | — | 직접 지정 |

### Router 그룹

```php
router()->group('/api/v1', function () {
    router()->get('/users', fn() => json()->ok([]));
    router()->get('/users/{id:int}', fn(int $id) => json()->ok(['id' => $id]));
    router()->post('/users', fn() => json()->ok(['created' => true]));
});
// 결과: /api/v1/users, /api/v1/users/{id:int}, ...
```

중첩 그룹도 가능:

```php
router()->group('/api', function () {
    router()->group('/v2', function () {
        router()->get('/items', fn() => '...');
        // → /api/v2/items
    });
});
```

### 미들웨어

```php
// 글로벌 미들웨어 등록
router()->use(function (string $method, string $uri): ?bool {
    // 인증 체크
    if (str_starts_with($uri, '/admin') && !auth()->check()) {
        redirect('/login');
    }
    return null; // null 또는 미반환 → 계속 진행
    // return false; → 요청 처리 중단
});
```

미들웨어 실행 순서: 등록 순서대로 실행, `false` 반환 시 이후 미들웨어 및 핸들러 실행 중단.

### 404 핸들러

```php
router()->notFound(function () {
    return render('404');
});
```

404 기본 동작:
- **API 요청** (`/api/`로 시작): JSON 에러 응답
- **웹 요청**: HTML `<h1>404 Not Found</h1>`

### 디스패치 흐름

```text
router()->dispatch()
│
├─ URI 파싱 (null 바이트 제거, 트레일링 슬래시 정규화)
├─ 미들웨어 순차 실행
│   └─ false 반환 시 → 중단
│
├─ ① 정적 라우트 매칭 (해시 O(1))
│   └─ 히트 → 핸들러 실행
│
├─ ② 동적 라우트 매칭 (regex 순회)
│   └─ 히트 → 파라미터 캐스팅 + 핸들러 실행
│
├─ ③ HEAD → GET 폴백 (정적+동적)
│   └─ 히트 → 핸들러 실행 (출력 버퍼 폐기)
│
└─ ④ 404 핸들러 실행
```

### 핸들러 반환 규칙

| 반환 타입 | 동작 |
| --- | --- |
| `string` | Router가 `echo` 출력 |
| `void` | 핸들러 내부에서 직접 출력 (`json()->ok()` 등) |
| `never` | 핸들러 내부에서 `exit` (`redirect()` 등) |

### 템플릿 렌더링

```php
// 뷰 경로 설정
router()->setViewPath(__DIR__ . '/views');

// 렌더링
$html = render('home', ['title' => '홈']);
// → views/home.php를 렌더링

// 서브디렉토리
$html = render('demo.basic', ['items' => $list]);
// → views/demo/basic.php
```

#### 렌더링 보안

- null 바이트 제거
- `..` 디렉토리 탈출 차단
- `realpath()` 기반 뷰 디렉토리 경계 검증

---

## 2. Request — HTTP 요청 추상화

`$_GET`, `$_POST`, `$_FILES`, `$_SERVER`, `$_COOKIE`, `php://input`을 통합한 요청 래퍼.

### 입력 데이터

```php
// GET + POST + JSON body에서 값 가져오기
$name = request()->input('name', 'default');

// GET 파라미터만
$page = request()->query('page', '1');

// POST 파라미터만
$token = request()->post('csrf_token');

// JSON body
$data = request()->json();          // 전체 배열
$email = request()->json('email');  // 특정 키

// Raw body
$raw = request()->raw();

// 전체 입력 (GET + POST + JSON 병합)
$all = request()->all();

// 특정 키만
$filtered = request()->only(['name', 'email']);

// 특정 키 제외
$rest = request()->except(['password', 'token']);

// 존재 확인
if (request()->has('email')) { ... }

// 비어있지 않은지 확인
if (request()->filled('name')) { ... }
```

### 파일 업로드

```php
// 파일 정보
$file = request()->file('avatar');
// ['name' => 'photo.jpg', 'type' => 'image/jpeg', 'tmp_name' => '...', 'error' => 0, 'size' => 12345]

// 파일 존재 확인
if (request()->hasFile('avatar')) {
    // 파일 처리
}
```

### HTTP 메서드

```php
$method = request()->method();      // 'GET', 'POST', ...
request()->isMethod('POST');        // true/false
request()->isGet();                 // GET인지
request()->isPost();                // POST인지
```

> **메서드 오버라이드**: POST 요청에서 `_method` 필드 또는 `X-HTTP-Method-Override` 헤더로 PUT/PATCH/DELETE를 에뮬레이션할 수 있다.

### 요청 정보

```php
request()->path();           // '/users/1' (쿼리스트링 제외)
request()->url();            // 'https://example.com/users/1'
request()->fullUrl();        // 'https://example.com/users/1?tab=posts'
request()->queryString();    // 'tab=posts'
request()->scheme();         // 'https'
request()->host();           // 'example.com'
request()->port();           // 443
request()->isSecure();       // true (HTTPS)
```

### 헤더

```php
// 단일 헤더
$auth = request()->header('Authorization');
$ct   = request()->header('Content-Type');

// 모든 헤더
$headers = request()->headers();
// ['ACCEPT' => 'text/html', 'HOST' => 'example.com', ...]

// Bearer 토큰 추출
$token = request()->bearerToken();
// Authorization: Bearer xxx → 'xxx'

// Content-Type
$ct = request()->contentType();
```

### 요청 타입 판별

```php
request()->isJson();     // Content-Type이 JSON인지
request()->isAjax();     // X-Requested-With: XMLHttpRequest
request()->wantsJson();  // Accept: application/json
request()->accepts('text/html');  // Accept 헤더 확인
```

### 클라이언트 정보

```php
request()->ip();         // 클라이언트 IP (Cat\Ip 존재 시 위임)
request()->userAgent();  // User-Agent 문자열
request()->referer();    // Referer URL
```

### 쿠키

```php
request()->cookie('session_id');         // 쿠키 값
request()->hasCookie('session_id');      // 존재 확인
```

### 서버 변수

```php
request()->server('REMOTE_ADDR');   // 서버 변수 접근
```

### 테스트 지원

```php
// 입력 데이터 덮어쓰기 (테스트용)
request()->override(['name' => '테스트', 'email' => 'test@test.com']);

// 현재 슈퍼글로벌로 리프레시
request()->refresh();
```

### Request 전체 메서드 요약

| 그룹 | 메서드 |
| --- | --- |
| **입력** | `input`, `query`, `post`, `json`, `raw`, `all`, `only`, `except`, `has`, `filled` |
| **파일** | `file`, `hasFile` |
| **메서드** | `method`, `isMethod`, `isGet`, `isPost` |
| **URL** | `path`, `url`, `fullUrl`, `queryString`, `scheme`, `host`, `port` |
| **헤더** | `header`, `headers`, `bearerToken`, `contentType` |
| **판별** | `isJson`, `isAjax`, `isSecure`, `wantsJson`, `accepts` |
| **클라이언트** | `ip`, `userAgent`, `referer` |
| **쿠키** | `cookie`, `hasCookie` |
| **서버** | `server` |
| **테스트** | `override`, `refresh` |

---

## 3. Response — HTTP 응답 빌더

상태 코드, 헤더, 쿠키, 본문을 체이닝으로 구성하는 응답 빌더.

> **핵심**: `html()`, `text()`, `redirect()` 등 본문 출력 메서드는 모두 `never` 반환 — 내부에서 `exit`한다.

### 상태 코드

```php
response()->status(404)->html('Not Found');
response()->getStatus();  // 현재 상태 코드
```

### 헤더

```php
// 단일 헤더
response()->header('X-Custom', 'value');

// 여러 헤더
response()->withHeaders([
    'X-Request-Id' => 'abc123',
    'X-Version'    => '1.0',
]);

// Content-Type
response()->contentType('application/xml', 'UTF-8');

// 캐시 제어
response()->noCache();          // 캐시 비활성화
response()->cache(3600);        // 1시간 캐시
```

#### Response 헤더 보안

모든 헤더 이름/값에서 `\r`, `\n` 제거 — HTTP Response Splitting 공격 차단.

### 쿠키

```php
// 쿠키 설정
response()->cookie(
    name: 'session',
    value: 'abc123',
    expires: 3600,        // 1시간 후 만료
    path: '/',
    domain: '',
    secure: true,
    httpOnly: true,
    sameSite: 'Lax'
);

// 쿠키 삭제
response()->forgetCookie('session');
```

### 본문 응답

```php
// HTML
response()->html('<h1>Hello</h1>');
response()->status(201)->html('<p>Created</p>');

// 텍스트
response()->text('OK');

// XML
response()->xml('<root><item>1</item></root>');

// 빈 응답 (204)
response()->noContent();
```

### 리디렉트

```php
// 기본 리디렉트 (302)
response()->redirect('/dashboard');

// 영구 리디렉트 (301)
response()->permanentRedirect('/new-url');

// 이전 페이지로
response()->back('/fallback');
```

#### 리디렉트 보안

- **오픈 리디렉트 방어**: 외부 URL은 `config('response.allowed_hosts')`에 등록된 호스트만 허용
- **CRLF 인젝션 방어**: Location 헤더에서 `\r`, `\n` 제거
- `back()`도 동일한 오픈 리디렉트 방어 적용

### 파일 다운로드 & 스트리밍

```php
// 파일 다운로드
response()->download('/path/to/report.pdf', 'monthly-report.pdf');

// 인라인 표시 (브라우저에서 열기)
response()->inline('/path/to/document.pdf');

// 스트리밍 (대용량 파일)
$handle = fopen('big-data.csv', 'r');
response()->stream($handle, 'export.csv', 'text/csv');

// 콜백 기반 스트리밍
response()->stream(function () {
    for ($i = 0; $i < 1000; $i++) {
        echo "row {$i}\n";
        flush();
    }
}, 'data.txt', 'text/plain');
```

### CORS 프리플라이트

```php
response()->corsPreflightOk();  // 204 빈 응답
```

### 싱글턴 상태 초기화

Response는 싱글턴이므로, `send()` 후 자동으로 상태(status, headers, cookies)를 초기화(`reset()`)한다. 다음 요청에서 이전 상태가 누적되지 않는다.

### Response 전체 메서드 요약

| 그룹 | 메서드 |
| --- | --- |
| **상태** | `status`, `getStatus` |
| **헤더** | `header`, `withHeaders`, `contentType`, `noCache`, `cache` |
| **쿠키** | `cookie`, `forgetCookie` |
| **본문** | `html`, `text`, `xml`, `noContent` |
| **리디렉트** | `redirect`, `back`, `permanentRedirect` |
| **파일** | `download`, `inline`, `stream` |
| **CORS** | `corsPreflightOk` |

---

## 도구 간 연동

```text
Router ← Request (dispatch에서 $_SERVER 사용, 향후 통합 가능)
Router ← Response (리디렉트 라우트에서 \redirect() 사용)
Router → render() (템플릿 렌더링)
Request → Cat\Ip (ip() 메서드에서 위임)
Response → config (allowed_hosts 설정 참조)
```
