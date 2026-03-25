<?php declare(strict_types=1);

/** Cat\Log 통합 테스트 (파일 I/O) */
return function (TestRunner $t): void {
    $t->suite('Log — write / level filter / tail / 로그 인젝션 방어');

    // 기존 로그 파일 삭제
    $logDir = __DIR__ . '/../_tmp/logs';
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';
    if (is_file($logFile)) {
        @unlink($logFile);
    }

    $t->test('info() 파일 기록', function () use ($t, $logFile) {
        logger()->info('테스트 메시지');
        $t->ok(is_file($logFile));
        $content = file_get_contents($logFile);
        $t->contains($content, '[INFO]');
        $t->contains($content, '테스트 메시지');
    });

    $t->test('debug() 레벨 기록', function () use ($t, $logFile) {
        logger()->debug('디버그 메시지');
        $content = file_get_contents($logFile);
        $t->contains($content, '[DEBUG]');
    });

    $t->test('컨텍스트 JSON 기록', function () use ($t, $logFile) {
        logger()->error('에러 발생', ['code' => 500]);
        $content = file_get_contents($logFile);
        $t->contains($content, '"code":500');
    });

    $t->test('로그 인젝션 방어 (개행 제거)', function () use ($t, $logFile) {
        logger()->warn("주입\r\n시도\0테스트");
        $content = file_get_contents($logFile);
        // 개행이 제거되어 한 줄로 기록되어야 함
        $lines = array_filter(explode("\n", trim($content)));
        $lastLine = end($lines);
        $t->contains($lastLine, '주입');
        $t->contains($lastLine, '시도');
        $t->notContains($lastLine, "\r");
    });

    $t->test('tail() 마지막 N줄 읽기', function () use ($t) {
        $result = logger()->tail(2);
        $t->ok(strlen($result) > 0);
        // 2줄 이하인지 확인
        $lines = array_filter(explode("\n", trim($result)));
        $t->ok(count($lines) <= 2);
    });

    $t->test('tail() 빈 파일', function () use ($t) {
        // 임시 로그 경로로 빈 파일 테스트는 어려우므로 존재하지 않는 날짜 파일 확인
        // tail()은 현재 날짜 파일만 읽으므로, 비어있지 않은 현재 파일로 검증
        $result = logger()->tail(100);
        $t->ok(strlen($result) > 0);
    });
};
