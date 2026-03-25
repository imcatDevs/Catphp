# Feed — RSS/Atom 피드

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Feed` |
| 파일 | `catphp/Feed.php` (173줄) |
| Shortcut | `feed()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Guard` (살균), `Cat\Cache` (피드 캐시) |

---

## 설정

```php
// config/app.php
'feed' => [
    'limit'     => 20,     // 피드 항목 수
    'cache_ttl' => 3600,   // 피드 캐시 TTL (초)
],
```

---

## 메서드 레퍼런스

### 빌더 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `title` | `title(string $title): self` | `self` | 피드 제목 |
| `description` | `description(string $description): self` | `self` | 피드 설명 |
| `link` | `link(string $url): self` | `self` | 피드 링크 |
| `items` | `items(array $items): self` | `self` | 피드 아이템 수동 설정 |
| `fromQuery` | `fromQuery(array $rows, string $titleCol = 'title', string $contentCol = 'content', string $dateCol = 'created_at', string $slugCol = 'slug'): self` | `self` | DB 결과에서 아이템 생성 |

### 출력

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `rss` | `rss(): never` | `never` | RSS 2.0 출력 + exit |
| `atom` | `atom(): never` | `never` | Atom 출력 + exit |
| `render` | `render(string $format = 'rss'): string` | `string` | XML 문자열 반환 |

---

## 사용 예제

### RSS 피드

```php
router()->get('/feed', function () {
    $posts = db()->table('posts')
        ->where('status', 'published')
        ->orderByDesc('created_at')
        ->limit(20)
        ->all();

    feed()
        ->title('My Blog')
        ->description('최신 게시글')
        ->link('https://example.com')
        ->fromQuery($posts, 'title', 'content', 'created_at', 'slug')
        ->rss();
});
```

### Atom 피드

```php
router()->get('/atom', function () {
    $posts = db()->table('posts')->orderByDesc('id')->limit(20)->all();

    feed()
        ->title('My Blog')
        ->link('https://example.com')
        ->fromQuery($posts)
        ->atom();
});
```

### 수동 아이템

```php
$f = feed()
    ->title('공지사항')
    ->link('https://example.com/notices')
    ->items([
        ['title' => '서버 점검', 'description' => '...', 'link' => '/notices/1', 'pubDate' => '2025-03-22'],
        ['title' => '업데이트', 'description' => '...', 'link' => '/notices/2', 'pubDate' => '2025-03-20'],
    ]);

$xml = $f->render('rss');   // 문자열로 반환
$xml = $f->render('atom');  // Atom 형식
```

---

## 내부 동작

### fromQuery() 흐름

```text
fromQuery($rows, $titleCol, $contentCol, $dateCol, $slugCol)
├─ array_slice($rows, 0, $limit) — 개수 제한
├─ 각 행:
│   ├─ title: guard()->clean($row[$titleCol])
│   ├─ description: guard()->clean(mb_substr(strip_tags($content), 0, 300))
│   ├─ link: guard()->clean($row[$slugCol])
│   └─ pubDate: $row[$dateCol] ?? date('Y-m-d H:i:s')
└─ 이뮤터블 반환
```

### RSS 2.0 구조

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
  <title>...</title>
  <description>...</description>
  <link>...</link>
  <item>
    <title>...</title>
    <description>...</description>
    <link>...</link>
    <guid isPermaLink="true">...</guid>
    <pubDate>Thu, 22 Mar 2025 00:00:00 +0900</pubDate>
  </item>
</channel>
</rss>
```

### XML 이스케이프

모든 값에 `htmlspecialchars(ENT_XML1, 'UTF-8')` 적용.

### 캐시

`rss()` 호출 시 `Cat\Cache` 도구가 로드된 경우에만 `cache()->set('feed:rss', ...)` 저장. `atom()`은 캐시하지 않음.

---

## 주의사항

1. **`rss()`, `atom()`은 `never`**: 내부에서 `exit` 호출. 이후 코드 실행 불가.
2. **`render()`는 문자열**: exit 없이 XML 문자열만 반환. 커스텀 처리가 필요할 때 사용.
3. **description 300자 제한**: `fromQuery()`에서 본문을 300자로 자르고 HTML 태그를 제거.
4. **pubDate 형식**: RSS는 `date('r')` (RFC 2822), Atom은 `date('c')` (ISO 8601).
5. **캐시 범위**: `rss()`만 캐시 저장. `atom()`은 캐시 없이 매번 빌드.
6. **pubDate 기본값**: `fromQuery()`에서 `$dateCol` 값이 없으면 현재 시간(`date('Y-m-d H:i:s')`) 사용.

---

## 연관 도구

- [DB](DB.md) — 피드 데이터 소스
- [Cache](Cache.md) — 피드 캐시
- [Guard](Guard.md) — 콘텐츠 살균
- [Slug](Slug.md) — 항목 링크 생성
- [Meta](Meta.md) — SEO 메타 태그
