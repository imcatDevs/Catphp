<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Queue — 비동기 작업 큐
 *
 * Redis 또는 DB 백엔드로 작업을 큐에 넣고 워커가 순차 실행.
 *
 * 사용법:
 *   queue()->push('email', ['to' => 'a@b.com', 'body' => 'hi']);
 *   queue()->push('resize', ['path' => '/img.jpg'], 'images', 3); // 큐 이름, 최대 재시도
 *   queue()->later(60, 'cleanup', ['days' => 7]);  // 60초 후 실행
 *
 * 워커 (cli.php):
 *   php cli.php queue:work              # 기본 큐
 *   php cli.php queue:work --queue=images
 */
final class Queue
{
    private static ?self $instance = null;

    private string $driver; // redis, db
    private string $defaultQueue;

    /** @var array<string, callable> 작업 핸들러 */
    private array $handlers = [];

    private function __construct()
    {
        $this->driver       = (string) config('queue.driver', 'redis');
        $this->defaultQueue = (string) config('queue.default', 'default');
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 핸들러 등록 ──

    /** 작업 핸들러 등록 */
    public function handle(string $job, callable $handler): self
    {
        $this->handlers[$job] = $handler;
        return $this;
    }

    // ── 작업 추가 ──

    /** 즉시 실행 큐에 추가 */
    public function push(string $job, array $payload = [], ?string $queue = null, int $maxRetries = 3): string
    {
        $id = bin2hex(random_bytes(16));
        $data = [
            'id'          => $id,
            'job'         => $job,
            'payload'     => $payload,
            'queue'       => $queue ?? $this->defaultQueue,
            'max_retries' => $maxRetries,
            'attempts'    => 0,
            'created_at'  => time(),
            'available_at'=> time(),
        ];

        $this->enqueue($data);
        return $id;
    }

    /** 지연 실행 (초 단위) */
    public function later(int $delaySeconds, string $job, array $payload = [], ?string $queue = null, int $maxRetries = 3): string
    {
        $id = bin2hex(random_bytes(16));
        $data = [
            'id'          => $id,
            'job'         => $job,
            'payload'     => $payload,
            'queue'       => $queue ?? $this->defaultQueue,
            'max_retries' => $maxRetries,
            'attempts'    => 0,
            'created_at'  => time(),
            'available_at'=> time() + $delaySeconds,
        ];

        if ($this->driver === 'redis') {
            // 지연 작업은 Sorted Set에 보관 (score = available_at)
            $this->redisDelayedEnqueue($data);
        } else {
            $this->dbEnqueue($data);
        }

        return $id;
    }

    // ── 작업 소비 (워커) ──

    /** 단일 작업 꺼내기 + 실행 */
    public function pop(?string $queue = null): bool
    {
        $queue ??= $this->defaultQueue;

        // 지연 작업 승격
        if ($this->driver === 'redis') {
            $this->promoteDelayed($queue);
        }

        $data = $this->dequeue($queue);
        if ($data === null) {
            return false;
        }

        return $this->process($data);
    }

    /** 워커 루프 (블로킹, SIGTERM/SIGINT 그레이스풀 셧다운 지원) */
    public function work(?string $queue = null, int $sleep = 3, int $maxJobs = 0): void
    {
        $queue ??= $this->defaultQueue;
        $processed = 0;
        $shouldQuit = false;

        // PCNTL 시그널 핸들링 (CLI 환경)
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            $handler = static function () use (&$shouldQuit): void {
                $shouldQuit = true;
            };
            pcntl_signal(SIGTERM, $handler);
            pcntl_signal(SIGINT, $handler);
        }

        while (!$shouldQuit) {
            if ($this->driver === 'redis') {
                $this->promoteDelayed($queue);
            }

            $data = $this->dequeue($queue);

            if ($data !== null) {
                $this->process($data);
                $processed++;

                if ($maxJobs > 0 && $processed >= $maxJobs) {
                    break;
                }
            } else {
                sleep($sleep);
            }
        }
    }

    /** 큐 크기 조회 */
    public function size(?string $queue = null): int
    {
        $queue ??= $this->defaultQueue;
        $key = "queue:{$queue}";

        if ($this->driver === 'redis') {
            return (int) \redis()->lLen($key);
        }

        return (int) \db()->table('queue_jobs')
            ->where('queue', $queue)
            ->whereNull('reserved_at')
            ->where('available_at', date('Y-m-d H:i:s'), '<=')
            ->count();
    }

    /** 큐 비우기 */
    public function clear(?string $queue = null): int
    {
        $queue ??= $this->defaultQueue;

        if ($this->driver === 'redis') {
            $count = (int) \redis()->lLen("queue:{$queue}");
            \redis()->del("queue:{$queue}", "queue:{$queue}:delayed");
            return $count;
        }

        return (int) \db()->table('queue_jobs')
            ->where('queue', $queue)
            ->delete();
    }

