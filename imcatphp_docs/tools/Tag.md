# Tag — 태그/카테고리 관리

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Tag` |
| 파일 | `catphp/Tag.php` (134줄) |
| Shortcut | `tag()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\DB`, `Cat\Slug`, `Cat\Guard` |

---

## 설정

별도 config 없음. `tags` + `taggables` 테이블 필요.

### 테이블 구조

```sql
CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE taggables (
    tag_id INTEGER NOT NULL,
    taggable_type VARCHAR(50) NOT NULL,
    taggable_id INTEGER NOT NULL,
    PRIMARY KEY (tag_id, taggable_type, taggable_id)
);
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `attach` | `attach(string $table, int\|string $id, array $tags): void` | `void` | 태그 붙이기 |
| `detach` | `detach(string $table, int\|string $id, array $tags): void` | `void` | 태그 제거 |
| `sync` | `sync(string $table, int\|string $id, array $tags): void` | `void` | 태그 동기화 (기존 제거 후 재설정) |
| `tagged` | `tagged(string $table, string $tagName): array` | `array` | 특정 태그의 항목 ID 목록 |
| `tags` | `tags(string $table, int\|string $id): array` | `array` | 항목의 태그 목록 (Guard 살균) |
| `cloud` | `cloud(string $table): array` | `array` | 태그 클라우드 `['name' => count]` |
| `popular` | `popular(int $limit = 10): array` | `array` | 인기 태그 상위 N개 (전체 테이블 대상) |

---

## 사용 예제

### 태그 붙이기

```php
// 게시글에 태그 추가
tag()->attach('posts', $postId, ['PHP', 'CatPHP', '프레임워크']);
```

### 태그 동기화

```php
// 기존 태그 모두 제거 후 새로 설정
tag()->sync('posts', $postId, ['PHP', '웹개발']);
```

### 태그 제거

```php
tag()->detach('posts', $postId, ['PHP']);
```

### 항목의 태그 조회

```php
$tags = tag()->tags('posts', $postId);
// [['name' => 'PHP', 'slug' => 'php'], ['name' => 'CatPHP', 'slug' => 'catphp']]
```

### 특정 태그의 항목 조회

```php
$postIds = tag()->tagged('posts', 'PHP');
// ['1', '5', '12', ...]  (PDO 반환값은 문자열)

$posts = db()->table('posts')->whereIn('id', $postIds)->all();
```

### 태그 클라우드

```php
$cloud = tag()->cloud('posts');
// ['PHP' => 15, 'JavaScript' => 8, 'CatPHP' => 5, ...]
```

### 인기 태그

```php
$popular = tag()->popular(5);
// [['name' => 'PHP', 'slug' => 'php', 'count' => '15'], ...]
```

---

## 내부 동작

### 다형성 태깅

`taggables` 테이블은 `taggable_type`(테이블명) + `taggable_id`(PK)로 어떤 테이블에든 태그를 연결할 수 있다:

```text
taggables:
  tag_id=1, taggable_type='posts',    taggable_id=5
  tag_id=1, taggable_type='products', taggable_id=12
```

### attach() 흐름

```text
attach('posts', 5, ['PHP'])
├─ guard()->clean('PHP') → 살균
├─ slug()->make('PHP') → 'php'
├─ tags 테이블에서 slug='php' 검색
│   ├─ 없으면 → INSERT INTO tags
│   └─ 있으면 → 기존 tag_id 사용
├─ taggables에 중복 확인
└─ 없으면 → INSERT INTO taggables
```

### sync() 흐름

```text
sync('posts', 5, ['PHP', '웹개발'])
├─ DELETE FROM taggables WHERE type='posts' AND id=5
└─ attach('posts', 5, ['PHP', '웹개발'])
```

---

## 보안 고려사항

- **태그명 살균**: `guard()->clean()` — XSS 방지
- **결과 살균**: `tags()`, `popular()` 결과에 `guard()->cleanArray()` 적용. `cloud()`도 `guard()->clean()` 적용
- **Prepared Statement**: 모든 DB 쿼리가 바인딩 파라미터 사용
- **Slug 정규화**: 대소문자 차이로 인한 중복 태그 방지

---

## 주의사항

1. **테이블 필수**: `tags`, `taggables` 테이블이 미리 생성되어 있어야 한다.
2. **성능**: `cloud()`, `popular()`는 JOIN + GROUP BY 쿼리. 대량 데이터에서는 캐시 사용 권장.
3. **삭제 시 정리**: 콘텐츠 삭제 시 `taggables` 레코드를 수동으로 정리해야 한다.
4. **태그명 중복**: slug 기반으로 중복 판단. 'PHP'와 'php'는 같은 태그로 처리.
5. **popular() vs cloud()**: `cloud($table)`은 특정 테이블 기준, `popular()`는 전체 테이블 대상(테이블 구분 없이 전역 인기 태그).
6. **살균 범위**: `attach()`만 `guard()->clean()`으로 태그명 살균 후 저장. `detach()`·`tagged()`는 slug 조회 목적이므로 살균 없이 `slug()->make()`만 호출.

---

## 연관 도구

- [DB](DB.md) — 쿼리 실행
- [Slug](Slug.md) — 태그 slug 생성
- [Guard](Guard.md) — 태그명 살균
- [Search](Search.md) — 태그 기반 검색
