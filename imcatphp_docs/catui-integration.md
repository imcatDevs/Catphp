# CatUI + CatPHP 통합 가이드

CatUI(프론트엔드 JS/CSS)와 CatPHP(백엔드 PHP)를 함께 사용할 때의 아키텍처, 주의점, 문제점을 정리한다.

| 항목 | CatUI | CatPHP |
| --- | --- | --- |
| 역할 | 프론트엔드 UI 프레임워크 | 백엔드 PHP 프레임워크 |
| 언어 | JavaScript (ES6+) | PHP 8.2+ |
| 전역 객체 | `IMCAT` | `CATPHP` 상수 |
| SPA 라우팅 | `catui-href` + `catui-target` | `data-spa` + `fetch()` |
| XSS 방어 | `IMCAT('#el').html()` 자동 이스케이프 | `e()` / `htmlspecialchars()` |
| 상태 관리 | `IMCAT.state.create()` (Proxy) | PHP 세션 / Redis |
| HTTP 클라이언트 | `IMCAT.api.get/post()` (fetch) | `http()->get/post()` (cURL) |

---

## 현재 통합 아키텍처

```text
브라우저 요청
    │
    ▼
Public/index.php (CatPHP Router)
    │
    ├─ GET /                  → render('index') → head.php + home.php + foot.php (전체 HTML)
    ├─ GET /home              → render('home')  → 프래그먼트 (body만)
    ├─ GET /tool/{name}       → render('tools/{name}') → 프래그먼트
    ├─ GET /demo/{page}       → render('demo/{page}')  → 프래그먼트
    └─ GET/POST /api/*        → json()->ok/fail()      → JSON 응답
    
SPA 흐름 (foot.php):
    1. data-spa 클릭 → fetch(path)로 프래그먼트 HTML 가져옴
    2. content.innerHTML = html  → DOM 교체
    3. <script> 재실행 (createElement로 복제)
    4. IMCAT.init() 호출 → data-imcat 재처리
    5. 사이드바 메뉴 활성화 동기화
```

### 파일 배치

```text
Public/
├── imcatui/                  ← CatUI 빌드 산출물
│   ├── imcat-ui.css          ← <head>에서 로드
│   ├── imcat-ui.min.js       ← </body> 직전 로드
│   ├── modules/              ← IMCAT.use()로 동적 로드
│   └── fonts/
├── index.php                 ← CatPHP 진입점
└── views/
    ├── include/
    │   ├── head.php          ← CSS + 헤더 + 사이드바
    │   └── foot.php          ← JS + SPA 라우팅 + 테마
    ├── index.php             ← head + home + foot (SPA 셸)
    ├── home.php              ← 홈 프래그먼트
    ├── tools/*.php           ← 도구 소개 프래그먼트
    └── demo/*.php            ← 데모 프래그먼트
```

---

## 이중 SPA 라우터 충돌

### 문제

CatUI에는 자체 SPA 라우터(`catui-href` + `catui-target`)가 있고, CatPHP 데모 앱은 별도의 `data-spa` + `fetch()` 기반 SPA를 구현한다. **두 시스템이 동시에 활성화되면 충돌**한다.

### 현재 선택

CatPHP 데모 앱은 CatUI 라우터를 사용하지 않고, `data-spa` 커스텀 SPA를 사용한다.

| 기능 | CatUI Router | CatPHP data-spa |
| --- | --- | --- |
| 트리거 | `catui-href` 속성 | `data-spa` 속성 |
| URL 관리 | History API (pushState) | Hash (`location.hash`) |
| 콘텐츠 타겟 | `catui-target` 속성 | `#spaContent` 고정 |
| 서버 연동 | 정적 HTML 파일 | CatPHP Router (PHP 렌더링) |
| 스크립트 실행 | 자동 | 수동 (createElement 복제) |

### 주의점

- `catui-href` 속성을 프래그먼트 내에서 사용하면 CatUI 라우터가 작동하여 **예기치 않은 페이지 전환**이 발생할 수 있다.
- 프래그먼트 내부에서 페이지 이동이 필요하면 반드시 `data-spa` 속성을 사용한다.

