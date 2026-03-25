<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Log — 로거
 *
 * @config array{
 *     path: string,    // 로그 디렉토리 경로
 *     level?: string,  // 최소 로그 레벨 (debug|info|warn|error)
 * } log  → config('log.path')
 */

enum LogLevel: int
{
    case DEBUG = 0;
    case INFO  = 1;
    case WARN  = 2;
    case ERROR = 3;
}

final class Log
{
    private static ?self $instance = null;

    private function __construct(
        private readonly string $path,
        private readonly LogLevel $minLevel,
    ) {}

    public static function getInstance(): self
    {
        $levelStr = strtolower((string) (\config('log.level') ?? 'debug'));
        $level = match ($levelStr) {
            'info'  => LogLevel::INFO,
            'warn'  => LogLevel::WARN,
            'error' => LogLevel::ERROR,
            default => LogLevel::DEBUG,
        };

        return self::$instance ??= new self(
            path: \config('log.path') ?? __DIR__ . '/../storage/logs',
            minLevel: $level,
        );
    }

    /** 로그 디렉토리 보장 */
    private function ensureDir(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /** 일별 로그 파일 경로 */
    private function filePath(): string
    {
        return $this->path . '/' . date('Y-m-d') . '.log';
    }

    /** 로그 기록 */
    private function write(LogLevel $level, string $message, array $context = []): void
    {
        if ($level->value < $this->minLevel->value) {
            return;
        }

        $this->ensureDir();

        $timestamp = date('Y-m-d H:i:s');
        $levelName = $level->name;
        // 로그 인젝션 방어: 메시지 내 개행/제어문자 제거
        $message = str_replace(["\r", "\n", "\0"], ' ', $message);
        $line = "[{$timestamp}] [{$levelName}] {$message}";

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;

        file_put_contents($this->filePath(), $line, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write(LogLevel::INFO, $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->write(LogLevel::WARN, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(LogLevel::ERROR, $message, $context);
    }

    /** 로그 파일 마지막 N줄 읽기 (8KB 버퍼 역방향 읽기) */
    public function tail(int $lines = 20): string
    {
        $file = $this->filePath();
        if (!is_file($file)) {
            return '';
        }

        $fp = fopen($file, 'r');
        if ($fp === false) {
            return '';
        }

        fseek($fp, 0, SEEK_END);
        $fileSize = ftell($fp);

        if ($fileSize === 0) {
            fclose($fp);
            return '';
        }

        $bufSize = 8192;
        $remaining = '';
        $result = [];
        $offset = $fileSize;

        while (count($result) < $lines && $offset > 0) {
            $readSize = min($bufSize, $offset);
            $offset -= $readSize;
            fseek($fp, $offset);
            $buf = fread($fp, $readSize) . $remaining;
            $remaining = '';

            $parts = explode("\n", $buf);
            $remaining = array_shift($parts);

            for ($i = count($parts) - 1; $i >= 0 && count($result) < $lines; $i--) {
                if ($parts[$i] !== '') {
                    array_unshift($result, $parts[$i]);
                }
            }
        }

        if ($remaining !== '' && count($result) < $lines) {
            array_unshift($result, $remaining);
        }

        fclose($fp);
        return implode(PHP_EOL, $result);
    }

    /** N일 이전 로그 파일 삭제 (로테이션) */
    public function clean(int $days = 30): int
    {
        if (!is_dir($this->path)) {
            return 0;
        }

        $files = glob($this->path . '/*.log');
        if ($files === false) {
            return 0;
        }

        $threshold = time() - ($days * 86400);
        $deleted = 0;

        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** 현재 로그 파일 삭제 */
    public function clear(): bool
    {
        $file = $this->filePath();
        return is_file($file) && unlink($file);
    }
}
