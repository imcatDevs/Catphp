<?php declare(strict_types=1);

/** Cat\Paginate 테스트 */
return function (TestRunner $t): void {
    $t->suite('Paginate — offset / lastPage / links / toArray');

    $t->test('offset() 계산', function () use ($t) {
        $p = paginate()->page(3)->perPage(10);
        $t->eq(20, $p->offset());
    });

    $t->test('offset() 1페이지', function () use ($t) {
        $p = paginate()->page(1)->perPage(20);
        $t->eq(0, $p->offset());
    });

    $t->test('lastPage() 계산', function () use ($t) {
        $p = paginate()->total(95)->perPage(10);
        $t->eq(10, $p->lastPage());
    });

    $t->test('lastPage() 정확 나눔', function () use ($t) {
        $p = paginate()->total(100)->perPage(10);
        $t->eq(10, $p->lastPage());
    });

    $t->test('lastPage() 빈 결과', function () use ($t) {
        $p = paginate()->total(0)->perPage(10);
        $t->eq(0, $p->lastPage());
    });

    $t->test('links() 1페이지면 빈 문자열', function () use ($t) {
        $p = paginate()->total(5)->perPage(10)->page(1);
        $t->eq('', $p->links());
    });

    $t->test('links() 여러 페이지 HTML', function () use ($t) {
        $p = paginate()->total(50)->perPage(10)->page(1);
        $html = $p->links();
        $t->contains($html, '<nav');
        $t->contains($html, 'class="active"');
    });

    $t->test('toArray() 구조', function () use ($t) {
        $items = [['id' => 1], ['id' => 2]];
        $p = paginate()->page(2)->perPage(10)->total(50)->items($items);
        $arr = $p->toArray();
        $t->eq(2, $arr['page']);
        $t->eq(10, $arr['per_page']);
        $t->eq(50, $arr['total']);
        $t->eq(5, $arr['last_page']);
        $t->count(2, $arr['data']);
    });
};
