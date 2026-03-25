<?php declare(strict_types=1);

/** Request HTTP 요청 추상화 테스트 */
return function (TestRunner $t): void {
    $t->suite('Request — method / path / input / only / except / has / filled / override');

    // 테스트 전 $_SERVER 백업
    $origServer = $_SERVER;
    $origGet = $_GET;
    $origPost = $_POST;

    $t->test('method() 기본 GET', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        request()->refresh();
        $t->eq('GET', request()->method());
    });

    $t->test('method() POST', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        request()->refresh();
        $t->eq('POST', request()->method());
    });

    $t->test('isMethod() 비교', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        request()->refresh();
        $t->ok(request()->isMethod('get'));
        $t->ok(request()->isMethod('GET'));
        $t->notOk(request()->isMethod('POST'));
    });

    $t->test('isGet() / isPost()', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        request()->refresh();
        $t->ok(request()->isGet());
        $t->notOk(request()->isPost());
    });

    $t->test('path() 쿼리스트링 제거', function () use ($t) {
        $_SERVER['REQUEST_URI'] = '/api/users?page=1&sort=name';
        $t->eq('/api/users', request()->path());
    });

    $t->test('path() 슬래시만', function () use ($t) {
        $_SERVER['REQUEST_URI'] = '/';
        $t->eq('/', request()->path());
    });

    $t->test('override() 입력 모킹', function () use ($t) {
        request()->override(['name' => 'Alice', 'age' => '30']);
        $t->eq('Alice', request()->input('name'));
        $t->eq('30', request()->input('age'));
        $t->isNull(request()->input('missing'));
    });

    $t->test('input() 기본값', function () use ($t) {
        request()->override([]);
        $t->eq('default', request()->input('key', 'default'));
    });

    $t->test('all() 전체 입력', function () use ($t) {
        request()->override(['a' => '1', 'b' => '2']);
        $all = request()->all();
        $t->hasKey($all, 'a');
        $t->hasKey($all, 'b');
    });

    $t->test('only() 지정 키만', function () use ($t) {
        request()->override(['name' => 'A', 'email' => 'a@b.c', 'age' => '20']);
        $result = request()->only(['name', 'email']);
        $t->hasKey($result, 'name');
        $t->hasKey($result, 'email');
        $t->eq(2, count($result));
    });

    $t->test('except() 지정 키 제외', function () use ($t) {
        request()->override(['name' => 'A', 'password' => 'secret', 'email' => 'a@b.c']);
        $result = request()->except(['password']);
        $t->hasKey($result, 'name');
        $t->hasKey($result, 'email');
        $t->eq(2, count($result));
    });

    $t->test('has() 입력 키 존재', function () use ($t) {
        request()->override(['key' => 'value']);
        $t->ok(request()->has('key'));
        $t->notOk(request()->has('missing'));
    });

    $t->test('filled() 비어있지 않은 값', function () use ($t) {
        request()->override(['name' => 'Alice', 'empty' => '', 'zero' => '0']);
        $t->ok(request()->filled('name'));
        $t->notOk(request()->filled('empty'));
        $t->notOk(request()->filled('missing'));
    });

    $t->test('header() 서버 변수 읽기', function () use ($t) {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $t->eq('application/json', request()->header('Accept'));
    });

    $t->test('header() Content-Type', function () use ($t) {
        $_SERVER['CONTENT_TYPE'] = 'text/html';
        $t->eq('text/html', request()->header('Content-Type'));
    });

    $t->test('header() 존재하지 않는 헤더', function () use ($t) {
        $t->isNull(request()->header('X-Nonexistent'));
        $t->eq('default', request()->header('X-Nonexistent', 'default'));
    });

    $t->test('bearerToken() Authorization 헤더', function () use ($t) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123token';
        $t->eq('abc123token', request()->bearerToken());
    });

    $t->test('bearerToken() 비Bearer 헤더', function () use ($t) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
        $t->isNull(request()->bearerToken());
    });

    $t->test('bearerToken() 헤더 없음', function () use ($t) {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $t->isNull(request()->bearerToken());
    });

    $t->test('isAjax() XMLHttpRequest 감지', function () use ($t) {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $t->ok(request()->isAjax());
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $t->notOk(request()->isAjax());
    });

    $t->test('isJson() Content-Type 감지', function () use ($t) {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $t->ok(request()->isJson());
        $_SERVER['CONTENT_TYPE'] = 'text/html';
        $t->notOk(request()->isJson());
    });

    $t->test('isSecure() HTTPS 감지', function () use ($t) {
        $_SERVER['HTTPS'] = 'on';
        $t->ok(request()->isSecure());
        $_SERVER['HTTPS'] = 'off';
        $t->notOk(request()->isSecure());
        unset($_SERVER['HTTPS']);
    });

    $t->test('refresh() 슈퍼글로벌 리프레시', function () use ($t) {
        $_GET = ['refreshed' => '1'];
        $_POST = [];
        request()->refresh();
        $t->eq('1', request()->input('refreshed'));
    });

    // 원복
    $_SERVER = $origServer;
    $_GET = $origGet;
    $_POST = $origPost;
    request()->refresh();
};
