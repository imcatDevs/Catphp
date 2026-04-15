<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Cli — CLI 프레임워크
 *
 * 명령어 등록/실행/헬퍼.
 */
final class Cli
{
    private static ?self $instance = null;

    /** @var array<string, array{description: string, handler: callable}> */
    private array $commands = [];

    private string $groupPrefix = '';

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 명령어 등록 */
    public function command(string $name, string $description, callable $handler): self
    {
        $fullName = $this->groupPrefix !== '' ? $this->groupPrefix . ':' . $name : $name;
        $this->commands[$fullName] = ['description' => $description, 'handler' => $handler];
        return $this;
    }

    /** 명령어 그룹 (네임스페이스 prefix 자동 적용) */
    public function group(string $prefix, callable $callback): self
    {
        $prevPrefix = $this->groupPrefix;
        $this->groupPrefix = $prevPrefix !== '' ? $prevPrefix . ':' . $prefix : $prefix;
        $callback();
        $this->groupPrefix = $prevPrefix;
        return $this;
    }

    /** $argv 기반 자동 라우팅 */
    public function run(): void
    {
        global $argv;
        $args = $argv ?? [];
        $command = $args[1] ?? 'help';

        if ($command === 'help') {
            $this->showHelp($args[2] ?? null);
            return;
        }

        if (!isset($this->commands[$command])) {
            $this->error("알 수 없는 명령어: {$command}");
            $this->info("사용 가능한 명령어: php cli.php help");
            return;
        }

        ($this->commands[$command]['handler'])();
    }

    /** 도움말 출력 */
    private function showHelp(?string $command = null): void
    {
        if ($command !== null && isset($this->commands[$command])) {
            $this->info("  {$command} — {$this->commands[$command]['description']}");
            return;
        }

        $this->info('CatPHP CLI');
        $this->newLine();

        $grouped = [];
        foreach ($this->commands as $name => $cmd) {
            $prefix = str_contains($name, ':') ? explode(':', $name)[0] : '_default';
            $grouped[$prefix][$name] = $cmd['description'];
        }

        ksort($grouped);
        foreach ($grouped as $group => $cmds) {
            if ($group !== '_default') {
                echo "  \033[33m{$group}\033[0m" . PHP_EOL;
            }
            foreach ($cmds as $name => $desc) {
                echo "    \033[32m{$name}\033[0m  {$desc}" . PHP_EOL;
            }
        }
    }

    // ── 인자/옵션 파싱 ──

    /** 위치 인자 (0-indexed) */
    public function arg(int $index): ?string
    {
        global $argv;
        $args = $argv ?? [];
        return $args[$index + 2] ?? null; // +2: script, command
    }

    /** 옵션 파싱 (--key=value 또는 --flag) */
    public function option(string $name, mixed $default = null): mixed
    {
        global $argv;
        $args = $argv ?? [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen("--{$name}="));
            }
            if ($arg === "--{$name}") {
                return true;
            }
        }
        return $default;
    }

    // ── 사용자 입력 ──

    /** 확인 질문 (y/n). default=true이면 Enter 시 Y, default=false이면 Enter 시 N */
    public function confirm(string $question, ?bool $default = null): bool
    {
        $hint = match ($default) {
            true  => 'Y/n',
            false => 'y/N',
            null  => 'y/n',
        };
        echo "\033[33m{$question} ({$hint}): \033[0m";
        $answer = strtolower(trim(fgets(STDIN) ?: ''));
        if ($answer === '' && $default !== null) {
            return $default;
        }
        return in_array($answer, ['y', 'yes'], true);
    }

    /** 텍스트 입력 프롬프트 */
    public function prompt(string $question, ?string $default = null): string
    {
        $suffix = $default !== null ? " [{$default}]" : '';
        echo "\033[33m{$question}{$suffix}: \033[0m";
        $answer = trim(fgets(STDIN) ?: '');
        return $answer !== '' ? $answer : ($default ?? '');
    }

    /** 선택지 */
    public function choice(string $question, array $options): ?string
    {
        echo "\033[33m{$question}\033[0m" . PHP_EOL;
        foreach ($options as $i => $option) {
            echo "  [\033[32m{$i}\033[0m] {$option}" . PHP_EOL;
        }
        echo "선택: ";
        $answer = trim(fgets(STDIN) ?: '');
        return $options[$answer] ?? null;
    }

    // ── 출력 헬퍼 ──

    public function info(string $message): void
    {
        echo "\033[34m{$message}\033[0m" . PHP_EOL;
    }

    public function success(string $message): void
    {
        echo "\033[32m✓ {$message}\033[0m" . PHP_EOL;
    }

    public function warn(string $message): void
    {
        echo "\033[33m⚠ {$message}\033[0m" . PHP_EOL;
    }

    public function error(string $message): void
    {
        echo "\033[31m✗ {$message}\033[0m" . PHP_EOL;
    }

    /** 테이블 출력 */
    public function table(array $headers, array $rows): void
    {
        // 열 너비 계산 (mb_strwidth: 한글/일본어 등 동아시아 문자 2칸 너비 반영)
        $widths = array_map(fn(string $h) => mb_strwidth($h), $headers);
        foreach ($rows as $row) {
            $values = is_array($row) ? array_values($row) : [$row];
            foreach ($values as $i => $val) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strwidth((string) $val));
            }
        }

        // 헤더 출력
        $line = '+';
        foreach ($widths as $w) {
            $line .= str_repeat('-', $w + 2) . '+';
        }
        echo $line . PHP_EOL;

        echo '|';
        foreach ($headers as $i => $h) {
            echo ' ' . $this->mbStrPad($h, $widths[$i]) . ' |';
        }
        echo PHP_EOL . $line . PHP_EOL;

        // 행 출력
        foreach ($rows as $row) {
            $values = is_array($row) ? array_values($row) : [$row];
            echo '|';
            foreach ($values as $i => $val) {
                echo ' ' . $this->mbStrPad((string) $val, $widths[$i] ?? 0) . ' |';
            }
            echo PHP_EOL;
        }
        echo $line . PHP_EOL;
    }

    /** 유니코드 너비 기반 패딩 (mb_strwidth 사용) */
    private function mbStrPad(string $str, int $width, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        $strWidth = mb_strwidth($str);
        $diff = $width - $strWidth;
        if ($diff <= 0) {
            return $str;
        }
        return match ($type) {
            STR_PAD_LEFT  => str_repeat($pad, $diff) . $str,
            STR_PAD_BOTH  => str_repeat($pad, (int) floor($diff / 2)) . $str . str_repeat($pad, (int) ceil($diff / 2)),
            default       => $str . str_repeat($pad, $diff),
        };
    }

    /** 프로그레스 바 */
    public function progress(int $current, int $total, int $width = 40): void
    {
        $percent = $total > 0 ? (int) ($current / $total * 100) : 0;
        $filled = (int) ($width * $current / max($total, 1));
        $bar = str_repeat('█', $filled) . str_repeat('░', $width - $filled);
        echo "\r[{$bar}] {$percent}% ({$current}/{$total})";
        if ($current >= $total) {
            echo PHP_EOL;
        }
    }

    public function newLine(int $count = 1): void
    {
        echo str_repeat(PHP_EOL, $count);
    }

    public function hr(string $char = '─', int $width = 60): void
    {
        echo str_repeat($char, $width) . PHP_EOL;
    }
}
