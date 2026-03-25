# Collection — 배열 체이닝 유틸

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Collection` |
| 파일 | `catphp/Collection.php` (641줄) |
| Shortcut | `collect()` |
| 싱글턴 | 아님 — 매번 `new Collection($items)` |
| 인터페이스 | `Countable`, `IteratorAggregate`, `JsonSerializable` |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

### 변환

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `map` | `map(callable $callback): self` | `self` | 각 요소에 콜백 적용 |
| `filter` | `filter(?callable $callback = null): self` | `self` | 조건 필터 (null이면 falsy 제거) |
| `reject` | `reject(callable $callback): self` | `self` | 조건 불일치 요소만 |
| `reduce` | `reduce(callable $callback, mixed $initial = null): mixed` | `mixed` | 누적 연산 |
| `flatten` | `flatten(int $depth = 1): self` | `self` | 중첩 배열 평탄화 |
| `flatMap` | `flatMap(callable $callback): self` | `self` | map + flatten |

### 추출

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `pluck` | `pluck(string $key, ?string $indexBy = null): self` | `self` | 특정 키 값 추출 |
| `only` | `only(array $keys): self` | `self` | 특정 키만 |
| `except` | `except(array $keys): self` | `self` | 특정 키 제외 |
| `first` | `first(?callable $callback = null, mixed $default = null): mixed` | `mixed` | 첫 번째 요소 |
| `last` | `last(?callable $callback = null, mixed $default = null): mixed` | `mixed` | 마지막 요소 |
| `nth` | `nth(int $index, mixed $default = null): mixed` | `mixed` | N번째 요소 |

### 조건

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `where` | `where(string $key, mixed $operator = null, mixed $value = null): self` | `self` | 키=값 필터 (연산자 지원) |
| `whereNull` | `whereNull(string $key): self` | `self` | null인 요소 |
| `whereNotNull` | `whereNotNull(string $key): self` | `self` | null이 아닌 요소 |
| `whereIn` | `whereIn(string $key, array $values): self` | `self` | 값 목록 포함 |
| `every` | `every(callable $callback): bool` | `bool` | 모든 요소 조건 충족 |
| `some` | `some(callable $callback): bool` | `bool` | 하나라도 조건 충족 |
| `contains` | `contains(mixed $value): bool` | `bool` | 값 포함 확인 |

### 정렬

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `sort` | `sort(?callable $callback = null): self` | `self` | 오름차순 정렬 |
| `sortDesc` | `sortDesc(): self` | `self` | 내림차순 정렬 |
| `sortBy` | `sortBy(string $key, string $direction = 'asc'): self` | `self` | 키 기준 정렬 |
| `sortKeys` | `sortKeys(): self` | `self` | 키(인덱스) 기준 정렬 |
| `reverse` | `reverse(): self` | `self` | 역순 |
| `shuffle` | `shuffle(): self` | `self` | 셔플 |

### 그룹 / 분할

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `groupBy` | `groupBy(string\|callable $key): self` | `self` | 키 기준 그룹핑 |
| `chunk` | `chunk(int $size): self` | `self` | N개씩 분할 |
| `take` | `take(int $limit): self` | `self` | 앞에서 N개 (음수: 뒤에서) |
| `skip` | `skip(int $count): self` | `self` | 앞에서 N개 건너뛰기 |
| `slice` | `slice(int $offset, ?int $length = null): self` | `self` | 슬라이스 |

### 집계

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `sum` | `sum(string\|callable\|null $key = null): int\|float` | `int\|float` | 합계 |
| `avg` | `avg(?string $key = null): int\|float\|null` | `int\|float\|null` | 평균 |
| `min` | `min(?string $key = null): mixed` | `mixed` | 최솟값 |
| `max` | `max(?string $key = null): mixed` | `mixed` | 최댓값 |
| `median` | `median(?string $key = null): int\|float\|null` | `int\|float\|null` | 중앙값 |

### 결합

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `merge` | `merge(iterable $items): self` | `self` | 합치기 |
| `unique` | `unique(?string $key = null): self` | `self` | 중복 제거 |
| `diff` | `diff(iterable $items): self` | `self` | 차집합 |
| `intersect` | `intersect(iterable $items): self` | `self` | 교집합 |
| `combine` | `combine(iterable $values): self` | `self` | 키-값 결합 |

### 반복 / 유틸

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `each` | `each(callable $callback): self` | `self` | 각 요소 실행 (false 반환 시 중단) |
| `random` | `random(int $count = 1): mixed` | `mixed` | 랜덤 요소 |
| `pipe` | `pipe(callable $callback): mixed` | `mixed` | 컬렉션을 콜백에 전달 |
| `when` | `when(bool $condition, callable $callback): self` | `self` | 조건부 실행 |
| `unless` | `unless(bool $condition, callable $callback): self` | `self` | 조건 거짓일 때 실행 |
| `tap` | `tap(callable $callback): self` | `self` | 부수효과 후 원본 반환 |
| `dump` | `dump(): self` | `self` | `var_dump` 디버그 |

### 출력

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `toArray` | `toArray(): array` | `array` | 배열 변환 (재귀) |
| `toJson` | `toJson(int $flags = ...): string` | `string` | JSON 문자열 |
| `implode` | `implode(string $glue, ?string $key = null): string` | `string` | 문자열 결합 |
| `values` | `values(): self` | `self` | 값 재인덱싱 (0부터) |
| `keys` | `keys(): self` | `self` | 키 목록 |
| `flip` | `flip(): self` | `self` | 키-값 뒤집기 |
| `isEmpty` | `isEmpty(): bool` | `bool` | 비어있는지 |
| `isNotEmpty` | `isNotEmpty(): bool` | `bool` | 비어있지 않은지 |
| `count` | `count(): int` | `int` | 요소 개수 |

---

## 사용 예제

### 기본 체이닝

```php
$names = collect($users)
    ->where('active', true)
    ->pluck('name')
    ->unique()
    ->sort()
    ->values()
    ->toArray();
