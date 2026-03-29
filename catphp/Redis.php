<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Redis — Redis 클라이언트 래퍼
 *
 * 캐시·세션·큐·Pub/Sub 등 다목적 Redis 드라이버.
 * phpredis 확장(ext-redis) 기반.
 *
 * 사용법:
 *   redis()->set('key', 'value', 60);
 *   redis()->get('key');
 *   redis()->hSet('user:1', 'name', 'Alice');
 *   redis()->publish('channel', 'msg');
 */
final class Redis
{
    private static ?self $instance = null;
    private ?\Redis $conn = null;

    private string $host;
    private int $port;
    private ?string $password;
    private int $database;
    private string $prefix;
    private float $timeout;

    private function __construct()
    {
        $this->host     = (string) config('redis.host', '127.0.0.1');
        $this->port     = (int) config('redis.port', 6379);
        $this->password = config('redis.password') ?: null;
        $this->database = (int) config('redis.database', 0);
        $this->prefix   = (string) config('redis.prefix', 'catphp:');
        $this->timeout  = (float) config('redis.timeout', 2.0);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 연결 (지연 로딩) ──

    private function connection(): \Redis
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        if (!extension_loaded('redis')) {
            throw new \RuntimeException('ext-redis 확장이 필요합니다. pecl install redis');
        }

        $this->conn = new \Redis();

        if (!$this->conn->connect($this->host, $this->port, $this->timeout)) {
            throw new \RuntimeException("Redis 연결 실패: {$this->host}:{$this->port}");
        }

        if ($this->password !== null) {
            if (!$this->conn->auth($this->password)) {
                throw new \RuntimeException('Redis 인증 실패: 비밀번호를 확인하세요.');
            }
        }

        if ($this->database !== 0) {
            $this->conn->select($this->database);
        }

        if ($this->prefix !== '') {
            $this->conn->setOption(\Redis::OPT_PREFIX, $this->prefix);
        }

        $this->conn->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_JSON);

