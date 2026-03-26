<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Rate — 레이트 리미트
 *
 * @config array{
 *     storage?: string,  // 'cache' (기본)
 * } rate  → config('rate.storage')
 */
final class Rate
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 레이트 리미트 확인 (flock 동시성 보호)
     *
     * @param string $key   식별 키 (예: 'api', 'login')
     * @param int    $window 시간 윈도우 (초)
     * @param int    $max    최대 허용 횟수
     * @return bool 허용 여부
     */
    public function limit(string $key, int $window, int $max): bool
    {
        $ip = \ip()->address();
        $file = $this->ratePath($key, $ip);

        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return true;
        }

        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
        $now = time();

        if (!is_array($data)) {
            $data = ['hits' => []];
        }

        // 슬라이딩 윈도우: 만료 히트 제거
        $data['hits'] = array_values(array_filter(
            $data['hits'],
            fn(int $t) => $t > ($now - $window)
        ));

        if (count($data['hits']) >= $max) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        $data['hits'][] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    /** 레이트 리미트 확인 (기록 없이 조회만) */
    public function check(string $key, int $window, int $max): bool
    {
        $ip = \ip()->address();
        $file = $this->ratePath($key, $ip);
        $now = time();

        if (!is_file($file)) {
            return true;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return true;
        }

        $hits = array_filter($data['hits'] ?? [], fn(int $t) => $t > ($now - $window));
        return count($hits) < $max;
    }

    /** 남은 요청 횟수 */
    public function remaining(string $key, int $window, int $max): int
    {
        $ip = \ip()->address();
        $file = $this->ratePath($key, $ip);
        $now = time();

        if (!is_file($file)) {
            return $max;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return $max;
        }

        $hits = array_filter($data['hits'] ?? [], fn(int $t) => $t > ($now - $window));
        return max(0, $max - count($hits));
    }

    /** 레이트 리미트 초기화 */
    public function reset(string $key): bool
    {
        $ip = \ip()->address();
        $file = $this->ratePath($key, $ip);
        return is_file($file) && unlink($file);
    }

    /**
     * 만료 파일 가비지 컬렉션 (확률적 실행)
     *
     * 기본 1% 확률로 실행하여 성능 영향 최소화.
     * 1시간 이상 변경 없는 파일 삭제.
     */
    public function gc(int $probability = 1, int $maxAge = 3600): void
    {
        if (random_int(1, 100) > $probability) {
            return;
        }

        $dir = \config('rate.path') ?? __DIR__ . '/../storage/rate';
        $files = glob($dir . '/*.json');
        if ($files === false) {
            return;
        }

        $threshold = time() - $maxAge;
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
            }
        }

        // block 파일도 정리
        $locks = glob($dir . '/*.lock');
        if ($locks !== false) {
            foreach ($locks as $lock) {
                $expiresAt = (int) @file_get_contents($lock);
                if ($expiresAt > 0 && $expiresAt < time()) {
                    @unlink($lock);
                }
            }
        }
    }

    /** rate 파일 경로 생성 */
    private function ratePath(string $key, string $ip): string
    {
        static $dirChecked = false;
        $dir = \config('rate.path') ?? __DIR__ . '/../storage/rate';

        if (!$dirChecked) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $dirChecked = true;

            // 확률적 GC 실행 (1%)
            $this->gc();
        }

        return $dir . '/' . md5("{$key}:{$ip}") . '.json';
    }
}
