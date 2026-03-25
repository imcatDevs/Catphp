<?php declare(strict_types=1);

/** Cat\Slug 테스트 */
return function (TestRunner $t): void {
    $t->suite('Slug — make / unique');

    $t->test('make() 영문 슬러그', function () use ($t) {
        $t->eq('hello-world', slug()->make('Hello World'));
    });

    $t->test('make() 한글 슬러그', function () use ($t) {
        $result = slug()->make('안녕 세계');
        $t->eq('안녕-세계', $result);
    });

    $t->test('make() 특수문자 제거', function () use ($t) {
        $t->eq('test-123', slug()->make('Test! @#$ 123'));
    });

    $t->test('make() 연속 공백 처리', function () use ($t) {
        $t->eq('a-b', slug()->make('  a   b  '));
    });

    $t->test('make() 커스텀 구분자', function () use ($t) {
        $t->eq('hello_world', slug()->make('Hello World', '_'));
    });

    $t->test('unique() 중복 없을 때', function () use ($t) {
        $result = slug()->unique('Test', fn($s) => false);
        $t->eq('test', $result);
    });

    $t->test('unique() 중복 시 suffix', function () use ($t) {
        $taken = ['test' => true, 'test-2' => true];
        $result = slug()->unique('Test', fn($s) => isset($taken[$s]));
        $t->eq('test-3', $result);
    });
};
