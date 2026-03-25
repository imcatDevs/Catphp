<?php declare(strict_types=1);

/** Cat\Event 테스트 */
return function (TestRunner $t): void {
    $t->suite('Event — on / emit / once / off / hasListeners');

    $t->test('on + emit 기본 동작', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $called = false;
        $e->on('test.basic', function () use (&$called) { $called = true; });
        $e->emit('test.basic');
        $t->ok($called);
        $e->off('test.basic');
    });

    $t->test('emit 인자 전달', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $received = null;
        $e->on('test.args', function (string $msg) use (&$received) { $received = $msg; });
        $e->emit('test.args', 'hello');
        $t->eq('hello', $received);
        $e->off('test.args');
    });

    $t->test('once 1회만 실행', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $count = 0;
        $e->once('test.once', function () use (&$count) { $count++; });
        $e->emit('test.once');
        $e->emit('test.once');
        $t->eq(1, $count);
    });

    $t->test('우선순위 높은 리스너 먼저 실행', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $order = [];
        $e->on('test.priority', function () use (&$order) { $order[] = 'low'; }, 0);
        $e->on('test.priority', function () use (&$order) { $order[] = 'high'; }, 10);
        $e->emit('test.priority');
        $t->eq('high', $order[0]);
        $t->eq('low', $order[1]);
        $e->off('test.priority');
    });

    $t->test('false 반환 시 전파 중단', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $reached = false;
        $e->on('test.stop', function () { return false; }, 10);
        $e->on('test.stop', function () use (&$reached) { $reached = true; }, 0);
        $e->emit('test.stop');
        $t->notOk($reached);
        $e->off('test.stop');
    });

    $t->test('off() ID 기반 제거', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $called = false;
        $id = $e->on('test.offid', function () use (&$called) { $called = true; });
        $e->off('test.offid', $id);
        $e->emit('test.offid');
        $t->notOk($called);
    });

    $t->test('hasListeners()', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $t->notOk($e->hasListeners('test.noone'));
        $e->on('test.has', function () {});
        $t->ok($e->hasListeners('test.has'));
        $e->off('test.has');
    });

    $t->test('미등록 이벤트 emit 안전', function () use ($t) {
        $e = \Cat\Event::getInstance();
        $e->emit('nonexistent.event'); // 예외 없이 통과
        $t->ok(true);
    });
};
