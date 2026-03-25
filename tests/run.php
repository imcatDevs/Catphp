<?php declare(strict_types=1);

/**
 * CatPHP 테스트 실행기
 *
 * 사용법:
 *   php tests/run.php              # 전체 테스트
 *   php tests/run.php unit         # 단위 테스트만
 *   php tests/run.php integration  # 통합 테스트만
 */

require __DIR__ . '/bootstrap.php';

$mode = $argv[1] ?? 'all';
$t = new TestRunner();

echo "═══ CatPHP 테스트 ═══\n";

// 단위 테스트
if ($mode === 'all' || $mode === 'unit') {
    echo "\n▶ 단위 테스트\n";
    $unitDir = __DIR__ . '/Unit';
    if (is_dir($unitDir)) {
        foreach (glob($unitDir . '/*Test.php') as $file) {
            $testFn = require $file;
            if (is_callable($testFn)) {
                $testFn($t);
            }
        }
    }
}

// 통합 테스트
if ($mode === 'all' || $mode === 'integration') {
    echo "\n▶ 통합 테스트\n";
    $intDir = __DIR__ . '/Integration';
    if (is_dir($intDir)) {
        foreach (glob($intDir . '/*Test.php') as $file) {
            $testFn = require $file;
            if (is_callable($testFn)) {
                $testFn($t);
            }
        }
    }
}

// 결과 리포트
$exitCode = $t->report();

// 임시 파일 정리
cleanTestTmp();

exit($exitCode);
