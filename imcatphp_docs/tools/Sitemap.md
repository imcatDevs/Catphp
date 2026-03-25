# Sitemap — XML 사이트맵 생성

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Sitemap` |
| 파일 | `catphp/Sitemap.php` (279줄) |
| Shortcut | `sitemap()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Cache` (캐시 저장, 선택적) |

---

## 설정

```php
// config/app.php
'sitemap' => [
    'base_url'  => 'https://example.com',  // 사이트 기본 URL
    'cache_ttl' => 3600,                    // 캐시 TTL (초)
],
```

---

## 메서드 레퍼런스

### URL 추가 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `url` | `url(string $loc, ?string $lastmod = null, ?string $changefreq = null, ?float $priority = null): self` | `self` | 단일 URL 추가 |
| `urls` | `urls(array $urls): self` | `self` | 복수 URL 일괄 추가 |
| `fromQuery` | `fromQuery(array $rows, string $urlPattern, string $dateCol = 'updated_at', string $changefreq = 'weekly', float $priority = 0.7): self` | `self` | DB 결과에서 URL 자동 생성 |

### 사이트맵 인덱스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `index` | `index(array $sitemaps): self` | `self` | 사이트맵 인덱스 생성 |

### 출력

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `render` | `render(): string` | `string` | XML 문자열 반환 |
| `output` | `output(): never` | `never` | Content-Type + echo + exit |
| `save` | `save(string $path): bool` | `bool` | 파일로 저장 |
| `count` | `count(): int` | `int` | URL 수 |

---

## 사용 예제

### 기본 사이트맵

```php
sitemap()
    ->url('/', '2025-01-01', 'daily', 1.0)
    ->url('/about', '2025-01-01', 'monthly', 0.8)
    ->url('/contact', '2025-01-01', 'yearly', 0.5)
    ->output();
```

### DB에서 자동 생성

```php
$posts = db()->table('posts')->select(['slug', 'updated_at'])->all();

sitemap()
    ->url('/', null, 'daily', 1.0)
    ->fromQuery($posts, '/post/{slug}', 'updated_at', 'weekly', 0.7)
    ->output();
```

### 파일로 저장

```php
$posts = db()->table('posts')->all();

sitemap()
    ->fromQuery($posts, '/post/{slug}')
    ->save('Public/sitemap.xml');
```

### 사이트맵 인덱스 생성

```php
sitemap()->index([
    '/sitemap-posts.xml',
    '/sitemap-pages.xml',
    ['loc' => '/sitemap-products.xml', 'lastmod' => '2025-01-15'],
])->output();
```

### CLI에서 생성

```bash
php cli.php sitemap:generate --table=posts --url=/post/{slug}
```

---

## 내부 동작

### URL 패턴 치환

```text
fromQuery($rows, '/post/{slug}', 'updated_at')
├─ 각 행에서 {slug} → $row['slug'] 치환
├─ $row['updated_at'] → Y-m-d 형식 변환
└─ base_url + loc → 절대 URL
```

### changefreq 검증

허용값: `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never`

잘못된 값 → `InvalidArgumentException`.

### priority 클램핑

```php
$entry['priority'] = max(0.0, min(1.0, $priority));
```

0.0~1.0 범위 밖이면 자동 보정.

### 50,000 URL 제한

사이트맵 프로토콜 0.9 스펙에 따라 파일당 최대 50,000개 URL. 초과 시 `RuntimeException` — `index()`로 분할 필요.

### 캐시

`output()` 호출 시 `Cat\Cache` 존재하면 자동 캐시 저장 (`sitemap:urlset` 또는 `sitemap:index` 키).

---

## 보안 고려사항

- **XML 이스케이프**: 모든 값에 `htmlspecialchars(ENT_XML1, 'UTF-8')` 적용
- **절대 URL 변환**: 상대 경로는 `base_url`에 결합. 이미 `http://`/`https://`로 시작하면 그대로 사용.

---

## 주의사항

1. **base_url 필수**: `sitemap.base_url` 미설정 시 상대 경로가 그대로 출력. 검색 엔진이 올바르게 인식하지 못할 수 있음.
2. **output()은 never**: 내부에서 `exit` 호출.
3. **save() 디렉토리**: 존재하지 않으면 자동 생성 (`mkdir` recursive).
4. **인덱스 모드**: `index()` 호출 후에는 `url()`로 추가한 URL이 무시됨.

---

## 연관 도구

- [Cache](Cache.md) — 사이트맵 캐시
- [DB](DB.md) — `fromQuery()` 데이터 소스
- [Meta](Meta.md) — SEO 메타 태그
