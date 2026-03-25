# Router — URL 라우팅 + 뷰 렌더링

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Router` |
| 파일 | `catphp/Router.php` (394줄) |
| Shortcut | `router()` |
| 싱글턴 | `getInstance()` — 뮤터블 (라우트 등록은 상태 누적) |
| 의존 확장 | 없음 |
| 추가 정의 | `Cat\HttpMethod` enum (GET/POST/PUT/PATCH/DELETE/OPTIONS/HEAD) |

---

## 설정

```php
// config/app.php
'view' => [
    'path' => __DIR__ . '/../Public/views',   // 뷰 디렉토리 경로
],
```

---

## 메서드 레퍼런스

### 라우트 등록

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `get` | `get(string $path, callable $handler): self` | `self` | GET 라우트 |
| `post` | `post(string $path, callable $handler): self` | `self` | POST 라우트 |
| `put` | `put(string $path, callable $handler): self` | `self` | PUT 라우트 |
| `patch` | `patch(string $path, callable $handler): self` | `self` | PATCH 라우트 |
| `delete` | `delete(string $path, callable $handler): self` | `self` | DELETE 라우트 |
| `options` | `options(string $path, callable $handler): self` | `self` | OPTIONS 라우트 |
| `match` | `match(array $methods, string $path, callable $handler): self` | `self` | 복수 HTTP 메서드 |
| `any` | `any(string $path, callable $handler): self` | `self` | 모든 HTTP 메서드 |
| `redirect` | `redirect(string $from, string $to, int $code = 301): self` | `self` | 리디렉트 라우트 |

### 라우트 구조

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `group` | `group(string $prefix, callable $callback): self` | `self` | 라우트 그룹 (prefix 자동 결합) |
| `use` | `use(callable $middleware): self` | `self` | 글로벌 미들웨어 등록 |
| `notFound` | `notFound(callable $handler): self` | `self` | 404 핸들러 설정 |

### 디스패치

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `dispatch` | `dispatch(): void` | `void` | 요청 URL을 매칭하고 핸들러 실행 |

### 뷰 렌더링

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `render` | `render(string $template, array $data = []): string` | `string` | PHP 뷰 템플릿 렌더링 |
| `setViewPath` | `setViewPath(string $path): self` | `self` | 뷰 디렉토리 직접 설정 |

---

## 파라미터 타입 시스템

`{param}` 또는 `{param:type}` 형식으로 동적 라우트 파라미터를 정의한다.

| 타입 | 정규식 | 설명 | 자동 캐스팅 |
| --- | --- | --- | --- |
| (없음) | `([^/]+)` | 모든 문자 (기본) | `string` |
| `int` | `(\d+)` | 숫자만 | `int` |
| `alpha` | `([a-zA-Z0-9_\-]+)` | 영숫자/언더스코어/하이픈 | `string` |
| `slug` | `([a-zA-Z0-9\-]+)` | URL 슬러그 | `string` |
| `uuid` | UUID v4 패턴 | UUID 포맷 | `string` |
| `date` | `YYYY-MM-DD` | 날짜 | `string` |
| `year` | `(\d{4})` | 연도 4자리 | `string` |
| `month` | `(0[1-9]\|1[0-2])` | 월 01~12 | `string` |
| `phone` | `(\+?[\d\-]{7,15})` | 전화번호 | `string` |
| `zip` | `(\d{5}(?:\-\d{4})?)` | 우편번호 (한국/미국) | `string` |
| `email` | 이메일 패턴 | 이메일 주소 | `string` |
| `ip` | IPv4 패턴 | IPv4 주소 | `string` |
| `hex` | `([0-9a-fA-F]+)` | 16진수 | `string` |
| `korean` | `([\p{Hangul}a-zA-Z0-9\-\s]+)` | 한글+영숫자 | `string` |
| `bool` | `(true\|false\|1\|0)` | 불린 | `bool` |
| `regex(...)` | 커스텀 | 직접 정규식 지정 | `string` |

---

## 사용 예제

### 기본 라우트

```php
router()->get('/', fn() => 'Hello CatPHP!');
router()->get('/about', fn() => render('about'));
router()->post('/contact', function () { /* ... */ });
```

### 동적 파라미터

```php
// 기본 (string)
router()->get('/user/{id}', function (string $id) {
    return "User: {$id}";
});

// 타입 지정 (int 자동 캐스팅)
router()->get('/post/{id:int}', function (int $id) {
    $post = db()->table('posts')->where('id', $id)->first();
    return render('post', ['post' => $post]);
});

// UUID
router()->get('/order/{uuid:uuid}', function (string $uuid) { /* ... */ });

// 커스텀 regex
router()->get('/code/{code:regex(\d{2,4})}', function (string $code) { /* ... */ });

// 한글 지원
router()->get('/tag/{name:korean}', function (string $name) { /* ... */ });
```

### 그룹과 중첩

```php
router()->group('/admin', function () {
    router()->get('/dashboard', fn() => render('admin/dashboard'));
    router()->get('/users', fn() => render('admin/users'));
    // → /admin/dashboard, /admin/users
});

// 중첩 그룹
router()->group('/api', function () {
    router()->group('/v1', function () {
        router()->get('/users', fn() => json()->ok($users));
        // → /api/v1/users
    });
});
```

### 미들웨어

```php
// 글로벌 미들웨어
router()->use(guard()->middleware());  // XSS 살균

