# 웹/CMS — User · Perm · Flash · Meta · Geo · Search

CatPHP의 웹 애플리케이션 계층. 유저 관리, 권한, 플래시 메시지, SEO, 다국어, 검색을 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| User | `user()` | `Cat\User` | 287 |
| Perm | `perm()` | `Cat\Perm` | 95 |
| Flash | `flash()` | `Cat\Flash` | 66 |
| Meta | `meta()` | `Cat\Meta` | 140 |
| Geo | `geo()` | `Cat\Geo` | 241 |
| Search | `search()` | `Cat\Search` | 234 |

---

## 목차

1. [User — 유저 관리](#1-user--유저-관리)
2. [Perm — 역할/권한 (RBAC)](#2-perm--역할권한-rbac)
3. [Flash — 플래시 메시지](#3-flash--플래시-메시지)
4. [Meta — SEO 메타 태그](#4-meta--seo-메타-태그)
5. [Geo — 다국어/지역화](#5-geo--다국어지역화)
6. [Search — 전문 검색](#6-search--전문-검색)

---

## 1. User — 유저 관리

DB 유저 CRUD + Auth 세션 통합. **모든 조회 결과에 XSS 살균 자동 적용**.

### User 설정

```php
'user' => [
    'table'       => 'users',
    'primary_key' => 'id',
    'hidden'      => ['password'],  // 응답에서 제외할 필드
],
```

### User 조회 (자동 XSS 살균)

```php
// 현재 로그인 유저
$me = user()->current();

// 특정 필드
$name = user()->get('name');

// ID로 조회
$user = user()->find(1);

// 필드로 조회
$user = user()->findBy('email', 'alice@example.com');

// 목록
$users = user()->list(limit: 20, offset: 0);

// 검색 (LIKE wildcard 이스케이프 적용)
$results = user()->search('name', '홍길동');

// 수 / 존재 확인
user()->count();
user()->exists('email', 'alice@example.com');
```

### User 원본 조회 (살균 없음)

```php
// 비밀번호 검증 등 내부 로직용
$raw = user()->raw(1);
$raw = user()->rawBy('email', 'alice@example.com');
```

### User 쓰기

```php
// 생성 (비밀번호 자동 해싱)
$id = user()->create([
    'name'     => '홍길동',
    'email'    => 'hong@example.com',
    'password' => 'mypassword',  // 자동 Argon2id 해싱
]);

// 수정
user()->update(1, ['name' => '김철수']);

// 삭제
user()->delete(1);
```

### User 인증 통합

```php
// 로그인 시도 (타이밍 공격 방어 내장)
if (user()->attempt('hong@example.com', $password)) {
    redirect('/dashboard');
} else {
    flash()->error('이메일 또는 비밀번호가 올바르지 않습니다');
}

// 세션 새로고침 (DB에서 최신 정보 로드)
user()->refresh();
```

### User 보안

| 항목 | 구현 |
| --- | --- |
| XSS | 모든 조회에 `guard()->cleanArray()` 자동 적용 |
| 비밀번호 | 자동 해싱 + hidden 필드 제거 |
| 타이밍 공격 | `attempt()`에서 유저 미존재 시에도 동일 시간 소비 |
| LIKE 이스케이프 | `search()`에서 `%`, `_`, `\` 이스케이프 |
| 입력 살균 | 쓰기 시 `guard()->cleanArray()` (비밀번호 제외) |

---

## 2. Perm — 역할/권한 (RBAC)

역할 기반 접근 제어. Auth 세션과 연동.

### Perm 설정

```php
'perm' => [
    'roles' => ['admin', 'editor', 'user'],
],
```

### Perm 권한 정의

```php
// 역할별 권한 부여
perm()->role('editor', ['posts.create', 'posts.edit', 'posts.delete']);
perm()->role('user', ['posts.create', 'comments.create']);
```

### Perm 권한 확인

```php
// 현재 로그인 사용자의 권한 확인
if (perm()->can('posts.edit')) {
    // 편집 가능
}

if (perm()->cannot('users.delete')) {
    // 삭제 불가
}

// admin은 모든 권한 자동 허용
```

### Perm 역할 관리

```php
// 역할 할당 (세션 갱신)
perm()->assign('editor');

// 등록된 역할 목록
$roles = perm()->roles();  // ['admin', 'editor', 'user']
```

### Perm 미들웨어

```php
// 특정 역할만 접근 허용
router()->use(perm()->middleware('admin', 'editor'));
// 미인증 → 401, 권한 없음 → 403
```

---

## 3. Flash — 플래시 메시지

세션 기반 일회성 메시지. 리디렉트 후 표시용.

### Flash 사용법

```php
// 타입별 편의 메서드
flash()->success('저장 완료');
flash()->error('오류가 발생했습니다');
flash()->warning('주의: 데이터가 변경됩니다');
flash()->info('새로운 기능이 추가되었습니다');

// 커스텀 타입
flash()->set('custom', '커스텀 메시지');

// 뷰에서 읽기 (읽은 후 자동 삭제)
$messages = flash()->get();
// [['type' => 'success', 'message' => '저장 완료']]

// 존재 확인
if (flash()->has()) {
    // 메시지 있음
}
```

### Flash 보안

- 메시지에 `guard()->clean()` 자동 적용 (XSS 차단)
- 타입명에 영숫자/밑줄만 허용 (정규식 필터)

---

## 4. Meta — SEO 메타 태그

title, description, Open Graph, Twitter Card, JSON-LD를 체이닝으로 설정.

### Meta 사용법

```php
meta()->title('게시글 제목 — MySite')
    ->description('게시글 설명...')
    ->canonical('https://example.com/posts/1')
    ->og('image', 'https://example.com/img/post.jpg')
    ->og('type', 'article')
    ->twitter('card', 'summary_large_image')
    ->twitter('site', '@mysite')
    ->jsonLd([
        '@context' => 'https://schema.org',
        '@type'    => 'Article',
        'name'     => '게시글 제목',
        'author'   => ['@type' => 'Person', 'name' => '홍길동'],
    ]);

// HTML <head> 안에서
echo meta()->render();
```

출력:

```html
<title>게시글 제목 — MySite</title>
<meta property="og:title" content="게시글 제목 — MySite">
<meta name="description" content="게시글 설명...">
<meta property="og:description" content="게시글 설명...">
<link rel="canonical" href="https://example.com/posts/1">
<meta property="og:image" content="https://example.com/img/post.jpg">
<meta property="og:type" content="article">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@mysite">
<script type="application/ld+json">{"@context":"https://schema.org",...}</script>
```

### Meta 추가 메서드

```php
// 상태 초기화 (SPA에서 새 페이지 전)
meta()->reset();

// 간단 sitemap.xml 생성
$xml = meta()->sitemap([
    ['loc' => 'https://example.com/', 'lastmod' => '2024-01-15', 'priority' => '1.0'],
    ['loc' => 'https://example.com/about', 'priority' => '0.8'],
]);
```

### Meta 보안

- `htmlspecialchars()` — title/description/og/twitter 값 이스케이프
- `JSON_HEX_TAG` — JSON-LD에서 `</script>` XSS 차단

---

## 5. Geo — 다국어/지역화

번역, 언어 감지, 통화/날짜 포맷, hreflang SEO 태그.

### Geo 설정

```php
'geo' => [
    'default'   => 'ko',
    'supported' => ['ko', 'en', 'ja'],
    'path'      => dirname(__DIR__) . '/lang',
],
```

### Geo 번역

번역 파일 (`lang/ko.php`):

```php
return [
    'welcome' => '환영합니다, :name님',
    'auth' => [
        'login'  => '로그인',
        'logout' => '로그아웃',
    ],
];
```

사용법:

```php
geo()->t('welcome', ['name' => '홍길동']);
// '환영합니다, 홍길동님'

geo()->t('auth.login');
// '로그인' (dot notation 지원)
```

### Geo 언어 감지

```php
// 자동 감지 (쿠키 → Accept-Language → IP 순)
$locale = geo()->detect();

// 수동 설정
geo()->locale('en');
$current = geo()->getLocale();  // 'en'
```

### Geo 로케일 포맷

```php
// 통화
geo()->currency(15000);          // '₩15,000'
geo()->currency(29.99, 'en');    // '$29.99'

// 날짜
geo()->date(time());             // '2024년 1월 15일'
geo()->date(time(), 'en');       // 'January 15, 2024'
```

### Geo URL/SEO

```php
// 다국어 URL 생성
geo()->url('/about');           // '/ko/about'
geo()->url('/about', 'en');    // '/en/about'

// 언어 전환 URL
geo()->switch('en');           // '/en/current-path'

// hreflang 태그 (SEO)
echo geo()->hreflang();
// <link rel="alternate" hreflang="ko" href="...">
// <link rel="alternate" hreflang="en" href="...">
// <link rel="alternate" hreflang="x-default" href="...">
```

### Geo 미들웨어

```php
router()->use(geo()->middleware());
// 자동 언어 감지 + 쿠키에 로케일 저장 (365일)
```

---

## 6. Search — 전문 검색

MySQL FULLTEXT, PostgreSQL tsvector, LIKE 폴백을 지원하는 검색 도구.

### Search 설정

```php
'search' => [
    'driver'    => 'fulltext',  // fulltext | like
    'cache_ttl' => 300,         // 결과 캐시 (초)
],
```

### Search 사용법

```php
// 기본 검색
$results = search()
    ->query('PHP 프레임워크')
    ->in('posts', ['title', 'content'])
    ->limit(20)
    ->offset(0)
    ->results();

// 검색 결과 수
$count = search()
    ->query('PHP')
    ->in('posts', ['title', 'content'])
    ->count();

// 검색어 하이라이트
$highlighted = search()
    ->query('PHP')
    ->highlight($post['title']);
// 'Introduction to <mark>PHP</mark> Framework'

// 커스텀 하이라이트 태그
$highlighted = search()->query('PHP')->highlight($text, 'strong');
```

### Search 드라이버별 동작

| 드라이버 | MySQL | PostgreSQL | SQLite |
| --- | --- | --- | --- |
| `fulltext` | `MATCH...AGAINST` (BOOLEAN MODE) | `to_tsvector...plainto_tsquery` | LIKE 폴백 |
| `like` | `LIKE %query%` | `LIKE %query%` | `LIKE %query%` |

### Search 메서드 요약

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `query(string)` | `self` | 검색어 (Guard 살균 적용) |
| `in(table, columns)` | `self` | 대상 테이블/컬럼 |
| `limit(int)` | `self` | 결과 제한 |
| `offset(int)` | `self` | 오프셋 |
| `results()` | `array` | 검색 실행 |
| `count()` | `int` | 결과 수 (SQL COUNT) |
| `highlight(text, tag)` | `string` | 검색어 하이라이트 |

### Search 보안

- 검색어에 `guard()->clean()` 자동 적용
- 테이블/컬럼명 `validateIdentifier()` 검증
- LIKE 폴백: `%`, `_`, `\` 이스케이프
- 캐시: `Cat\Cache` 존재 시 자동 캐싱

---

## 도구 간 연동

```text
User → DB (CRUD)
User → Auth (세션 로그인/로그아웃)
User → Guard (XSS 살균, 입력 살균)
Perm → Auth (현재 유저 역할 확인)
Flash → Session (플래시 저장)
Flash → Guard (메시지 살균)
Meta → (독립) HTML 출력
Geo → Cookie (로케일 저장)
Geo → Ip (IP 기반 언어 감지)
Search → DB (raw SQL)
Search → Guard (검색어 살균)
Search → Cache (결과 캐싱)
```
