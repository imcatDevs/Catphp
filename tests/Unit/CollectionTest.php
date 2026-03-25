<?php declare(strict_types=1);

/** Collection 배열 체이닝 테스트 */
return function (TestRunner $t): void {
    $t->suite('Collection — map / filter / pluck / where / sort / sum / chunk / unique');

    $t->test('map() 각 요소 변환', function () use ($t) {
        $c = collect([1, 2, 3])->map(fn($v) => $v * 2);
        $t->eq([2, 4, 6], $c->toArray());
    });

    $t->test('filter() 조건 필터', function () use ($t) {
        $c = collect([1, 2, 3, 4, 5])->filter(fn($v) => $v > 3);
        $t->eq([4, 5], $c->values()->toArray());
    });

    $t->test('filter() null이면 falsy 제거', function () use ($t) {
        $c = collect([0, 1, '', 'a', null, false, 2])->filter();
        $t->eq([1, 'a', 2], $c->values()->toArray());
    });

    $t->test('reject() 조건 반대 필터', function () use ($t) {
        $c = collect([1, 2, 3, 4])->reject(fn($v) => $v % 2 === 0);
        $t->eq([1, 3], $c->values()->toArray());
    });

    $t->test('reduce() 누적 연산', function () use ($t) {
        $sum = collect([1, 2, 3, 4])->reduce(fn($carry, $v) => $carry + $v, 0);
        $t->eq(10, $sum);
    });

    $t->test('pluck() 키 추출', function () use ($t) {
        $items = [['name' => 'Alice', 'age' => 30], ['name' => 'Bob', 'age' => 25]];
        $names = collect($items)->pluck('name')->toArray();
        $t->eq(['Alice', 'Bob'], $names);
    });

    $t->test('where() 조건 검색', function () use ($t) {
        $items = [['active' => true, 'name' => 'A'], ['active' => false, 'name' => 'B'], ['active' => true, 'name' => 'C']];
        $result = collect($items)->where('active', true)->values()->toArray();
        $t->count(2, $result);
        $t->eq('A', $result[0]['name']);
        $t->eq('C', $result[1]['name']);
    });

    $t->test('sort() 정렬', function () use ($t) {
        $c = collect([3, 1, 2])->sort()->values();
        $t->eq([1, 2, 3], $c->toArray());
    });

    $t->test('sortBy() 키 기준 정렬', function () use ($t) {
        $items = [['name' => 'C'], ['name' => 'A'], ['name' => 'B']];
        $result = collect($items)->sortBy('name')->values()->pluck('name')->toArray();
        $t->eq(['A', 'B', 'C'], $result);
    });

    $t->test('sum() 합계 (숫자)', function () use ($t) {
        $t->eq(15, collect([1, 2, 3, 4, 5])->sum());
    });

    $t->test('sum() 합계 (키)', function () use ($t) {
        $items = [['price' => 100], ['price' => 200], ['price' => 300]];
        $t->eq(600, collect($items)->sum('price'));
    });

    $t->test('avg() 평균', function () use ($t) {
        $result = collect([1, 2, 3, 4, 5])->avg();
        $t->ok($result == 3);
    });

    $t->test('min() / max()', function () use ($t) {
        $c = collect([5, 2, 8, 1, 9]);
        $t->eq(1, $c->min());
        $t->eq(9, $c->max());
    });

    $t->test('chunk() 분할', function () use ($t) {
        $chunks = collect([1, 2, 3, 4, 5])->chunk(2)->toArray();
        $t->count(3, $chunks);
        $t->eq([1, 2], $chunks[0]);
    });

    $t->test('unique() 중복 제거', function () use ($t) {
        $c = collect([1, 2, 2, 3, 3, 3])->unique()->values();
        $t->eq([1, 2, 3], $c->toArray());
    });

    $t->test('flatten() 1단계 평탄화', function () use ($t) {
        $c = collect([[1, 2], [3, 4]])->flatten();
        $t->eq([1, 2, 3, 4], $c->toArray());
    });

    $t->test('flatten() 깊은 평탄화', function () use ($t) {
        $c = collect([[1, 2], [3, [4, 5]]])->flatten(PHP_INT_MAX);
        $t->eq([1, 2, 3, 4, 5], $c->toArray());
    });

    $t->test('groupBy() 그룹핑', function () use ($t) {
        $items = [['type' => 'a', 'v' => 1], ['type' => 'b', 'v' => 2], ['type' => 'a', 'v' => 3]];
        $grouped = collect($items)->groupBy('type')->toArray();
        $t->count(2, $grouped);
        $t->count(2, $grouped['a']);
        $t->count(1, $grouped['b']);
    });

    $t->test('first() / last()', function () use ($t) {
        $c = collect([10, 20, 30]);
        $t->eq(10, $c->first());
        $t->eq(30, $c->last());
    });

    $t->test('contains() 값 존재', function () use ($t) {
        $t->ok(collect([1, 2, 3])->contains(2));
        $t->notOk(collect([1, 2, 3])->contains(5));
    });

    $t->test('isEmpty() / isNotEmpty()', function () use ($t) {
        $t->ok(collect([])->isEmpty());
        $t->ok(collect([1])->isNotEmpty());
    });

    $t->test('count() Countable', function () use ($t) {
        $t->eq(3, count(collect([1, 2, 3])));
    });

    $t->test('toJson() JSON 직렬화', function () use ($t) {
        $json = collect(['a' => 1, 'b' => 2])->toJson();
        $t->eq('{"a":1,"b":2}', $json);
    });

    $t->test('merge() 병합', function () use ($t) {
        $c = collect([1, 2])->merge([3, 4]);
        $t->eq([1, 2, 3, 4], $c->toArray());
    });

    $t->test('diff() 차집합', function () use ($t) {
        $c = collect([1, 2, 3, 4])->diff([2, 4])->values();
        $t->eq([1, 3], $c->toArray());
    });

    $t->test('intersect() 교집합', function () use ($t) {
        $c = collect([1, 2, 3, 4])->intersect([2, 3, 5])->values();
        $t->eq([2, 3], $c->toArray());
    });

    $t->test('take() / skip()', function () use ($t) {
        $c = collect([1, 2, 3, 4, 5]);
        $t->eq([1, 2, 3], $c->take(3)->toArray());
        $t->eq([3, 4, 5], $c->skip(2)->values()->toArray());
    });

    $t->test('reverse() 역순', function () use ($t) {
        $c = collect([1, 2, 3])->reverse()->values();
        $t->eq([3, 2, 1], $c->toArray());
    });

    $t->test('keys() / values()', function () use ($t) {
        $c = collect(['a' => 1, 'b' => 2]);
        $t->eq(['a', 'b'], $c->keys()->toArray());
        $t->eq([1, 2], $c->values()->toArray());
    });

    $t->test('median() 중앙값 (홀수)', function () use ($t) {
        $result = collect([1, 2, 3, 4, 5])->median();
        $t->ok($result == 3);
    });

    $t->test('median() 중앙값 (짝수)', function () use ($t) {
        $result = collect([1, 2, 3, 4])->median();
        $t->ok($result == 2.5);
    });
};