// 커스텀 미들웨어
router()->use(function (string $method, string $uri): ?bool {
    if (str_starts_with($uri, '/admin')) {
        if (!auth()->check()) {
            json()->fail('Forbidden', code: 403);
            return false;  // 요청 중단
        }
        $user = auth()->user();
        if ($user === null || ($user['role'] ?? '') !== 'admin') {
            json()->fail('Forbidden', code: 403);
            return false;
        }
    }
    return null;  // 계속 진행
});
```

### 리디렉트 라우트

```php
router()->redirect('/old-page', '/new-page');       // 301 기본
router()->redirect('/temp', '/other', 302);          // 302 임시
```

### 템플릿 렌더링

```php
// 핸들러에서
router()->get('/home', fn() => render('home', ['title' => '홈']));

// render() 헬퍼 (router()->render() 위임)
$html = render('posts/show', ['post' => $post]);
```

### 404 핸들러

```php
router()->notFound(fn() => render('404'));

// API용 404는 자동 처리
// /api/ 경로 → {"ok": false, "error": {"message": "Not Found", "code": 404}}
```

---

## 내부 동작

### 매칭 알고리즘

```text
dispatch() 실행 흐름:
1. REQUEST_METHOD, REQUEST_URI 파싱
2. null 바이트 제거 + 트레일링 슬래시 정규화
3. 글로벌 미들웨어 순차 실행 (false 반환 시 중단)
4. 정적 라우트 해시 O(1) 매칭 — staticRoutes[method][path]
5. 동적 라우트 regex 순차 매칭 — dynamicRoutes 배열 순회
6. HEAD → GET 폴백 (정적 + 동적 모두 시도, 출력 버퍼로 body 폐기)
7. 404 핸들러 (또는 기본 404 응답)
```

### 정적 vs 동적 라우트

| 구분 | 저장 | 매칭 속도 | 예시 |
| --- | --- | --- | --- |
| 정적 | `staticRoutes[method][path]` | **O(1)** 해시 조회 | `/home`, `/api/posts` |
| 동적 | `dynamicRoutes[]` 배열 | **O(n)** 순차 매칭 | `/user/{id}`, `/post/{slug:slug}` |

### 핸들러 실행 규칙

```text
핸들러 반환값:
  string → Router가 echo 출력 (웹 HTML)
  void   → 핸들러 내부에서 직접 출력 (json()->ok()는 내부에서 echo + exit)
```

### HEAD 요청 처리

HEAD 요청은 GET 핸들러를 실행하되 `ob_start()`/`ob_end_clean()`으로 body를 폐기한다. 정적 라우트와 동적 라우트 모두 폴백된다.

### 뷰 경로 해석

```text
render('posts/show')
→ config('view.path') . '/posts/show.php'
→ realpath 검증 (뷰 디렉토리 내부 확인)
→ extract($data, EXTR_SKIP) + require + ob_get_clean()
```

---

## 보안 고려사항

### URL 살균

- **null 바이트 제거**: `str_replace("\0", '', $rawUri)`
- **parse_url 실패 방어**: 빈 문자열이면 `/`로 폴백
- **트레일링 슬래시 정규화**: 루트(`/`) 제외하고 제거

### 뷰 경로 보안

- **null 바이트 제거**: `str_replace(["\0", '..'], '', $template)`
- **디렉토리 트래버설 차단**: `realpath` 검증 → 뷰 디렉토리 밖 접근 시 `RuntimeException`
- **`extract(EXTR_SKIP)`**: 기존 변수 덮어쓰기 방지

### 404 API 자동 감지

`/api/`로 시작하는 URL은 자동으로 JSON 404 응답 반환:

```json
{"ok": false, "error": {"message": "Not Found", "code": 404}}
```

---

## 주의사항

1. **라우트 등록 순서**: 정적 라우트가 동적 라우트보다 항상 우선한다. 동적 라우트 간에는 등록 순서대로 매칭 (첫 매칭 승).

2. **그룹 prefix**: `group()` 내에서 등록된 라우트에 prefix가 자동 결합된다. 중첩 그룹도 가능.

3. **미들웨어 실행 순서**: 등록 순서대로 실행. `false` 반환 시 이후 미들웨어와 핸들러 모두 실행되지 않음.

4. **render()와 CATPHP 상수**: 뷰 파일에 `defined('CATPHP') || exit;` 가드가 있으므로, `render()`를 통해서만 뷰가 실행된다.

5. **동적 파라미터명 = input() 키**: 동적 파라미터 `{id}`의 값은 핸들러의 인자로 전달되며, 동시에 `input('id')`로도 접근 가능하다.

6. **dispatch() 1회 호출**: `dispatch()`는 보통 `Public/index.php` 마지막 줄에서 1회만 호출한다.

7. **Swoole 환경**: Swoole에서는 `dispatch()`가 요청마다 호출되며, 슈퍼글로벌이 자동 초기화된다.

---

## 연관 도구

- [Request](Request.md) — HTTP 요청 추상화 (`input()`, 헤더 등)
- [Response](Response.md) — HTTP 응답 빌더 (redirect, download, stream)
- [Guard](Guard.md) — XSS 살균 미들웨어 (`guard()->middleware()`)
- [Cors](Cors.md) — CORS 헤더 (`cors()->handle()`)
- [Swoole](Swoole.md) — 비동기 서버 (Router 자동 통합)
