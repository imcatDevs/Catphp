<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Debug — 디버그 유틸
 *
 * 변수 덤프, 타이머, 메모리 측정, 쿼리 로그.
 *
 * 사용법:
 *   debug()->dump($var);
 *   debug()->dd($var);                    // dump & die
 *   debug()->timer('db');                 // 타이머 시작
 *   debug()->timerEnd('db');              // 경과 시간 (ms)
 *   debug()->memory();                    // 현재 메모리 (사람 읽기 형식)
 *   debug()->log('query', $sql, 12.3);   // 쿼리 로그 기록
 *   debug()->getLogs();                   // 전체 로그
 *   debug()->bar();                       // HTML 디버그 바
 */
final class Debug
{
    private static ?self $instance = null;

    /** @var array<string, float> 타이머 */
    private array $timers = [];

    /** @var list<array{type:string, message:string, time:float, memory:int}> */
    private array $logs = [];

    /** @var float 앱 시작 시간 */
    private float $startTime;

    private function __construct()
    {
        $this->startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 변수 덤프 ──

    /** 변수 출력 (계속 실행) */
    public function dump(mixed ...$vars): self
    {
        foreach ($vars as $var) {
            if (PHP_SAPI === 'cli') {
                var_dump($var);
            } else {
                echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:12px;border-radius:8px;'
                   . 'font-family:monospace;font-size:13px;margin:8px;overflow-x:auto;">';
                $this->prettyPrint($var);
                echo '</pre>';
            }
        }
        return $this;
    }

    /** 변수 출력 후 종료 */
    public function dd(mixed ...$vars): never
    {
        $this->dump(...$vars);

        // 호출 위치 표시
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $trace = $traces[array_key_last($traces)] ?? [];
        $file = $trace['file'] ?? 'unknown';
        $line = $trace['line'] ?? 0;

        if (PHP_SAPI === 'cli') {
            echo "\n--- dd() at {$file}:{$line} ---\n";
        } else {
            echo '<div style="background:#313244;color:#a6adc8;padding:6px 12px;font-family:monospace;'
               . 'font-size:11px;border-radius:0 0 8px 8px;margin:0 8px 8px;">'
               . "dd() at {$file}:{$line}</div>";
        }

        exit(1);
    }

    // ── 타이머 ──

    /** 타이머 시작 */
    public function timer(string $label = 'default'): self
    {
        $this->timers[$label] = microtime(true);
        return $this;
    }

    /** 타이머 종료, 경과 시간 반환 (ms) */
    public function timerEnd(string $label = 'default'): float
    {
        $start = $this->timers[$label] ?? $this->startTime;
        $elapsed = (microtime(true) - $start) * 1000;
        unset($this->timers[$label]);

        $this->log('timer', "{$label}: {$elapsed}ms");
        return round($elapsed, 2);
    }

    /** 앱 시작부터 경과 시간 (ms) */
    public function elapsed(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    // ── 메모리 ──

    /** 현재 메모리 사용량 (사람 읽기 형식) */
    public function memory(): string
    {
        return $this->formatBytes(memory_get_usage(true));
    }

    /** 피크 메모리 */
    public function peakMemory(): string
    {
        return $this->formatBytes(memory_get_peak_usage(true));
    }

    /** 메모리 사용량 (바이트) */
    public function memoryUsage(): int
    {
        return memory_get_usage(true);
    }

    // ── 로그 ──

    /** 디버그 로그 기록 */
    public function log(string $type, string $message, float $duration = 0.0): self
    {
        $this->logs[] = [
            'type'     => $type,
            'message'  => $message,
            'time'     => $duration > 0 ? $duration : $this->elapsed(),
            'memory'   => memory_get_usage(true),
        ];
        return $this;
    }

    /** 모든 로그 반환 */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /** 로그 개수 */
    public function logCount(): int
    {
        return count($this->logs);
    }

    /** 로그 초기화 */
    public function clearLogs(): self
    {
        $this->logs = [];
        return $this;
    }

    // ── 디버그 바 ──

    /** HTML 디버그 바 (페이지 하단 삽입용) */
    public function bar(): string
    {
        // 프로덕션 환경에서는 디버그 바 비활성화 (정보 노출 방지)
        if (!(bool) \config('app.debug', false)) {
            return '';
        }

        if (PHP_SAPI === 'cli') {
            return $this->cliBar();
        }

        $elapsed = $this->elapsed();
        $memory = $this->memory();
        $peak = $this->peakMemory();
        $logCount = count($this->logs);
        $php = PHP_VERSION;

        $logsHtml = '';
        foreach ($this->logs as $i => $log) {
            $bg = $i % 2 === 0 ? '#313244' : '#2b2b3d';
            $typeColor = match ($log['type']) {
                'query'   => '#89b4fa',
                'timer'   => '#a6e3a1',
                'error'   => '#f38ba8',
                'warning' => '#fab387',
                default   => '#cdd6f4',
            };
            $logsHtml .= "<div style=\"padding:4px 8px;background:{$bg};font-size:12px;\">"
                . "<span style=\"color:{$typeColor};font-weight:bold;\">[{$log['type']}]</span> "
                . htmlspecialchars($log['message'], ENT_QUOTES, 'UTF-8')
                . " <span style=\"color:#6c7086;\">{$log['time']}ms | "
                . $this->formatBytes($log['memory']) . "</span></div>";
        }

        return <<<HTML
<div id="catphp-debug-bar" style="position:fixed;bottom:0;left:0;right:0;z-index:99999;
    font-family:monospace;background:#1e1e2e;color:#cdd6f4;border-top:2px solid #89b4fa;">
    <div style="display:flex;gap:16px;padding:6px 12px;font-size:12px;cursor:pointer;"
         onclick="document.getElementById('catphp-debug-detail').style.display=
         document.getElementById('catphp-debug-detail').style.display==='none'?'block':'none'">
        <span style="color:#89b4fa;font-weight:bold;">🐱 CatPHP</span>
        <span>⏱ {$elapsed}ms</span>
        <span>💾 {$memory}</span>
        <span>📊 Peak: {$peak}</span>
        <span>📝 Logs: {$logCount}</span>
        <span style="color:#6c7086;">PHP {$php}</span>
    </div>
    <div id="catphp-debug-detail" style="display:none;max-height:300px;overflow-y:auto;
         border-top:1px solid #313244;">{$logsHtml}</div>
</div>
HTML;
    }

    /** CLI 디버그 출력 */
    private function cliBar(): string
    {
        $lines = [];
        $lines[] = "── CatPHP Debug ──";
        $lines[] = "  Time: {$this->elapsed()}ms | Memory: {$this->memory()} | Peak: {$this->peakMemory()}";
        $lines[] = "  Logs: " . count($this->logs);
        foreach ($this->logs as $log) {
            $lines[] = "    [{$log['type']}] {$log['message']} ({$log['time']}ms)";
        }
        return implode("\n", $lines);
    }

    // ── 유틸 ──

    /** 호출 스택 출력 */
    public function trace(int $limit = 10): self
    {
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        array_shift($traces); // 자기 자신 제거

        if (PHP_SAPI === 'cli') {
            echo "── Stack Trace ──\n";
            foreach ($traces as $i => $t) {
                $file = $t['file'] ?? 'internal';
                $line = $t['line'] ?? 0;
                $func = ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '');
                echo "  #{$i} {$file}:{$line} → {$func}()\n";
            }
        } else {
            echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:12px;border-radius:8px;'
               . 'font-family:monospace;font-size:12px;margin:8px;">';
            echo "<b style=\"color:#89b4fa;\">Stack Trace</b>\n";
            foreach ($traces as $i => $t) {
                $file = $t['file'] ?? 'internal';
                $line = $t['line'] ?? 0;
                $func = ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '');
                echo "  <span style=\"color:#6c7086;\">#{$i}</span> {$file}:{$line} → "
                   . "<span style=\"color:#a6e3a1;\">{$func}()</span>\n";
            }
            echo '</pre>';
        }

        return $this;
    }