```

### 그룹핑 + 집계

```php
$byCategory = collect($products)
    ->groupBy('category')
    ->map(fn($group) => [
        'count' => $group->count(),
        'total' => $group->sum('price'),
        'avg'   => $group->avg('price'),
    ])
    ->toArray();
```

### 필터링 + 변환

```php
$result = collect($orders)
    ->where('status', 'completed')
    ->where('total', '>=', 10000)
    ->sortBy('created_at', 'desc')
    ->take(10)
    ->map(fn($o) => [
        'id'    => $o['id'],
        'total' => number_format($o['total']),
    ])
    ->toArray();
```

### 점 표기법

```php
$cities = collect($users)->pluck('address.city')->unique()->toArray();
// → ['Seoul', 'Busan', ...]
```

### 청크 처리

```php
collect($allUsers)->chunk(100)->each(function ($chunk) {
    foreach ($chunk as $user) {
        // 100명씩 배치 처리
    }
});
```

### 조건부 체이닝

```php
$result = collect($items)
    ->when($search !== '', fn($c) => $c->filter(
        fn($i) => str_contains($i['name'], $search)
    ))
    ->sortBy('name')
    ->toArray();
```

---

## 내부 동작

### 이뮤터블 패턴

모든 변환 메서드는 `new self(...)` — 원본 컬렉션 변이 없음. `each()`와 `tap()`만 `$this` 반환.

### 점 표기법 (dataGet)

`pluck()`, `where()`, `sortBy()` 등에서 중첩 키 접근 지원:

```text
dataGet($item, 'user.address.city')
├─ 점(.) 포함? → 세그먼트별 순차 탐색
├─ 배열: $item['user']['address']['city']
├─ 객체: $item->user->address->city
└─ 없으면 null
```

### where() 연산자

```php
->where('age', '>=', 18)    // 2파라미터: = 기본
->where('status', 'active')  // 3파라미터: 연산자 명시
```

지원 연산자: `=`, `===`, `!=`, `>`, `>=`, `<`, `<=`

### JSON 직렬화

`JsonSerializable` 구현 → `json_encode(collect([...]))` 직접 사용 가능. 중첩 Collection도 재귀적으로 배열 변환.

---

## 주의사항

1. **싱글턴 아님**: `collect()` 호출마다 새 인스턴스 생성. 대량 데이터에서 메모리 주의.

2. **take() 음수**: `take(-5)` — 뒤에서 5개 반환.

3. **groupBy 반환**: Collection of Collections — 각 그룹이 Collection 인스턴스.

4. **where() 느슨한 비교**: `=` 연산자는 `==` (느슨한 비교). 엄격 비교는 `===` 사용.

5. **contains() 클로저**: `contains(fn($v) => ...)` 형태로 콜백 조건도 사용 가능.

6. **median() 정렬**: 내부에서 자동 정렬 후 중앙값 계산.

---

## 연관 도구

- [DB](DB.md) — `db()->all()` 결과를 `collect()`로 래핑
- [Paginate](Paginate.md) — 페이지네이션 데이터 처리
- [Json](Json.md) — `collect()->toArray()` → JSON 응답