```html
<!-- ✅ 올바름 — CatPHP SPA 라우팅 -->
<a data-spa="/tool/cache" class="badge">Cache</a>

<!-- ❌ 금지 — CatUI 라우터와 충돌 -->
<a catui-href="/tool/cache">Cache</a>
```

---

## XSS 보안 갭

### 문제: innerHTML 직접 주입

SPA 프래그먼트 교체 시 `content.innerHTML = html`을 사용한다. 이는 CatUI의 XSS 자동 방어(`IMCAT('#el').html()`)를 우회한다.

```javascript
// foot.php의 SPA 라우팅 — XSS 이스케이프 없음
const html = await res.text();
content.innerHTML = html;  // ⚠️ CatUI XSS 방어 우회
```

### 현재 완화 조치

- 프래그먼트는 **CatPHP 서버에서 렌더링**한 신뢰할 수 있는 HTML이다.
- 사용자 입력이 프래그먼트에 포함되는 경우 서버 측에서 `e()` (`htmlspecialchars`)로 이스케이프해야 한다.
- 에러 메시지 표시 시 `IMCAT.security?.escapeHTML()`을 사용한다.

### 권장 사항

1. **서버 측 이스케이프 필수**: 프래그먼트 PHP에서 사용자 입력 출력 시 반드시 `e()` 사용.
2. **프래그먼트 내 `rawHtml()` 금지**: CatUI의 `rawHtml()`은 신뢰 소스 전용.
3. **API 응답을 DOM에 직접 삽입 금지**: `IMCAT.api.get()`의 결과를 `innerHTML`로 넣지 않는다.

```php
<!-- ✅ 서버 측 이스케이프 -->
<p><?= e($user['name']) ?></p>

<!-- ❌ 위험 — 이스케이프 없이 출력 -->
<p><?= $user['name'] ?></p>
```

---

## CSRF 토큰과 SPA

### CSRF 토큰 만료 문제

CatPHP의 CSRF 보호(`csrf()->middleware()`)는 세션 기반 토큰을 사용한다. SPA에서 AJAX로 폼을 전송할 때 토큰이 누락되거나 만료될 수 있다.

### 해결 패턴

#### 방법 1: 메타 태그로 토큰 전달

```php
<!-- head.php에 추가 -->
<meta name="csrf-token" content="<?= e(csrf()->token()) ?>">
```

```javascript
// CatUI API 호출 시 자동 첨부
IMCAT.api.interceptors.request = (config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) config.headers['X-CSRF-TOKEN'] = token;
    return config;
};
```

#### 방법 2: SPA 프래그먼트 폼에 hidden input

```php
<form onsubmit="submitForm(event)">
    <?= csrf()->field() ?>
    <input type="text" name="title" class="form-input">
    <button type="submit" class="btn btn--primary">저장</button>
</form>
```

### CSRF 주의점

- SPA에서 페이지를 장시간 열어두면 세션이 만료되어 CSRF 토큰이 무효화된다.
- API 전용 라우트(`/api/*`)에서는 CSRF 대신 JWT 인증(`auth()->middleware()`)을 사용하는 것이 적합하다.

---

## JSON 응답과 exit 문제

### exit 호출의 부작용

CatPHP의 `json()->ok()`/`json()->fail()`은 내부에서 `exit`을 호출한다 (`send()` → `echo` + `exit`). 이는 다음 상황에서 문제가 된다:

| 상황 | 영향 |
| --- | --- |
| **Swoole 서버** | `exit`이 워커 프로세스를 종료시킴 |
| **단위 테스트** | 테스트 실행이 중단됨 |
| **후처리 로직** | `json()->ok()` 이후 코드 실행 불가 |

### JSON exit 현재 완화 조치

- Swoole 도구에서는 `ob_start()` + `ob_get_clean()` 패턴으로 출력을 캡처한다.
- `router()->dispatch()` 가 핸들러 반환값을 처리하므로, 핸들러가 string을 반환하면 `echo` 출력한다.

### JSON exit 권장 사항

- API 라우트에서만 `json()->ok()` 사용 (HTML 라우트에서 사용 금지).
- Swoole 환경에서는 JSON 응답을 직접 `$res->end()`로 전송한다.

---

## data-imcat 재초기화

### 프래그먼트 교체 시 초기화 누락