        return $this->conn;
    }

    // ── String 명령어 ──

    /** 값 설정 (TTL 초, 0 = 영구) */
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($ttl > 0) {
            return $this->connection()->setex($key, $ttl, $value);
        }
        return $this->connection()->set($key, $value);
    }

    /** 값 읽기 */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->connection()->get($key);
        return $value !== false ? $value : $default;
    }

    /** 키 삭제 */
    public function del(string ...$keys): int
    {
        return $this->connection()->del(...$keys);
    }

    /** 키 존재 여부 */
    public function exists(string $key): bool
    {
        return (bool) $this->connection()->exists($key);
    }

    /** TTL 설정 */
    public function expire(string $key, int $ttl): bool
    {
        return $this->connection()->expire($key, $ttl);
    }

    /** 남은 TTL (초) */
    public function ttl(string $key): int
    {
        return $this->connection()->ttl($key);
    }

    /** 증가 */
    public function incr(string $key, int $by = 1): int
    {
        return $this->connection()->incrBy($key, $by);
    }

    /** 감소 */
    public function decr(string $key, int $by = 1): int
    {
        return $this->connection()->decrBy($key, $by);
    }

    // ── Hash 명령어 ──

    public function hSet(string $key, string $field, mixed $value): bool|int
    {
        return $this->connection()->hSet($key, $field, $value);
    }

    public function hGet(string $key, string $field, mixed $default = null): mixed
    {
        $value = $this->connection()->hGet($key, $field);
        return $value !== false ? $value : $default;
    }

    public function hGetAll(string $key): array
    {
        return $this->connection()->hGetAll($key) ?: [];
    }

    public function hDel(string $key, string ...$fields): int
    {
        return $this->connection()->hDel($key, ...$fields);
    }

    public function hExists(string $key, string $field): bool
    {
        return $this->connection()->hExists($key, $field);
    }

    // ── List 명령어 ──

    /** 리스트 오른쪽 추가 (큐 입력) */
    public function rPush(string $key, mixed ...$values): int
    {
        return $this->connection()->rPush($key, ...$values);
    }

    /** 리스트 왼쪽 꺼내기 (큐 소비) */
    public function lPop(string $key): mixed
    {
        $value = $this->connection()->lPop($key);
        return $value !== false ? $value : null;
    }

    /** 블로킹 왼쪽 꺼내기 */
    public function blPop(string $key, int $timeout = 0): mixed
    {
        $result = $this->connection()->blPop([$key], $timeout);
        return $result ? $result[1] : null;
    }

    /** 리스트 길이 */
    public function lLen(string $key): int
    {
        return $this->connection()->lLen($key);
    }

    /** 리스트 범위 조회 */
    public function lRange(string $key, int $start = 0, int $end = -1): array
    {
        return $this->connection()->lRange($key, $start, $end) ?: [];
    }

    // ── Set 명령어 ──

    public function sAdd(string $key, mixed ...$members): int
    {
        return $this->connection()->sAdd($key, ...$members);
    }

    public function sMembers(string $key): array
    {
        return $this->connection()->sMembers($key) ?: [];
    }

    public function sIsMember(string $key, mixed $member): bool
    {
        return $this->connection()->sIsMember($key, $member);
    }

    public function sRem(string $key, mixed ...$members): int
    {
        return $this->connection()->sRem($key, ...$members);
    }

    // ── Sorted Set 명령어 ──

    public function zAdd(string $key, float $score, mixed $member): int
    {
        return $this->connection()->zAdd($key, $score, $member);
    }

    public function zRange(string $key, int $start = 0, int $end = -1, bool $withScores = false): array
    {
        return $this->connection()->zRange($key, $start, $end, $withScores) ?: [];
    }

    public function zRem(string $key, mixed ...$members): int
    {
        return $this->connection()->zRem($key, ...$members);
    }

    public function zScore(string $key, mixed $member): float|false
    {
        return $this->connection()->zScore($key, $member);
    }

    // ── Pub/Sub ──

    /** 메시지 발행 */
    public function publish(string $channel, mixed $message): int
    {
        return $this->connection()->publish($channel, is_string($message) ? $message : (json_encode($message, JSON_UNESCAPED_UNICODE) ?: ''));
    }

    // ── 유틸리티 ──

    /** 패턴 키 검색 (주의: 프로덕션에서 대량 사용 금지) */
    public function keys(string $pattern = '*'): array
    {
        // 운영환경 경고: KEYS 명령은 대량 키에서 Redis를 블로킹함
        if (!(bool) config('app.debug', false) && class_exists('Cat\\Log', false)) {
            \logger()->warn('Redis keys() 호출 감지. 운영환경에서는 SCAN 사용을 권장합니다.', [
                'pattern' => $pattern,
            ]);
        }
        return $this->connection()->keys($pattern) ?: [];
    }

    /** 전체 삭제 (현재 DB) — 개발환경에서만 허용 */
    public function flush(): bool
    {
        if (!(bool) config('app.debug', false)) {
            if (class_exists('Cat\\Log', false)) {
                \logger()->error('Redis flush() 차단: 운영환경에서는 flushDB가 금지됩니다.');
            }
            return false;
        }
        return $this->connection()->flushDB();
    }

    /** PING */
    public function ping(): bool
    {
        try {
            $result = $this->connection()->ping();
            return $result === true || $result === '+PONG';
        } catch (\RedisException) {
            return false;
        }
    }

    /** 캐시 기억 패턴 (Cache::remember와 동일) */
    public function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /** 원시 \Redis 인스턴스 반환 (고급 사용) */
    public function raw(): \Redis
    {
        return $this->connection();
    }

    public function __destruct()
    {
        if ($this->conn !== null) {
            try {
                $this->conn->close();
            } catch (\RedisException) {
                // 무시
            }
            $this->conn = null;
        }
    }
}
