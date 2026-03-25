<?php declare(strict_types=1);

/** Cat\Router 통합 테스트 (라우트 등록/매칭 — dispatch 제외) */
return function (TestRunner $t): void {
    $t->suite('Router — 정적/동적 라우트 등록 + 타입 캐스팅 + 그룹');

    // Router는 싱글턴이므로 기존 라우트가 누적됨.
    // dispatch()는 header/exit이 필요하므로 테스트하지 않고,
    // 라우트 등록 후 반환값(self) 확인 + 간접 검증.

    $t->test('get() 라우트 등록 반환 self', function () use ($t) {
        $result = router()->get('/test/unit', fn() => 'ok');
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('post() 라우트 등록', function () use ($t) {
        $result = router()->post('/test/unit-post', fn() => 'ok');
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('any() 모든 메서드 등록', function () use ($t) {
        $result = router()->any('/test/any', fn() => 'ok');
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('group() prefix 적용', function () use ($t) {
        $registered = false;
        router()->group('/api/v2', function () use (&$registered) {
            router()->get('/items', fn() => 'items');
            $registered = true;
        });
        $t->ok($registered);
    });

    $t->test('동적 라우트 {param} 등록', function () use ($t) {
        $result = router()->get('/test/user/{id}', fn(string $id) => $id);
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('타입 라우트 {id:int} 등록', function () use ($t) {
        $result = router()->get('/test/item/{id:int}', fn(int $id) => $id);
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('use() 미들웨어 등록', function () use ($t) {
        $result = router()->use(function () { return null; });
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('notFound() 핸들러 등록', function () use ($t) {
        $result = router()->notFound(fn() => '404 page');
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('redirect() 라우트 등록', function () use ($t) {
        $result = router()->redirect('/old-path', '/new-path', 301);
        $t->isType(\Cat\Router::class, $result);
    });

    $t->test('잘못된 타입 예외', function () use ($t) {
        $t->throws(function () {
            router()->get('/test/bad/{id:nonexistent}', fn() => 'x');
        }, \InvalidArgumentException::class);
    });
};
