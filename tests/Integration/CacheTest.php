<?php declare(strict_types=1);

/** Cat\Cache 통합 테스트 (파일 I/O) */
return function (TestRunner $t): void {
    $t->suite('Cache — set / get / del / has / clear / remember');

    cache()->clear();

    $t->test('set + get 라운드트립', function () use ($t) {
        cache()->set('test_key', 'hello');
        $t->eq('hello', cache()->get('test_key'));
    });

    $t->test('get 없는 키 → 기본값', function () use ($t) {
        $t->eq('default', cache()->get('missing_key', 'default'));
    });

    $t->test('get 없는 키 → null', function () use ($t) {
        $t->isNull(cache()->get('missing_key2'));
    });

    $t->test('has() 존재 확인', function () use ($t) {
        cache()->set('exist_key', 'val');
        $t->ok(cache()->has('exist_key'));
        $t->notOk(cache()->has('no_exist_key'));
    });

    $t->test('del() 삭제', function () use ($t) {
        cache()->set('del_key', 'val');
        $t->ok(cache()->del('del_key'));
        $t->isNull(cache()->get('del_key'));
    });

    $t->test('null 값 캐싱 (센티넬 패턴)', function () use ($t) {
        cache()->set('null_key', null);
        $t->ok(cache()->has('null_key'));
        $t->isNull(cache()->get('null_key'));
    });

    $t->test('remember() 콜백 실행', function () use ($t) {
        cache()->del('rem_key');
        $count = 0;
        $val = cache()->remember('rem_key', function () use (&$count) {
            $count++;
            return 'computed';
        });
        $t->eq('computed', $val);
        $t->eq(1, $count);
        // 두 번째 호출은 캐시 히트
        cache()->remember('rem_key', function () use (&$count) { $count++; return 'x'; });
        $t->eq(1, $count);
    });

    $t->test('clear() 전체 삭제', function () use ($t) {
        cache()->set('c1', 'a');
        cache()->set('c2', 'b');
        cache()->clear();
        $t->isNull(cache()->get('c1'));
        $t->isNull(cache()->get('c2'));
    });
};
