# Paginate — 페이지네이션

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Paginate` |
| 파일 | `catphp/Paginate.php` (177줄) |
| Shortcut | `paginate()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\DB` (`fromQuery()`), 코어 `input()` (페이지 번호) |

---

## 설정

별도 config 없음. 기본 페이지당 20개.

---

## 메서드 레퍼런스

### 빌더

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `page` | `page(?int $page = null): self` | `self` | 현재 페이지 (null이면 `input('page')` 사용) |
| `perPage` | `perPage(int $perPage): self` | `self` | 페이지당 항목 수 |
| `total` | `total(int $total): self` | `self` | 전체 항목 수 |
| `items` | `items(array $items): self` | `self` | 항목 배열 |

### DB 연동

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `fromQuery` | `fromQuery(DB $query, int $perPage = 20): self` | `self` | DB 쿼리 → 자동 count + limit/offset |

### 출력

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `links` | `links(string $urlPattern = '?page={page}', int $window = 2): string` | `string` | 페이지 링크 HTML |
| `toArray` | `toArray(): array` | `array` | API용 배열 (`data`, `total`, `page`, `per_page`, `last_page`) |

### 게터

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `offset` | `offset(): int` | `int` | 오프셋 계산 |
| `lastPage` | `lastPage(): int` | `int` | 마지막 페이지 번호 |
| `hasNext` | `hasNext(): bool` | `bool` | 다음 페이지 존재 |
| `hasPrev` | `hasPrev(): bool` | `bool` | 이전 페이지 존재 |
| `getCurrentPage` | `getCurrentPage(): int` | `int` | 현재 페이지 |
| `getPerPage` | `getPerPage(): int` | `int` | 페이지당 항목 수 |
| `getTotal` | `getTotal(): int` | `int` | 전체 항목 수 |

---

## 사용 예제

### DB 연동 (가장 간편)

```php
$pager = paginate()->fromQuery(
    db()->table('posts')->where('status', 'published')->orderByDesc('id'),
    perPage: 15
);

// 웹: HTML 링크
echo $pager->links('/posts?page={page}');

// API: JSON 응답
$arr = $pager->toArray();
json()->paginated($arr['data'], $arr['page'], $arr['per_page'], $arr['total']);
```

### 수동 설정

```php
$total = db()->table('users')->count();
$users = db()->table('users')->limit(20)->offset(($page - 1) * 20)->all();

$pager = paginate()
    ->page($page)
    ->perPage(20)
    ->total($total)
    ->items($users);

echo $pager->links('/users?page={page}');
```

### 커스텀 URL 패턴

```php
// 쿼리스트링
echo $pager->links('?page={page}&sort=name');

// 경로 기반
echo $pager->links('/posts/page/{page}');
```

### 윈도우 크기 조절

```php
// 현재 페이지 주변 3개씩 표시 (기본 2)
echo $pager->links('?page={page}', window: 3);
```

### 네비게이션 정보

```php
if ($pager->hasPrev()) {
    echo '<a href="?page=' . ($pager->getCurrentPage() - 1) . '">이전</a>';
}
if ($pager->hasNext()) {
    echo '<a href="?page=' . ($pager->getCurrentPage() + 1) . '">다음</a>';
}
echo "총 {$pager->getTotal()}개 중 {$pager->getCurrentPage()}/{$pager->lastPage()} 페이지";
```

---

## 내부 동작

### links() 윈도우 트렁케이션

```text
총 50페이지, 현재 25, window=2:

1 ... 23 24 [25] 26 27 ... 50

총 7페이지 (threshold 이하):
1 2 3 4 5 6 7  ← 전체 출력
```

- threshold = `(window * 2) + 5`
- 페이지 수가 threshold 이하면 전체 출력
- 초과 시: 첫 페이지 + 윈도우 + 마지막 페이지 + 생략 부호

### fromQuery() 흐름

```text
fromQuery($query, 20)
├─ page = input('page') ?? 1
├─ total = $query->count()
├─ items = $query->limit(20)->offset((page-1)*20)->all()
└─ 이뮤터블 인스턴스 반환
```

### HTML 출력 보안

`links()` 내부에서 URL을 `htmlspecialchars(ENT_QUOTES, 'UTF-8')`로 이스케이프한다.

---

## 주의사항

1. **`fromQuery()` 쿼리 2회**: `count()` + `all()` — DB 쿼리가 2회 실행된다. 복잡한 쿼리에서는 별도로 total을 캐싱하는 것이 효율적.

2. **page 자동 감지**: `page()`와 `fromQuery()`는 `input('page')`에서 자동으로 현재 페이지를 읽는다. 쿼리스트링 `?page=N`이 기본.

3. **최소값 보장**: `page()`는 `max(1, ...)`, `perPage()`는 `max(1, ...)` — 0 이하 값 방지.

4. **이뮤터블**: 모든 빌더 메서드가 `clone` 반환. 같은 인스턴스로 다른 설정을 적용할 수 있다.

5. **CSS 클래스**: `links()`가 생성하는 HTML: `<nav class="pagination">`, 현재 페이지 `<a class="active">`, 생략 `<span class="ellipsis">`.

---

## 연관 도구

- [DB](DB.md) — `fromQuery()` 내부 사용
- [Json](Json.md) — `json()->paginated()` API 응답
- [Collection](Collection.md) — 결과 데이터 처리