CatUI의 `data-imcat` 선언적 초기화는 페이지 로드 시 1회 실행된다. SPA 프래그먼트를 `innerHTML`로 교체하면 새 프래그먼트의 `data-imcat` 요소가 자동으로 초기화되지 않는다.

### 현재 해결

```javascript
// foot.php — SPA 프래그먼트 로드 후
if (typeof IMCAT !== 'undefined' && IMCAT.init) IMCAT.init();
```

### 재초기화 주의점

1. **중복 초기화**: `IMCAT.init()`이 이미 초기화된 컴포넌트를 다시 초기화하면 이벤트 리스너가 중복 등록될 수 있다. CatUI는 내부적으로 `_initialized` 플래그로 이를 방지하지만, 커스텀 컴포넌트에서는 직접 확인이 필요하다.
2. **비동기 모듈**: `IMCAT.init()`은 코어 컴포넌트만 재처리한다. `IMCAT.use()`로 로드하는 확장 모듈의 `data-imcat` 속성은 별도로 초기화해야 한다.

```javascript
// 프래그먼트 내 비동기 모듈 사용 시
(async function() {
    const { Tabs } = await IMCAT.use('navigation');
    new Tabs('#myTabs');  // 명시적 초기화 필요
})();
```

---

## 스크립트 재실행 제약

### innerHTML과 script 태그

SPA 프래그먼트를 `innerHTML`로 주입하면 `<script>` 태그가 실행되지 않는다. `foot.php`에서 `createElement('script')` + `replaceWith`로 이를 해결하지만, 제약이 있다.

### 제약 사항

| 제약 | 설명 |
| --- | --- |
| **실행 순서** | 여러 `<script>` 태그의 실행 순서가 보장되지 않을 수 있음 |
| **외부 스크립트** | `src` 속성의 외부 JS는 비동기 로드되므로 로드 완료 전 의존 코드가 실행될 수 있음 |
| **모듈 스크립트** | `type="module"` 스크립트는 이 방식으로 재실행이 어려움 |
| **에러 격리** | 하나의 스크립트 에러가 나머지 스크립트 실행을 중단시키지 않도록 `try-catch` 래핑 필요 (현재 적용됨) |

### 권장 패턴

```html
<!-- ✅ 프래그먼트 내 단일 인라인 스크립트 -->
<div class="demo-section">
    <!-- HTML 콘텐츠 -->
</div>
<script>
(async function() {
    // 즉시 실행 함수로 스코프 격리
    const { Modal } = await IMCAT.use('overlays');
    // ...
})();
</script>

<!-- ❌ 피해야 하는 패턴 — 여러 외부 스크립트 -->
<script src="/js/lib-a.js"></script>
<script src="/js/lib-b.js"></script>  <!-- lib-a 로드 전에 실행될 수 있음 -->
<script>useLibA(); useLibB();</script>
```

---

## 이벤트 리스너 누수

### 리스너·타이머 미정리 문제

SPA 프래그먼트에서 등록한 이벤트 리스너, 타이머(`setInterval`), CatUI 이벤트 버스 구독(`IMCAT.on()`)은 프래그먼트가 교체되어도 자동으로 정리되지 않는다.

### 예시

```javascript
// 프래그먼트 A에서 등록
IMCAT.on('user:login', handleLogin);
const timer = setInterval(pollData, 5000);

// 프래그먼트 B로 전환 → handleLogin, timer가 계속 살아있음!
```

### 리스너 정리 패턴

```javascript
// 프래그먼트 내에서 정리 함수 등록
(function() {
    const unsub = IMCAT.on('user:login', handleLogin);
    const timer = setInterval(pollData, 5000);

    // 프래그먼트 교체 감지 (MutationObserver 또는 커스텀 이벤트)
    const cleanup = () => {
        unsub();                    // CatUI 이벤트 구독 해제
        clearInterval(timer);       // 타이머 정리
    };

    // 부모 DOM 제거 시 자동 정리
    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.removedNodes) {
                if (node.contains && node.contains(document.currentScript?.parentElement)) {
                    cleanup();
                    observer.disconnect();
                    return;
                }
            }
        }
    });
    observer.observe(document.getElementById('spaContent'), { childList: true });
})();
```

---

## CatUI API와 CatPHP API 연동

