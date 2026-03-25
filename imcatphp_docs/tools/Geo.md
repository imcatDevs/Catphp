# Geo — 다국어/지역화

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Geo` |
| 파일 | `catphp/Geo.php` (241줄) |
| Shortcut | `geo()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Cookie` (로케일 쿠키), `Cat\Ip` (IP 기반 감지) |
| 선택 확장 | `ext-intl` (통화/날짜 포맷) |

---

## 설정

```php
// config/app.php
'geo' => [
    'default'   => 'ko',            // 기본 로케일
    'supported' => ['ko', 'en'],    // 지원 로케일 목록
    'path'      => __DIR__ . '/../lang',  // 번역 파일 디렉토리
],
```

---

## 메서드 레퍼런스

### 로케일

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `getLocale` | `getLocale(): string` | `string` | 현재 로케일 |
| `locale` | `locale(string $locale): self` | `self` | 로케일 설정 (지원 목록 내) |
| `detect` | `detect(): string` | `string` | 자동 감지 (쿠키 → Accept-Language → IP) |

### 번역

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `t` | `t(string $key, array $replace = []): string` | `string` | 번역 (플레이스홀더 치환) |

### URL

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `url` | `url(string $path, ?string $locale = null): string` | `string` | 다국어 URL 생성 |
| `switch` | `switch(string $locale): string` | `string` | 언어 전환 URL |

### 포맷

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `currency` | `currency(float\|int $amount, ?string $locale = null): string` | `string` | 통화 포맷 |
| `date` | `date(int $timestamp, ?string $locale = null): string` | `string` | 날짜 포맷 |

### SEO / 미들웨어

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `hreflang` | `hreflang(): string` | `string` | hreflang HTML 태그 |
| `middleware` | `middleware(): callable` | `callable` | 자동 감지 + 쿠키 저장 미들웨어 |

---

## 번역 파일 형식

```php
// lang/ko.php
return [
    'welcome'  => '환영합니다',
    'greeting' => '안녕하세요, :name님!',
    'auth' => [
        'login'  => '로그인',
        'logout' => '로그아웃',
    ],
];
```

```php
// lang/en.php
return [
    'welcome'  => 'Welcome',
    'greeting' => 'Hello, :name!',
    'auth' => [
        'login'  => 'Login',
        'logout' => 'Logout',
    ],
];
```

---

## 사용 예제

### 번역 사용

```php
geo()->locale('ko');
echo geo()->t('welcome');            // '환영합니다'
echo geo()->t('greeting', ['name' => '홍길동']);  // '안녕하세요, 홍길동님!'
echo geo()->t('auth.login');         // '로그인' (dot notation)
echo geo()->t('missing_key');        // 'missing_key' (키가 없으면 키 자체 반환)
```

### 자동 감지

```php
// 미들웨어로 자동 감지 + 쿠키 저장
router()->use(geo()->middleware());

// 수동 감지
$locale = geo()->detect();
// 1. 쿠키(_locale) → 2. Accept-Language → 3. IP 국가 → 4. 기본값
```

### 다국어 URL

```php
geo()->url('/posts', 'ko');    // '/ko/posts'
geo()->url('/posts', 'en');    // '/en/posts'
geo()->switch('en');            // 현재 URL의 로케일만 변경
```

### 통화 포맷

```php
geo()->currency(15000, 'ko');   // '₩15,000'
geo()->currency(99.99, 'en');   // '$99.99'
// ext-intl 있으면 NumberFormatter 사용
```

### 날짜 포맷

```php
geo()->date(time(), 'ko');   // '2025년 3월 22일'
geo()->date(time(), 'en');   // 'March 22, 2025'
// ext-intl 있으면 IntlDateFormatter 사용
```

### hreflang (SEO)

```php
// <head> 내에서
echo geo()->hreflang();
```

출력:

```html
<link rel="alternate" hreflang="ko" href="https://example.com/ko/posts">
<link rel="alternate" hreflang="en" href="https://example.com/en/posts">
<link rel="alternate" hreflang="x-default" href="https://example.com/ko/posts">
```

### 뷰에서 사용

```php
<h1><?= geo()->t('welcome') ?></h1>
<p><?= geo()->t('greeting', ['name' => user()->get('name')]) ?></p>
<a href="<?= geo()->switch('en') ?>">English</a>
<a href="<?= geo()->switch('ko') ?>">한국어</a>
```

---

## 내부 동작

### detect() 우선순위

```text
detect()
├─ 1. 쿠키 '_locale' (Cookie 도구)
├─ 2. Accept-Language 헤더 (첫 2글자 매칭)
├─ 3. IP 국가 코드 (Ip 도구 → localeMap)
│   └─ KR→ko, US→en, GB→en, JP→ja, CN→zh
└─ 4. 기본 로케일 (config)
```

### 번역 로드 (지연)

```text
t('auth.login')
├─ loadTranslations('ko') — 최초 1회만
│   ├─ lang/ko.php require
│   └─ flatten(): 중첩 배열 → dot notation
│       'auth.login' → '로그인'
├─ translations['ko']['auth.login'] ?? 'auth.login'
└─ :placeholder 치환
```

### switch() CRLF 방어

```php
$uri = str_replace(["\r", "\n", "\0"], '', $_SERVER['REQUEST_URI'] ?? '/');
```

### hreflang() 호스트 살균

```php
$host = preg_replace('/[^\w.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$scheme = in_array($scheme, ['http', 'https'], true) ? $scheme : 'https';
```

---

## 보안 고려사항

- **로케일 화이트리스트**: `locale()`, `detect()` 모두 `supported` 목록 내에서만 허용
- **CRLF 방어**: `switch()`, `hreflang()`에서 REQUEST_URI의 `\r\n\0` 제거
- **호스트 살균**: `hreflang()`에서 HTTP_HOST의 특수문자 제거
- **스키마 검증**: `hreflang()`에서 http/https만 허용

---

## 주의사항

1. **ext-intl 선택적**: `currency()`, `date()`는 intl 확장 없이도 동작 (폴백 포맷). 정확한 포맷이 필요하면 intl 설치 권장.

2. **번역 파일 형식**: `lang/{locale}.php`에서 배열 반환. 중첩 배열은 자동으로 dot notation으로 평탄화.

3. **IP 국가 매핑 제한**: 내장 `localeMap`에 5개국만 정의. 추가 국가는 코드 수정 또는 커스텀 감지 로직 필요.

4. **쿠키 우선**: `detect()` 시 쿠키가 최우선. 사용자가 언어를 변경하면 쿠키에 저장되어 이후 요청에서 유지.

5. **URL 규칙**: `url()`, `switch()`는 `/{locale}/path` 형식. 쿼리스트링 기반(`?lang=ko`)이 필요하면 커스텀 구현.

---

## 연관 도구

- [Cookie](Cookie.md) — 로케일 쿠키 저장
- [Ip](Ip.md) — IP 기반 국가 감지
- [Meta](Meta.md) — SEO 메타 태그
- [Router](Router.md) — 미들웨어 등록
