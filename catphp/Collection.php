<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Collection — 배열 체이닝 유틸
 *
 * Laravel Collection 스타일의 배열 조작 메서드 체인.
 *
 * 사용법:
 *   collect([1, 2, 3])->map(fn($v) => $v * 2)->toArray();
 *   collect($users)->pluck('name')->unique()->sort()->values();
 *   collect($items)->where('active', true)->groupBy('category');
 *   collect($data)->filter()->chunk(10)->each(fn($chunk) => ...);
 */
final class Collection implements \Countable, \IteratorAggregate, \JsonSerializable
{
    /** @var list<mixed>|array<string, mixed> */
    private array $items;

    /** @param iterable<mixed> $items */
    public function __construct(iterable $items = [])
    {
        $this->items = is_array($items) ? $items : iterator_to_array($items);
    }

    // ── 변환 ──

    /**
     * 각 요소에 콜백 적용
     *
     * @param callable(mixed, string|int): mixed $callback
     */
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items, array_keys($this->items)));
    }

    /**
     * 조건에 맞는 요소만 필터
     *
     * @param ?callable(mixed, string|int): bool $callback null이면 falsy 값 제거
     */
    public function filter(?callable $callback = null): self
    {
        if ($callback === null) {
            return new self(array_filter($this->items));
        }
        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /** 조건에 맞지 않는 요소만 남기기 */
    public function reject(callable $callback): self
    {
        return $this->filter(fn($v, $k) => !$callback($v, $k));
    }

    /**
     * 누적 연산
     *
     * @param callable(mixed, mixed): mixed $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * 중첩 배열 1단계 평탄화
     */
    public function flatten(int $depth = 1): self
    {
        $result = [];
        $this->flattenArray($this->items, $depth, $result);
        return new self($result);
    }

    /**
     * 각 요소를 콜백으로 변환 후 평탄화
     *
     * @param callable(mixed, string|int): iterable $callback
     */
    public function flatMap(callable $callback): self
    {
        return $this->map($callback)->flatten();
    }

    // ── 추출 ──

    /**
     * 특정 키의 값만 추출
     *
     * @param string $key 추출할 키
     * @param ?string $indexBy 인덱스로 사용할 키
     */
    public function pluck(string $key, ?string $indexBy = null): self
    {
        $result = [];
        foreach ($this->items as $item) {
            $value = $this->dataGet($item, $key);
            if ($indexBy !== null) {
                $index = $this->dataGet($item, $indexBy);
                $result[(string) $index] = $value;
            } else {
                $result[] = $value;
            }
        }
        return new self($result);
    }

    /** 특정 키만 포함 */
    public function only(array $keys): self
    {
        return new self(array_intersect_key($this->items, array_flip($keys)));
    }

    /** 특정 키 제외 */
    public function except(array $keys): self
    {
        return new self(array_diff_key($this->items, array_flip($keys)));
    }

    /** 첫 번째 요소 */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if ($this->items === []) {
                return $default;
            }
            $copy = $this->items;
            return reset($copy);
        }
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return $default;
    }

    /** 마지막 요소 */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if ($this->items === []) {
                return $default;
            }
            $copy = $this->items;
            return end($copy);
        }
        $result = $default;
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                $result = $value;
            }
        }
        return $result;
    }

    /** N번째 요소 */
    public function nth(int $index, mixed $default = null): mixed
    {
        $values = array_values($this->items);
        return $values[$index] ?? $default;
    }

    // ── 조건 ──

    /** where 조건 필터 (키=값 매칭) */
    public function where(string $key, mixed $operator = null, mixed $value = null): self
    {
        // where('key', 'value') → where('key', '=', 'value')
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $actual = $this->dataGet($item, $key);
            return match ($operator) {
                '='       => $actual == $value,
                '==='     => $actual === $value,
                '!='      => $actual != $value,
                '>'       => $actual > $value,
                '>='      => $actual >= $value,
                '<'       => $actual < $value,
                '<='      => $actual <= $value,
                default   => $actual == $value,
            };
        });
    }

    /** where + null 허용 */
    public function whereNull(string $key): self
    {
        return $this->filter(fn($item) => $this->dataGet($item, $key) === null);
    }

    /** where + null 제외 */
    public function whereNotNull(string $key): self
    {
        return $this->filter(fn($item) => $this->dataGet($item, $key) !== null);
    }

    /**
     * 값이 목록에 포함되는 요소만
     *
     * @param list<mixed> $values
     */
    public function whereIn(string $key, array $values): self
    {
        return $this->filter(fn($item) => in_array($this->dataGet($item, $key), $values, true));
    }

    /** 모든 요소가 조건 충족? */
    public function every(callable $callback): bool
    {
        foreach ($this->items as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }
        return true;
    }

    /** 하나라도 조건 충족? */
    public function some(callable $callback): bool
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }
        return false;
    }

    /** 값 포함 확인 */
    public function contains(mixed $value): bool
    {
        if ($value instanceof \Closure) {
            return $this->some($value);
        }
        return in_array($value, $this->items, true);
    }

    // ── 정렬 ──

    /** 오름차순 정렬 */
    public function sort(?callable $callback = null): self
    {
        $items = $this->items;
        $callback ? usort($items, $callback) : sort($items);
        return new self($items);
    }

    /** 내림차순 정렬 */
    public function sortDesc(): self
    {
        $items = $this->items;
        rsort($items);
        return new self($items);
    }

    /** 키 기준 정렬 */
    public function sortBy(string $key, string $direction = 'asc'): self
    {
        $items = $this->items;
        usort($items, function ($a, $b) use ($key, $direction) {
            $va = $this->dataGet($a, $key);
            $vb = $this->dataGet($b, $key);
            $cmp = $va <=> $vb;
            return $direction === 'desc' ? -$cmp : $cmp;
        });
        return new self($items);
    }

    /** 역순 */
    public function reverse(): self
    {
        return new self(array_reverse($this->items, true));
    }

    /** 키 기준 정렬 */
    public function sortKeys(): self
    {
        $items = $this->items;
        ksort($items);
        return new self($items);
    }

    // ── 그룹 / 분할 ──

    /** 키 기준 그룹핑 */
    public function groupBy(string|callable $key): self
    {
        $groups = [];
        foreach ($this->items as $item) {
            $group = is_callable($key) ? $key($item) : $this->dataGet($item, $key);
            $groups[(string) $group][] = $item;
        }
        return new self(array_map(fn($g) => new self($g), $groups));
    }

    /**
     * N개씩 분할
     *
     * @return self Collection of Collections
     */
    public function chunk(int $size): self
    {
        if ($size <= 0) {
            return new self();
        }
        $chunks = array_chunk($this->items, $size, true);
        return new self(array_map(fn($c) => new self($c), $chunks));
    }

    /** 앞에서 N개 */
    public function take(int $limit): self
    {
        if ($limit < 0) {
            return new self(array_slice($this->items, $limit));
        }
        return new self(array_slice($this->items, 0, $limit));
    }

    /** 건너뛰기 */
    public function skip(int $count): self
    {
        return new self(array_slice($this->items, $count));
    }

    /** 슬라이스 */
    public function slice(int $offset, ?int $length = null): self
    {
        return new self(array_slice($this->items, $offset, $length, true));
    }

    // ── 집계 ──

    /** 합계 */
    public function sum(string|callable|null $key = null): int|float
    {
        if ($key === null) {
            return array_sum($this->items);
        }
        if ($key instanceof \Closure || !is_string($key)) {
            return $this->map($key)->reduce(
                fn($carry, $v) => $carry + (is_numeric($v) ? $v : 0),
                0
            );
        }
        return $this->pluck($key)->reduce(
            fn($carry, $v) => $carry + (is_numeric($v) ? $v : 0),
            0
        );
    }

    /** 평균 */
    public function avg(?string $key = null): int|float|null
    {
        $count = $this->count();
        if ($count === 0) {
            return null;
        }
        return $this->sum($key) / $count;
    }

    /** 최솟값 */
    public function min(?string $key = null): mixed
    {
        $items = $key !== null ? $this->pluck($key)->toArray() : $this->items;
        return $items === [] ? null : min($items);
    }

    /** 최댓값 */
    public function max(?string $key = null): mixed
    {
        $items = $key !== null ? $this->pluck($key)->toArray() : $this->items;
        return $items === [] ? null : max($items);
    }

    /** 중앙값 */
    public function median(?string $key = null): int|float|null
    {
        $items = $key !== null ? $this->pluck($key)->sort()->values()->toArray() : $this->sort()->values()->toArray();
        $count = count($items);
        if ($count === 0) {
            return null;
        }
        $mid = intdiv($count, 2);
        if ($count % 2 === 0) {
            return ($items[$mid - 1] + $items[$mid]) / 2;
        }
        return $items[$mid];
    }

    // ── 결합 ──

    /** 다른 배열/컬렉션 합치기 */
    public function merge(iterable $items): self
    {
        $other = $items instanceof self ? $items->toArray() : (is_array($items) ? $items : iterator_to_array($items));
        return new self(array_merge($this->items, $other));
    }

    /** 중복 제거 (값 기준) */
    public function unique(?string $key = null): self
    {
        if ($key !== null) {
            $seen = [];
            $result = [];
            foreach ($this->items as $item) {
                $k = (string) $this->dataGet($item, $key);
                if (!isset($seen[$k])) {
                    $seen[$k] = true;
                    $result[] = $item;
                }
            }
            return new self($result);
        }
        return new self(array_unique($this->items, SORT_REGULAR));
    }

    /** 차집합 */
    public function diff(iterable $items): self
    {
        $other = $items instanceof self ? $items->toArray() : (is_array($items) ? $items : iterator_to_array($items));
        return new self(array_diff($this->items, $other));
    }

    /** 교집합 */
    public function intersect(iterable $items): self
    {
        $other = $items instanceof self ? $items->toArray() : (is_array($items) ? $items : iterator_to_array($items));
        return new self(array_intersect($this->items, $other));
    }

    /** 키-값 쌍으로 결합 */
    public function combine(iterable $values): self
    {
        $vals = $values instanceof self ? $values->toArray() : (is_array($values) ? $values : iterator_to_array($values));
        $result = array_combine($this->items, $vals);
        return new self($result !== false ? $result : []);
    }

    // ── 반복 ──

    /**
     * 각 요소에 콜백 실행 (결과 무시)
     *
     * @param callable(mixed, string|int): mixed $callback false 반환 시 중단
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /** 랜덤 요소 */
    public function random(int $count = 1): mixed
    {
        if ($this->items === []) {
            return $count === 1 ? null : new self();
        }

        $keys = (array) array_rand($this->items, min($count, count($this->items)));
        if ($count === 1) {
            return $this->items[$keys[0]];
        }

        return new self(array_map(fn($k) => $this->items[$k], $keys));
    }

    /** 셔플 */
    public function shuffle(): self
    {
        $items = $this->items;
        shuffle($items);
        return new self($items);
    }

    // ── 변환 / 출력 ──

    /** 배열로 변환 */
    public function toArray(): array
    {
        return array_map(
            fn($v) => $v instanceof self ? $v->toArray() : $v,
            $this->items
        );
    }

    /** JSON 문자열 */
    public function toJson(int $flags = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $flags) ?: '[]';
    }

    /** 문자열 결합 */
    public function implode(string $glue, ?string $key = null): string
    {
        $items = $key !== null ? $this->pluck($key)->toArray() : $this->items;
        return implode($glue, $items);
    }

    /** 값만 재인덱싱 (0부터) */
    public function values(): self
    {
        return new self(array_values($this->items));
    }

    /** 키 목록 */
    public function keys(): self
    {
        return new self(array_keys($this->items));
    }

    /** 키-값 뒤집기 */
    public function flip(): self
    {
        return new self(array_flip($this->items));
    }

    /** 비어있는지 */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** 비어있지 않은지 */
    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    /** 요소 개수 */
    public function count(): int
    {
        return count($this->items);
    }

    /** 콜백 파이프 */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /** 조건부 실행 */
    public function when(bool $condition, callable $callback): self
    {
        if ($condition) {
            return $callback($this) ?? $this;
        }
        return $this;
    }

    /** 조건 거짓일 때만 콜백 실행 */
    public function unless(bool $condition, callable $callback): self
    {
        return $this->when(!$condition, $callback);
    }

    /** 컬렉션을 콜백에 전달하고 원본 반환 (부수효과용) */
    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    /** 디버그 덤프 */
    public function dump(): self
    {
        var_dump($this->toArray());
        return $this;
    }

    // ── 인터페이스 구현 ──

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ── 내부 ──

    /** 중첩 배열/객체에서 값 추출 (점 표기법 지원: 'user.address.city') */
    private function dataGet(mixed $target, string $key): mixed
    {
        // 점 표기법이 아닌 경우 직접 접근
        if (!str_contains($key, '.')) {
            if (is_array($target)) {
                return $target[$key] ?? null;
            }
            if (is_object($target)) {
                return $target->{$key} ?? null;
            }
            return null;
        }

        // 점 표기법: 세그먼트별 순차 탐색
        $segments = explode('.', $key);
        $current = $target;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (is_object($current) && property_exists($current, $segment)) {
                $current = $current->{$segment};
            } else {
                return null;
            }
        }

        return $current;
    }

    /** 재귀 평탄화 */
    private function flattenArray(array $array, int $depth, array &$result): void
    {
        foreach ($array as $item) {
            if (is_array($item) && $depth > 0) {
                $this->flattenArray($item, $depth - 1, $result);
            } elseif ($item instanceof self && $depth > 0) {
                $this->flattenArray($item->toArray(), $depth - 1, $result);
            } else {
                $result[] = $item;
            }
        }
    }
}
