<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Schedule — 크론 스케줄러
 *
 * CLI 기반 작업 예약. 시스템 크론탭에 1분 간격 등록 후 사용.
 * crontab: * * * * * php /path/to/cli.php schedule:run
 *
 * 사용법:
 *   schedule()->command('cache:clear')->daily();
 *   schedule()->call(fn() => logger()->info('heartbeat'))->everyMinute();
 *   schedule()->command('log:rotate')->weeklyOn(1, '03:00'); // 월요일 3시
 */
final class Schedule
{
    private static ?self $instance = null;

    /** @var array<int, array{type: string, value: mixed, expression: string, description: string, withoutOverlapping: bool}> */
    private array $tasks = [];

    /** 현재 편집 중인 태스크 인덱스 */
    private int $currentIndex = -1;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 태스크 등록 ──

    /** CLI 명령어 예약 */
    public function command(string $command): self
    {
        $this->tasks[] = [
            'type'               => 'command',
            'value'              => $command,
            'expression'         => '* * * * *',
            'description'        => $command,
            'withoutOverlapping' => false,
        ];
        $this->currentIndex = count($this->tasks) - 1;
        return $this;
    }

    /** 콜백 함수 예약 */
    public function call(callable $callback, string $description = ''): self
    {
        $this->tasks[] = [
            'type'               => 'callback',
            'value'              => $callback,
            'expression'         => '* * * * *',
            'description'        => $description ?: 'Closure',
            'withoutOverlapping' => false,
        ];
        $this->currentIndex = count($this->tasks) - 1;
        return $this;
    }

    // ── 스케줄 표현식 ──

    /** 원시 cron 표현식 */
    public function cron(string $expression): self
    {
        $this->current()['expression'] = $expression;
        return $this;
    }

    /** 매분 */
    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    /** N분마다 */
    public function everyMinutes(int $n): self
    {
        return $this->cron("*/{$n} * * * *");
    }

    /** 5분마다 */
    public function everyFiveMinutes(): self
    {
        return $this->everyMinutes(5);
    }

    /** 15분마다 */
    public function everyFifteenMinutes(): self
    {
        return $this->everyMinutes(15);
    }

    /** 30분마다 */
    public function everyThirtyMinutes(): self
    {
        return $this->everyMinutes(30);
    }

