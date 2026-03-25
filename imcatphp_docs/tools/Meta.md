# Meta — SEO 메타 태그

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Meta` |
| 파일 | `catphp/Meta.php` (140줄) |
| Shortcut | `meta()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | 없음 |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

### 빌더

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `title` | `title(string $title): self` | `self` | 페이지 타이틀 |
| `description` | `description(string $description): self` | `self` | 메타 디스크립션 |
| `canonical` | `canonical(string $url): self` | `self` | canonical URL |
| `og` | `og(string $property, string $content): self` | `self` | Open Graph 태그 |
| `twitter` | `twitter(string $name, string $content): self` | `self` | Twitter Card 태그 |
| `jsonLd` | `jsonLd(array $data): self` | `self` | JSON-LD 구조화 데이터 |
| `reset` | `reset(): self` | `self` | 상태 초기화 |

### 출력

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `render` | `render(): string` | `string` | 메타 태그 HTML |
| `sitemap` | `sitemap(array $urls): string` | `string` | sitemap.xml 생성 |

---

## 사용 예제

### 기본 메타 태그

```php
meta()
    ->title('CatPHP 프레임워크')
    ->description('PHP 8.2+ 전용 경량 프레임워크')
    ->canonical('https://example.com/');

echo meta()->render();
```

출력:

```html
<title>CatPHP 프레임워크</title>
<meta property="og:title" content="CatPHP 프레임워크">
<meta name="description" content="PHP 8.2+ 전용 경량 프레임워크">
<meta property="og:description" content="PHP 8.2+ 전용 경량 프레임워크">
<link rel="canonical" href="https://example.com/">
```

### Open Graph

```php
meta()
    ->title('게시글 제목')
    ->description('게시글 요약')
    ->og('type', 'article')
    ->og('image', 'https://example.com/image.jpg')
    ->og('url', 'https://example.com/posts/1');
```

### Twitter Card

```php
meta()
    ->twitter('card', 'summary_large_image')
    ->twitter('site', '@catphp')
    ->twitter('image', 'https://example.com/card.jpg');
```

### JSON-LD (구조화 데이터)

```php
meta()->jsonLd([
    '@context' => 'https://schema.org',
    '@type'    => 'Article',
    'headline' => '게시글 제목',
    'author'   => ['@type' => 'Person', 'name' => '홍길동'],
    'datePublished' => '2025-01-01',
]);
```

### 페이지별 메타 (reset 활용)

```php
// 라우트 핸들러에서
meta()->reset()
    ->title($post['title'] . ' - MyBlog')
    ->description(mb_substr($post['body'], 0, 160))
    ->og('type', 'article');
```

### 간이 sitemap.xml

```php
router()->get('/sitemap.xml', function () {
    $urls = [
        ['loc' => 'https://example.com/', 'priority' => '1.0'],
        ['loc' => 'https://example.com/about', 'lastmod' => '2025-01-01'],
    ];
    header('Content-Type: application/xml');
    echo meta()->sitemap($urls);
});
```

---

## 내부 동작

### render() 출력 순서

```text
render()
├─ 1. <title> + og:title (title 설정 시)
├─ 2. <meta description> + og:description (description 설정 시)
├─ 3. <link canonical> (canonical 설정 시)
├─ 4. Open Graph 태그들 (og() 호출순)
├─ 5. Twitter Card 태그들 (twitter() 호출순)
└─ 6. <script type="application/ld+json"> (jsonLd 설정 시)
```

### 자동 OG 태그

`title()`과 `description()` 설정 시 `og:title`, `og:description`이 **자동 생성**된다. `og()`로 별도 설정하면 추가 OG 태그가 출력.

### HTML 이스케이프

모든 출력값에 `htmlspecialchars(ENT_QUOTES, 'UTF-8')` 적용:

- title, description, canonical URL
- OG 태그 content
- Twitter 태그 content
- sitemap URL (`ENT_XML1`)

### JSON-LD 보안

```php
json_encode($data, JSON_HEX_TAG)
```

`JSON_HEX_TAG` — `<`, `>`를 `\u003C`, `\u003E`로 인코딩하여 `</script>` 인젝션 방지.

---

## 주의사항

1. **싱글턴 상태 유지**: `meta()`는 싱글턴이므로 이전 페이지의 메타가 남아있을 수 있다. 페이지별로 `reset()` 호출 권장.

2. **`render()` 위치**: `<head>` 태그 내에서 호출해야 한다.

3. **sitemap() vs Sitemap 도구**: `meta()->sitemap()`은 간단한 sitemap용. 대규모 사이트는 `Sitemap` 도구 사용 권장.

4. **og:title 중복**: `title()` 호출 시 `og:title`이 자동 생성되므로, `og('title', ...)`을 별도 호출하면 중복될 수 있다.

---

## 연관 도구

- [Sitemap](Sitemap.md) — 대규모 XML 사이트맵 생성
- [Geo](Geo.md) — `hreflang()` 태그 (다국어 SEO)
- [Slug](Slug.md) — SEO 친화적 URL 생성