    /** 실패한 작업 목록 */
    public function failed(int $limit = 50): array
    {
        if ($this->driver === 'redis') {
            return \redis()->lRange('queue:failed', 0, $limit - 1);
        }

        return \db()->table('queue_failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->all();
    }

    /** 실패한 작업 재시도 */
    public function retryFailed(string $id): bool
    {
        if ($this->driver === 'redis') {
            $failed = \redis()->lRange('queue:failed', 0, -1);
            foreach ($failed as $item) {
                if (is_array($item) && ($item['id'] ?? '') === $id) {
                    // List에서 삭제 (lRem: count=1 → 좌→우 첫 번째 매칭 삭제)
                    \redis()->raw()->lRem($this->prefixed('queue:failed'), $item, 1);
                    $item['attempts'] = 0;
                    $this->enqueue($item);
                    return true;
                }
            }
            return false;
        }

        $job = \db()->table('queue_failed_jobs')->where('id', $id)->first();
        if (!$job) {
            return false;
        }
        $payload = json_decode($job['payload'] ?? '{}', true);
        $this->push($payload['job'] ?? '', $payload['payload'] ?? [], $payload['queue'] ?? null);
        \db()->table('queue_failed_jobs')->where('id', $id)->delete();
        return true;
    }

    // ── 내부 로직 ──

    private function process(array $data): bool
    {
        $job = $data['job'] ?? '';
        $handler = $this->handlers[$job] ?? null;

        if ($handler === null) {
            $this->fail($data, "핸들러 미등록: {$job}");
            return false;
        }

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;

        try {
            $handler($data['payload'] ?? []);
            return true;
        } catch (\Throwable $e) {
            if ($data['attempts'] >= ($data['max_retries'] ?? 3)) {
                $this->fail($data, $e->getMessage());
            } else {
                // 재시도: 지수 백오프 (2^attempts 초 후)
                $delay = (int) pow(2, $data['attempts']);
                $data['available_at'] = time() + $delay;
                if ($this->driver === 'redis') {
                    $this->redisDelayedEnqueue($data);
                } else {
                    $this->dbEnqueue($data);
                }
            }
            return false;
        }
    }

    private function fail(array $data, string $error): void
    {
        $data['error'] = $error;
        $data['failed_at'] = time();

        if ($this->driver === 'redis') {
            \redis()->rPush('queue:failed', $data);
        } else {
            \db()->table('queue_failed_jobs')->insert([
                'id'        => $data['id'],
                'queue'     => $data['queue'] ?? $this->defaultQueue,
                'payload'   => json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}',
                'error'     => $error,
                'failed_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function enqueue(array $data): void
    {
        $queue = $data['queue'] ?? $this->defaultQueue;

        if ($this->driver === 'redis') {
            \redis()->rPush("queue:{$queue}", $data);
        } else {
            $this->dbEnqueue($data);
        }
    }

    private function dequeue(string $queue): ?array
    {
        if ($this->driver === 'redis') {
            $data = \redis()->lPop("queue:{$queue}");
            return is_array($data) ? $data : null;
        }

        // DB: 트랜잭션으로 원자적 dequeue (동시 워커 중복 소비 방지)
        $now = date('Y-m-d H:i:s');
        $payload = null;

        \db()->transaction(function () use ($queue, $now, &$payload): void {
            $job = \db()->table('queue_jobs')
                ->where('queue', $queue)
                ->whereNull('reserved_at')
                ->where('available_at', $now, '<=')
                ->orderBy('id', 'asc')
                ->first();

            if (!$job) {
                return;
            }

            // 예약 마킹 (다른 워커가 같은 작업을 가져가지 못하도록)
            $affected = \db()->table('queue_jobs')
                ->where('id', $job['id'])
                ->whereNull('reserved_at')
                ->update(['reserved_at' => date('Y-m-d H:i:s')]);

            if ($affected === 0) {
                // 다른 워커가 이미 예약함
                return;
            }

            $payload = json_decode($job['payload'] ?? '{}', true);
            $payload['id'] = (string) $job['id'];

            // 작업 삭제
            \db()->table('queue_jobs')->where('id', $job['id'])->delete();
        });

        return $payload;
    }

    private function dbEnqueue(array $data): void
    {
        \db()->table('queue_jobs')->insert([
            'queue'        => $data['queue'] ?? $this->defaultQueue,
            'payload'      => json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}',
            'attempts'     => $data['attempts'] ?? 0,
            'available_at' => date('Y-m-d H:i:s', $data['available_at'] ?? time()),
            'created_at'   => date('Y-m-d H:i:s', $data['created_at'] ?? time()),
        ]);
    }

    private function redisDelayedEnqueue(array $data): void
    {
        $queue = $data['queue'] ?? $this->defaultQueue;
        \redis()->zAdd("queue:{$queue}:delayed", (float) ($data['available_at'] ?? time()), $data);
    }

    private function promoteDelayed(string $queue): void
    {
        $now = time();
        $delayedKey = "queue:{$queue}:delayed";
        $queueKey = "queue:{$queue}";

        // Lua 스크립트로 원자적 이동 (다중 워커 중복 방지)
        $lua = <<<'LUA'
local delayed = KEYS[1]
local queue = KEYS[2]
local now = tonumber(ARGV[1])
local moved = 0
local items = redis.call('zrangebyscore', delayed, '-inf', now, 'LIMIT', 0, 100)
for _, item in ipairs(items) do
    if redis.call('zrem', delayed, item) > 0 then
        redis.call('rpush', queue, item)
        moved = moved + 1
    end
end
return moved
LUA;

        \redis()->raw()->eval(
            $lua,
            [$this->prefixed($delayedKey), $this->prefixed($queueKey), (string) $now],
            2
        );
    }

    /** Redis 프리픽스 적용된 키 반환 */
    private function prefixed(string $key): string
    {
        return (string) \config('redis.prefix', 'catphp:') . $key;
    }
}