    /** 매시간 */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    /** 매시간 N분에 */
    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    /** 매일 자정 */
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /** 매일 지정 시각 */
    public function dailyAt(string $time): self
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return $this->cron("{$m} {$h} * * *");
    }

    /** 매주 일요일 자정 */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /** 매주 지정 요일·시각 (0=일, 1=월, ..., 6=토) */
    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): self
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return $this->cron("{$m} {$h} * * {$dayOfWeek}");
    }

    /** 매월 1일 자정 */
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /** 매월 지정일·시각 */
    public function monthlyOn(int $day, string $time = '00:00'): self
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return $this->cron("{$m} {$h} {$day} * *");
    }

    // ── 옵션 ──

    /** 설명 */
    public function description(string $desc): self
    {
        $this->current()['description'] = $desc;
        return $this;
    }

    /** 중복 실행 방지 */
    public function withoutOverlapping(): self
    {
        $this->current()['withoutOverlapping'] = true;
        return $this;
    }

    // ── 실행 ──

    /** 현재 시각 기준 실행 대상 실행 */
    public function run(): int
    {
        $executed = 0;
        $now = new \DateTimeImmutable();

        foreach ($this->tasks as $task) {
            if (!$this->isDue($task['expression'], $now)) {
                continue;
            }

            if ($task['withoutOverlapping'] && !$this->acquireLock($task['description'])) {
                continue;
            }

            try {
                if ($task['type'] === 'command') {
                    // CLI 명령어 실행 (인수 분리 — 옵션 정상 전달)
                    $cmd = $task['value'];
                    $parts = preg_split('/\s+/', $cmd) ?: [$cmd];
                    $escaped = implode(' ', array_map('escapeshellarg', $parts));
                    passthru("php " . escapeshellarg(__DIR__ . '/../cli.php') . " " . $escaped);
                } else {
                    // 콜백 실행
                    ($task['value'])();
                }
                $executed++;
            } catch (\Throwable $e) {
                if (class_exists(\Cat\Log::class)) {
                    cat('Log')->error("Schedule 실패: {$task['description']}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            } finally {
                if ($task['withoutOverlapping']) {
                    $this->releaseLock($task['description']);
                }
            }
        }

        return $executed;
    }

    /** 등록된 태스크 목록 */
    public function list(): array
    {
        return array_map(fn(array $t) => [
            'expression'  => $t['expression'],
            'type'        => $t['type'],
            'description' => $t['description'],
        ], $this->tasks);
    }

    // ── cron 표현식 매칭 ──

    private function isDue(string $expression, \DateTimeImmutable $now): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        return $this->matchField($minute, (int) $now->format('i'))
            && $this->matchField($hour, (int) $now->format('G'))
            && $this->matchField($day, (int) $now->format('j'))
            && $this->matchField($month, (int) $now->format('n'))
            && $this->matchField($weekday, (int) $now->format('w'));
    }

    private function matchField(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        // 콤마 분리 (1,5,15)
        foreach (explode(',', $field) as $part) {
            $part = trim($part);

            // 범위 (1-5)
            if (str_contains($part, '-')) {
                [$from, $to] = explode('-', $part, 2);
                if ($value >= (int) $from && $value <= (int) $to) {
                    return true;
                }
                continue;
            }

            // 스텝 (*/5 또는 10-20/3)
            if (str_contains($part, '/')) {
                [$base, $step] = explode('/', $part, 2);
                $step = (int) $step;
                if ($step <= 0) {
                    continue;
                }
                // 범위 기반 스텝: 10-20/3 → 10부터 3 간격
                if ($base !== '*' && str_contains($base, '-')) {
                    [$from, $to] = explode('-', $base, 2);
                    $from = (int) $from;
                    $to = (int) $to;
                    if ($value >= $from && $value <= $to && ($value - $from) % $step === 0) {
                        return true;
                    }
                } elseif ($base === '*') {
                    // */5 → 0부터 5 간격
                    if ($value % $step === 0) {
                        return true;
                    }
                } else {
                    // 단일 값 기반 스텝: 5/3 → 5부터 3 간격
                    $from = (int) $base;
                    if ($value >= $from && ($value - $from) % $step === 0) {
                        return true;
                    }
                }
                continue;
            }

            // 정확 매칭
            if ((int) $part === $value) {
                return true;
            }
        }

        return false;
    }

    // ── 중복 방지 락 ──

    private function lockPath(string $name): string
    {
        $dir = (string) config('cache.path', __DIR__ . '/../storage/cache');
        return $dir . '/schedule_' . md5($name) . '.lock';
    }

    /** @var array<string, resource> flock 핸들 보관 */
    private array $lockHandles = [];

    private function acquireLock(string $name): bool
    {
        $path = $this->lockPath($name);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($path, 'c');
        if ($fh === false) {
            return false;
        }

        // 비블로킹 배타적 락 (TOCTOU 레이스 컨디션 제거)
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            // 10분 이상 된 락 파일 정리 (데드락 방지)
            clearstatcache(true, $path);
            if (is_file($path) && time() - (int) filemtime($path) > 600) {
                @unlink($path);
            }
            return false;
        }

        // PID 기록 + 핸들 보관
        ftruncate($fh, 0);
        fwrite($fh, (string) getmypid());
        fflush($fh);
        $this->lockHandles[$name] = $fh;

        return true;
    }

    private function releaseLock(string $name): void
    {
        $path = $this->lockPath($name);
        if (isset($this->lockHandles[$name])) {
            flock($this->lockHandles[$name], LOCK_UN);
            fclose($this->lockHandles[$name]);
            unset($this->lockHandles[$name]);
        }
        @unlink($path);
    }

    /** @return array<string, mixed> */
    private function &current(): array
    {
        if ($this->currentIndex < 0) {
            throw new \RuntimeException('태스크를 먼저 등록하세요 (command 또는 call).');
        }
        return $this->tasks[$this->currentIndex];
    }
}