### CatUI → CatPHP

```javascript
// CatUI의 HTTP 클라이언트로 CatPHP API 호출
const users = await IMCAT.api.get('/api/users');
// → CatPHP: json()->ok(db()->table('users')->all())
// → 응답: { ok: true, data: [...] }
```

### API 연동 주의점

| 항목 | 설명 |
| --- | --- |
| **응답 형식** | CatPHP `json()->ok()`는 `{ok, data}` 형식. CatUI `IMCAT.api`는 기본적으로 응답 body 전체를 반환 |
| **에러 처리** | CatPHP `json()->fail()`은 HTTP 422 + `{ok: false, error: {message}}`. CatUI에서 `try-catch`로 처리 필요 |
| **인증** | CatPHP `auth()->middleware()`는 `Authorization: Bearer <token>` 헤더 필요. CatUI API 인터셉터로 자동 첨부 |
| **CORS** | 같은 오리진이면 문제 없음. 다른 오리진에서 요청 시 CatPHP `cors()->middleware()` 필요 |

### 통합 패턴: CRUD 페이지

```html
<!-- 프래그먼트: tools/user-list.php -->
<div class="demo-section">
    <div class="card card--outlined">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0">사용자 목록</h5>
            <button class="btn btn--primary btn--sm" onclick="addUser()">추가</button>
        </div>
        <div id="userTable"></div>
    </div>
</div>
<script>
(async function() {
    const { DataTable } = await IMCAT.use('data-viz');

    // CatPHP API 호출
    let res;
    try {
        res = await IMCAT.api.get('/api/users');
    } catch (e) {
        IMCAT.toast.error('데이터 로드 실패');
        return;
    }

    // CatPHP 응답 형식: { ok: true, data: [...] }
    if (!res.ok) {
        IMCAT.toast.error(res.error?.message ?? '알 수 없는 오류');
        return;
    }

    new DataTable('#userTable', {
        columns: [
            { key: 'id', title: 'ID' },
            { key: 'name', title: '이름' },
            { key: 'email', title: '이메일' },
        ],
        data: res.data,
        pagination: true,
    });
})();

async function addUser() {
    const name = await IMCAT.prompt('이름을 입력하세요');
    if (!name) return;

    try {
        await IMCAT.api.post('/api/users', { name, email: name + '@example.com' });
        IMCAT.toast.success('추가 완료');
    } catch (e) {
        IMCAT.toast.error('추가 실패');
    }
}
</script>
```

---

## 테마 (다크모드) 연동

### 현재 구현

- CatUI `theme` 모듈로 라이트/다크 전환한다.
- CatUI CSS 변수(`--bg-primary`, `--text-primary` 등)가 자동으로 전환된다.
- CatPHP 뷰에서 CatUI CSS 클래스를 사용하면 자동으로 다크모드 지원된다.

### 테마 주의점

| 항목 | 설명 |
| --- | --- |
| **인라인 스타일** | `style="color: #333"` 같은 하드코딩 색상은 다크모드에서 깨짐 |
| **이미지** | 밝은 배경 전용 로고/아이콘은 다크모드에서 안 보일 수 있음 |
| **커스텀 CSS** | `head.php`의 커스텀 스타일이 CatUI CSS 변수를 사용해야 테마 전환이 정상 동작 |

```css
/* ✅ CSS 변수 사용 — 테마 전환 자동 대응 */
.my-component { color: var(--text-primary); background: var(--bg-secondary); }

/* ❌ 하드코딩 — 다크모드에서 깨짐 */
.my-component { color: #333; background: #f8f9fa; }
```

---

## Swoole 환경 특수 사항

CatPHP Swoole 서버에서 CatUI를 사용할 때 추가 주의사항이 있다.

| 항목 | 설명 |
| --- | --- |
| **정적 파일** | `swoole.static_handler = true` + `swoole.document_root = Public/` 설정 시 CatUI JS/CSS를 Swoole이 직접 서빙 |
| **슈퍼글로벌 격리** | 각 요청마다 `$_GET`, `$_POST` 등이 초기화됨. CatUI의 AJAX 요청도 동일하게 격리 |
| **exit 금지** | `json()->ok()` 내부의 `exit`이 워커를 종료시킴. Swoole 핸들러에서는 `ob_start()` + 캡처 패턴 사용 |
| **세션** | PHP 기본 세션(`session_start()`)은 Swoole에서 작동하지 않음. Redis 세션 또는 JWT 사용 필요 |
| **Hot Reload** | CatUI 모듈 JS/CSS 변경 시 Swoole Hot Reload가 감지하지 않음 (PHP 파일만 감시). 브라우저 하드 리프레시 필요 |

