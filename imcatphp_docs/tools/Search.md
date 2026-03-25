# Search — 전문 검색

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Search` |
| 파일 | `catphp/Search.php` (234줄) |
| Shortcut | `search()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\DB`, `Cat\Guard` (검색어 살균), `Cat\Cache` (결과 캐시) |

---

## 설정

```php
// config/app.php
'search' => [
    'driver'    => 'fulltext',   // 'fulltext' | 'like'
    'cache_ttl' => 300,          // 검색 캐시 TTL (초, 기본 5분)
],
```

---

## 메서드 레퍼런스

### 빌더 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `query` | `query(string $query): self` | `self` | 검색어 (Guard 살균 자동 적용) |
| `in` | `in(string $table, array $columns): self` | `self` | 검색 대상 테이블/컬럼 |
| `limit` | `limit(int $limit): self` | `self` | 결과 제한 |
| `offset` | `offset(int $offset): self` | `self` | 결과 오프셋 |

### 실행

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `results` | `results(): array` | `array` | 검색 결과 (캐시 포함) |
| `count` | `count(): int` | `int` | 검색 결과 수 |

### 유틸

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `highlight` | `highlight(string $text, string $tag = 'mark'): string` | `string` | 검색어 하이라이트 |

---

## 사용 예제

### 기본 검색

```php
$results = search()
    ->query('PHP 프레임워크')
    ->in('posts', ['title', 'body'])
    ->limit(20)
    ->results();
```

### 페이지네이션

```php
$q = search()
    ->query(input('q'))
    ->in('posts', ['title', 'body']);

$total = $q->count();
$results = $q->limit(20)->offset(($page - 1) * 20)->results();

json()->paginated($results, $total, $page, 20);
```

### 검색어 하이라이트

```php
$s = search()->query('CatPHP');
$results = $s->in('posts', ['title', 'body'])->results();

foreach ($results as $row) {
    echo $s->highlight($row['title']);
    // → 'Hello <mark>CatPHP</mark> World'
}
```

### 커스텀 하이라이트 태그

```php
echo $s->highlight($text, 'strong');
// → '<strong>CatPHP</strong>'
```

### LIKE 드라이버 (FULLTEXT 미사용)

```php
// config: search.driver = 'like'
$results = search()
    ->query('홍길동')
    ->in('users', ['name', 'email'])
    ->limit(10)
    ->results();
```

---

## 내부 동작

### 드라이버별 SQL

#### MySQL FULLTEXT

```sql
SELECT *, MATCH(title, body) AGAINST(? IN BOOLEAN MODE) AS _score
FROM posts
WHERE MATCH(title, body) AGAINST(? IN BOOLEAN MODE)
ORDER BY _score DESC
LIMIT ?
```

**전제**: `FULLTEXT INDEX`가 대상 컬럼에 생성되어 있어야 한다.

#### PostgreSQL tsvector

```sql
SELECT *, ts_rank(to_tsvector('simple', title || ' ' || body), plainto_tsquery('simple', ?)) AS _score
FROM posts
WHERE to_tsvector('simple', title || ' ' || body) @@ plainto_tsquery('simple', ?)
ORDER BY _score DESC
LIMIT ?
```

#### LIKE 폴백 (SQLite 포함)

```sql
SELECT * FROM posts
WHERE title LIKE ? OR body LIKE ?
LIMIT ?
```

와일드카드 이스케이프 적용: `%`, `_`, `\`

### 캐시 흐름

```text
results()
├─ 캐시 키: md5(table:query:columns:limit:offset)
├─ Cache 존재 → 캐시 반환
├─ Cache 미존재 → executeSearch()
│   └─ 드라이버 분기 (MySQL/PostgreSQL/SQLite/LIKE)
└─ Cache 저장 (cache_ttl 초)
```

### 검색어 살균

```php
$c->queryStr = guard()->clean($query);
```

`query()` 호출 시 `guard()->clean()` 자동 적용 — XSS, CRLF, 제어문자 제거.

### SQL 식별자 검증

```php
private static function validateIdentifier(string $name): void {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $name)) {
        throw new \InvalidArgumentException("유효하지 않은 SQL 식별자: {$name}");
    }
}
```

`in()` 메서드에서 테이블명과 컬럼명 모두 검증.

---

## 보안 고려사항

- **검색어 살균**: `guard()->clean()` 자동 적용
- **SQL 식별자 검증**: 테이블/컬럼명에 SQL 인젝션 불가
- **Prepared Statement**: 검색어는 항상 바인딩 파라미터로 전달
- **LIKE 이스케이프**: `%`, `_`, `\` 문자를 `addcslashes()`로 이스케이프
- **하이라이트 XSS 방지**: `highlight()` 내부에서 텍스트를 `htmlspecialchars()`로 이스케이프 후 태그 삽입
- **태그명 살균**: `highlight()` 태그명에서 영숫자만 허용

---

## 주의사항

1. **FULLTEXT INDEX 필수**: MySQL `fulltext` 드라이버 사용 시 대상 컬럼에 FULLTEXT INDEX가 있어야 한다. 없으면 SQL 에러.

2. **SQLite**: FULLTEXT 드라이버 선택 시에도 LIKE 폴백으로 동작한다. FTS5 가상 테이블이 필요하면 직접 `db()->raw()` 사용.

3. **캐시 무효화**: 데이터 변경 시 검색 캐시가 자동 무효화되지 않는다. TTL 만료를 기다리거나 `cache()->del('search:...')` 수동 삭제.

4. **`_score` 컬럼**: FULLTEXT 검색 결과에 `_score` 컬럼이 포함된다. 필요 없으면 무시.

5. **빈 검색어**: `query()` 없이 `results()` 호출 시 빈 배열 반환.

6. **기본 limit**: `limit()` 미설정 시 기본 100개.

---

## 연관 도구

- [DB](DB.md) — 쿼리 실행 (`db()->raw()`)
- [Cache](Cache.md) — 검색 결과 캐싱
- [Guard](Guard.md) — 검색어 살균
- [Paginate](Paginate.md) — 검색 결과 페이지네이션
