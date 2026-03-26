<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Cache — 파일 캐시
 *
 * @config array{
 *     path: string,   // 캐시 디렉토리 경로
 *     ttl?: int,      // 기본 TTL (초, 기본 3600)
 * } cache  → config('cache.path')
 */
final class Cache
{
    private static ?self $instance = null;

    private function __construct(
        private readonly string $path,
        private readonly int $defaultTtl,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            path: \config('cache.path') ?? __DIR__ . '/../storage/cache',
            defaultTtl: (int) (\config('cache.ttl') ?? 3600),
        );
    }

    /** 캐시 디렉토리 보장 */
    private function ensureDir(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /** 캐시 키 → 파일 경로 */
    private function filePath(string $key): string
    {
        return $this->path . '/' . hash('xxh3', $key) . '.cache';
    }

    /** 캐시 읽기 */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return $default;
        }

        $data = json_decode($content, true, 16);
        if (!is_array($data) || !isset($data['expires']) || !array_key_exists('value', $data)) {
            return $default;
        }

        // TTL 만료 확인
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($file);
            return $default;
        }

        return $data['value'];
    }

    /** 캐시 쓰기 */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->ensureDir();
        $file = $this->filePath($key);
        $ttl ??= $this->defaultTtl;

        $data = json_encode([
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ], JSON_UNESCAPED_UNICODE);

        return file_put_contents($file, $data, LOCK_EX) !== false;
    }

    /** 캐시 삭제 */
    public function del(string $key): bool
    {
        $file = $this->filePath($key);
        return is_file($file) && unlink($file);
    }

    /** 캐시 존재 확인 */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /** 전체 캐시 삭제 */
    public function clear(): bool
    {
        if (!is_dir($this->path)) {
            return true;
        }
        $files = glob($this->path . '/*.cache');
        if ($files === false) {
            return false;
        }
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    /** 캐시에 없으면 콜백 실행 후 저장 (null 값도 캐싱 가능) */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->get($key, $sentinel);
        if ($value !== $sentinel) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }
}