---

## 문제 체크리스트

프로젝트 시작 시 확인할 사항을 정리한다.

### 필수 확인

- [ ] `Public/imcatui/` 디렉토리에 CatUI 빌드 산출물이 있는가?
- [ ] `head.php`에서 `imcat-ui.css`를 로드하는가?
- [ ] `foot.php`에서 `imcat-ui.min.js`를 로드하는가?
- [ ] SPA 프래그먼트 로드 후 `IMCAT.init()`을 호출하는가?
- [ ] 프래그먼트 내에서 `catui-href` 대신 `data-spa`를 사용하는가?

### 보안 확인

- [ ] 프래그먼트 PHP에서 사용자 입력 출력 시 `e()` 이스케이프를 사용하는가?
- [ ] API 라우트에 CSRF 또는 JWT 인증이 적용되었는가?
- [ ] `IMCAT('#el').rawHtml()`에 사용자 입력을 전달하지 않는가?
- [ ] CatUI `IMCAT.api` 호출 시 인증 토큰이 올바르게 첨부되는가?

### 성능 확인

- [ ] CatUI 모듈을 `IMCAT.use()`로 필요할 때만 로드하는가? (번들 사이즈 최적화)
- [ ] 프래그먼트 내 이벤트 리스너와 타이머를 정리하는가? (메모리 누수 방지)
- [ ] 프래그먼트 스크립트가 IIFE로 스코프 격리되어 있는가? (전역 오염 방지)

### 호환성 확인

- [ ] 인라인 스타일 대신 CatUI CSS 변수를 사용하는가? (다크모드 호환)
- [ ] CatPHP `json()` 응답 형식(`{ok, data, error}`)에 맞춰 프론트 에러 처리를 하는가?
- [ ] Swoole 환경에서 `exit` 호출이 없는가?

---

## 요약: CatUI와 CatPHP의 역할 분담

```text
┌─────────────────────────────────────────────────────────┐
│  브라우저 (CatUI)                                        │
│                                                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐       │
│  │ CSS 클래스 │  │ JS 모듈   │  │ SPA (data-spa)    │       │
│  │ .btn .card │  │ Modal     │  │ fetch() → HTML    │       │
│  │ .table     │  │ Toast     │  │ innerHTML 교체    │       │
│  │ .alert     │  │ DataTable │  │ IMCAT.init()      │       │
│  └──────────┘  └──────────┘  └──────────────────┘       │
│       ▲              ▲              │                     │
│       │              │              ▼                     │
│  CatUI CSS 변수   IMCAT.use()   IMCAT.api.get/post()    │
│  (테마 자동 전환)  (동적 로드)    (HTTP 요청)             │
└──────────────────────────────────│────────────────────────┘
                                   │
                          HTTP (fetch / XHR)
                                   │
┌──────────────────────────────────│────────────────────────┐
│  서버 (CatPHP)                   ▼                        │
│                                                           │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐        │
│  │ Router    │  │ Guard     │  │ API 라우트         │        │
│  │ 라우트 매칭 │  │ XSS 방어  │  │ json()->ok/fail() │        │
│  │ 미들웨어   │  │ CSRF 검증 │  │ auth()->verify()  │        │
│  └──────────┘  └──────────┘  └──────────────────┘        │
│       │              │              │                      │
│       ▼              ▼              ▼                      │
│  render()      e() 이스케이프   DB 쿼리 + 비즈니스 로직     │
│  PHP 프래그먼트   서버 측 보안    데이터 처리                 │
└───────────────────────────────────────────────────────────┘
```

**핵심 원칙**: CatUI는 **표현(UI/UX)**, CatPHP는 **처리(데이터/보안)**를 담당한다. 보안 검증은 반드시 서버 측(CatPHP)에서 수행하고, CatUI는 사용자 경험 향상에 집중한다.
