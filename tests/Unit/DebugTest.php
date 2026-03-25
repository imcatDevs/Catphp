<?php declare(strict_types=1);

/** Debug 타이머/메모리/로그 테스트 */
return function (TestRunner $t): void {
    $t->suite('Debug — timer / memory / log / getLogs / clearLogs');

    $t->test('timer() + timerEnd() 경과 시간', function () use ($t) {
        debug()->timer('test_timer');
        usleep(10000); // 10ms
        $elapsed = debug()->timerEnd('test_timer');
        $t->ok($elapsed > 0);
        $t->isType('float', $elapsed);
    });

    $t->test('elapsed() 앱 시작 이후 경과', function () use ($t) {
        $elapsed = debug()->elapsed();
        $t->ok($elapsed > 0);
        $t->isType('float', $elapsed);
    });

    $t->test('memory() 사람 읽기 형식', function () use ($t) {
        $mem = debug()->memory();
        $t->ok(is_string($mem));
        $t->ok(strlen($mem) > 0);
        // KB, MB 등의 단위 포함
        $t->ok((bool) preg_match('/\d+(\.\d+)?\s*(B|KB|MB|GB)/', $mem));
    });

    $t->test('peakMemory() 피크', function () use ($t) {
        $peak = debug()->peakMemory();
        $t->ok(is_string($peak));
        $t->ok(strlen($peak) > 0);
    });

    $t->test('memoryUsage() 바이트', function () use ($t) {
        $bytes = debug()->memoryUsage();
        $t->isType('int', $bytes);
        $t->ok($bytes > 0);
    });

    $t->test('log() 디버그 로그 기록', function () use ($t) {
        debug()->clearLogs();
        debug()->log('query', 'SELECT * FROM users', 1.5);
        debug()->log('info', '테스트 메시지');
        $logs = debug()->getLogs();
        $t->count(2, $logs);
        $t->eq('query', $logs[0]['type']);
        $t->eq('SELECT * FROM users', $logs[0]['message']);
        $t->eq(1.5, $logs[0]['time']);
        $t->eq('info', $logs[1]['type']);
    });

    $t->test('logCount() 개수', function () use ($t) {
        debug()->clearLogs();
        debug()->log('a', 'msg1');
        debug()->log('b', 'msg2');
        debug()->log('c', 'msg3');
        $t->eq(3, debug()->logCount());
    });

    $t->test('clearLogs() 초기화', function () use ($t) {
        debug()->clearLogs();
        $t->eq(0, debug()->logCount());
        $t->count(0, debug()->getLogs());
    });

    $t->test('log() 메모리 포함', function () use ($t) {
        debug()->clearLogs();
        debug()->log('test', 'memory check');
        $logs = debug()->getLogs();
        $t->hasKey($logs[0], 'memory');
        $t->ok($logs[0]['memory'] > 0);
    });

    $t->test('timer() 체이닝', function () use ($t) {
        $result = debug()->timer('chain_test');
        $t->isType('Cat\\Debug', $result);
        debug()->timerEnd('chain_test');
    });

    $t->test('timerEnd() 미시작 타이머 — 앱 시작부터 측정', function () use ($t) {
        $elapsed = debug()->timerEnd('never_started');
        $t->ok($elapsed > 0);
    });

    // 정리
    debug()->clearLogs();
};
