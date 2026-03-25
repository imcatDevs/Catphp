<?php declare(strict_types=1);

/** catphp.php 코어 헬퍼 함수 테스트 */
return function (TestRunner $t): void {
    $t->suite('Core — config / parse_size / env / is_cli');

    $t->test('config() dot notation 읽기', function () use ($t) {
        $t->eq('Asia/Seoul', config('app.timezone'));
    });

    $t->test('config() 기본값 반환', function () use ($t) {
        $t->eq('fallback', config('nonexistent.key', 'fallback'));
    });

    $t->test('config() 중첩 키 읽기', function () use ($t) {
        $t->ok(is_array(config('cors.origins')));
    });

    $t->test('parse_size() M 단위', function () use ($t) {
        $t->eq(10485760, parse_size('10M'));
    });

    $t->test('parse_size() G 단위', function () use ($t) {
        $t->eq(1073741824, parse_size('1G'));
    });

    $t->test('parse_size() K 단위', function () use ($t) {
        $t->eq(1024, parse_size('1K'));
    });

    $t->test('parse_size() 숫자만', function () use ($t) {
        $t->eq(500, parse_size('500'));
    });

    $t->test('is_cli() CLI 환경', function () use ($t) {
        $t->ok(is_cli());
    });
};
