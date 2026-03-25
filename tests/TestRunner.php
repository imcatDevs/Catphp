<?php declare(strict_types=1);

/**
 * CatPHP 경량 테스트 러너 — 외부 의존성 없음
 *
 * 사용법:
 *   $t = new TestRunner();
 *   $t->test('설명', function() use ($t) { $t->eq(1, 1); });
 *   $t->report();
 */
final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private int $total = 0;
    /** @var array<string> */
    private array $failures = [];
    private string $currentTest = '';
    private string $currentSuite = '';

    /** 테스트 스위트 시작 */
    public function suite(string $name): void
    {
        $this->currentSuite = $name;
        echo "\n  ── {$name} ──\n";
    }

    /** 개별 테스트 실행 */
    public function test(string $description, callable $fn): void
    {
        $this->currentTest = $description;
        $this->total++;
        try {
            $fn();
            $this->passed++;
            echo "    ✓ {$description}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $label = $this->currentSuite ? "[{$this->currentSuite}] {$description}" : $description;
            $this->failures[] = "✗ {$label}: {$e->getMessage()}";
            echo "    ✗ {$description} — {$e->getMessage()}\n";
        }
    }

    // ── Assert 메서드 ──

    /** 동등 비교 */
    public function eq(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            $e = $this->format($expected);
            $a = $this->format($actual);
            throw new \RuntimeException($msg ?: "기대: {$e}, 실제: {$a}");
        }
    }

    /** 불일치 비교 */
    public function neq(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected === $actual) {
            throw new \RuntimeException($msg ?: "값이 같으면 안 됨: {$this->format($actual)}");
        }
    }

    /** true 검증 */
    public function ok(mixed $value, string $msg = ''): void
    {
        if (!$value) {
            throw new \RuntimeException($msg ?: "true 기대, 실제: {$this->format($value)}");
        }
    }

    /** false 검증 */
    public function notOk(mixed $value, string $msg = ''): void
    {
        if ($value) {
            throw new \RuntimeException($msg ?: "false 기대, 실제: {$this->format($value)}");
        }
    }

    /** null 검증 */
    public function isNull(mixed $value, string $msg = ''): void
    {
        if ($value !== null) {
            throw new \RuntimeException($msg ?: "null 기대, 실제: {$this->format($value)}");
        }
    }

    /** not null 검증 */
    public function notNull(mixed $value, string $msg = ''): void
    {
        if ($value === null) {
            throw new \RuntimeException($msg ?: "null이 아닌 값 기대");
        }
    }

    /** 문자열 포함 검증 */
    public function contains(string $haystack, string $needle, string $msg = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException($msg ?: "'{$needle}' 포함 기대");
        }
    }

    /** 문자열 미포함 검증 */
    public function notContains(string $haystack, string $needle, string $msg = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new \RuntimeException($msg ?: "'{$needle}' 미포함 기대");
        }
    }

    /** 배열 키 존재 검증 */
    public function hasKey(array $array, string|int $key, string $msg = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new \RuntimeException($msg ?: "키 '{$key}' 존재 기대");
        }
    }

    /** 배열 수 검증 */
    public function count(int $expected, array|countable $actual, string $msg = ''): void
    {
        $c = count($actual);
        if ($c !== $expected) {
            throw new \RuntimeException($msg ?: "개수 기대: {$expected}, 실제: {$c}");
        }
    }

    /** 예외 발생 검증 */
    public function throws(callable $fn, string $exceptionClass = \Throwable::class, string $msg = ''): void
    {
        try {
            $fn();
            throw new \RuntimeException($msg ?: "{$exceptionClass} 예외 기대했으나 발생하지 않음");
        } catch (\Throwable $e) {
            if (!($e instanceof $exceptionClass)) {
                throw new \RuntimeException($msg ?: "{$exceptionClass} 기대, 실제: " . get_class($e) . " — {$e->getMessage()}");
            }
        }
    }

    /** 타입 검증 */
    public function isType(string $type, mixed $value, string $msg = ''): void
    {
        $actual = get_debug_type($value);
        if ($actual !== $type) {
            throw new \RuntimeException($msg ?: "타입 기대: {$type}, 실제: {$actual}");
        }
    }

    // ── 결과 리포트 ──

    public function report(): int
    {
        echo "\n════════════════════════════════════════\n";
        echo "테스트: {$this->total}개  ";
        echo "✓ {$this->passed}개  ";
        echo "✗ {$this->failed}개\n";

        if (!empty($this->failures)) {
            echo "\n실패 목록:\n";
            foreach ($this->failures as $f) {
                echo "  {$f}\n";
            }
        }

        echo "════════════════════════════════════════\n";
        return $this->failed > 0 ? 1 : 0;
    }

    public function passed(): int { return $this->passed; }
    public function failed(): int { return $this->failed; }

    private function format(mixed $value): string
    {
        if (is_null($value)) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_string($value)) return "'" . mb_substr($value, 0, 80) . "'";
        if (is_array($value)) return 'array(' . count($value) . ')';
        if (is_object($value)) return get_class($value);
        return (string) $value;
    }
}