    /** 콜백 실행 시간 측정 */
    public function measure(string $label, callable $callback): mixed
    {
        $this->timer($label);
        $result = $callback();
        $ms = $this->timerEnd($label);
        return $result;
    }

    // ── 내부 ──

    /** 바이트 → 사람 읽기 형식 */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $factor < 3) {
            $size /= 1024;
            $factor++;
        }
        return round($size, 2) . ' ' . $units[$factor];
    }

    /** 예쁜 변수 출력 */
    private function prettyPrint(mixed $var, int $depth = 0): void
    {
        $indent = str_repeat('  ', $depth);

        if ($var === null) {
            echo "<span style=\"color:#f38ba8;\">null</span>";
            return;
        }
        if (is_bool($var)) {
            $v = $var ? 'true' : 'false';
            echo "<span style=\"color:#fab387;\">{$v}</span>";
            return;
        }
        if (is_int($var) || is_float($var)) {
            echo "<span style=\"color:#89b4fa;\">{$var}</span>";
            return;
        }
        if (is_string($var)) {
            $escaped = htmlspecialchars($var, ENT_QUOTES, 'UTF-8');
            $len = mb_strlen($var);
            echo "<span style=\"color:#a6e3a1;\">\"{$escaped}\"</span>"
               . " <span style=\"color:#6c7086;\">(len={$len})</span>";
            return;
        }
        if (is_array($var)) {
            $count = count($var);
            if ($count === 0) {
                echo "<span style=\"color:#6c7086;\">[] (empty)</span>";
                return;
            }
            if ($depth > 4) {
                echo "<span style=\"color:#6c7086;\">[...] ({$count})</span>";
                return;
            }
            echo "array({$count}) [\n";
            foreach ($var as $k => $v) {
                echo $indent . "  ";
                if (is_string($k)) {
                    echo "<span style=\"color:#f9e2af;\">\"{$k}\"</span> => ";
                } else {
                    echo "<span style=\"color:#6c7086;\">{$k}</span> => ";
                }
                $this->prettyPrint($v, $depth + 1);
                echo "\n";
            }
            echo $indent . "]";
            return;
        }
        if (is_object($var)) {
            $class = htmlspecialchars(get_class($var), ENT_QUOTES, 'UTF-8');
            echo "<span style=\"color:#cba6f7;\">{$class}</span> "
               . htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8');
            return;
        }
        echo (string) $var;
    }
}
