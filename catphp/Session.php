<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Session — 세션 관리 래퍼
 *
 * PHP 네이티브 세션을 config 기반으로 초기화하고 편의 메서드 제공.
 *
 * 사용법:
 *   session()->get('user_id');
 *   session()->set('cart', $items);
 *   session()->flash('success', '저장 완료');
 *   session()->has('user_id');
 *   session()->forget('cart');
 *   session()->regenerate();
 *   session()->destroy();
 */
final class Session
{
    private static ?self $instance = null;

    private bool $started = false;

    private function __construct()
    {
        $this->start();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 세션 시작 */
    public function start(): self
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return $this;
        }

        // CLI 환경에서는 세션 시작하지 않음
        if (PHP_SAPI === 'cli') {
            $this->started = true;
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
            return $this;
        }

        $lifetime = (int) \config('session.lifetime', 7200);
        $path     = (string) \config('session.path', '/');
        $secure   = (bool) \config('session.secure', false);
        $httpOnly = (bool) \config('session.httponly', true);
        $sameSite = (string) \config('session.samesite', 'Lax');

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $path,
            'secure'   => $secure,
            'httponly'  => $httpOnly,
            'samesite' => $sameSite,
        ]);

        ini_set('session.gc_maxlifetime', (string) $lifetime);

        if (!headers_sent()) {
            session_start();
        }

        $this->started = true;

        // flash 데이터 정리 (이전 요청 flash 삭제)
        $this->ageFlash();

        return $this;
    }

    // ── 기본 CRUD ──

    /** 세션 값 가져오기 (dot notation 미지원, 단순 키) */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** 세션 값 설정 */
    public function set(string $key, mixed $value): self
    {
        $_SESSION[$key] = $value;
        return $this;
    }

    /** 세션 키 존재 확인 */
    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION ?? []);
    }

    /** 세션 키 삭제 */
    public function forget(string $key): self
    {
        unset($_SESSION[$key]);
        return $this;
    }

    /** 값을 가져온 뒤 삭제 */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /** 모든 세션 데이터 */
    public function all(): array
    {
        return $_SESSION ?? [];
    }

    /** 세션 전체 초기화 */
    public function clear(): self
    {
        $_SESSION = [];
        return $this;
    }

    // ── Flash 데이터 ──

    /** flash 데이터 설정 (다음 요청까지만 유지) */
    public function flash(string $key, mixed $value): self
    {
        $_SESSION[$key] = $value;
        if (!in_array($key, $_SESSION['_flash_new'] ?? [], true)) {
            $_SESSION['_flash_new'][] = $key;
        }
        return $this;
    }

    /** flash 데이터 가져오기 */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** flash 데이터 존재 확인 */
    public function hasFlash(string $key): bool
    {
        $old = $_SESSION['_flash_old'] ?? [];
        $new = $_SESSION['_flash_new'] ?? [];
        return in_array($key, $old, true) || in_array($key, $new, true);
    }

    /** 현재 flash 데이터를 한 번 더 유지 */
    public function reflash(): self
    {
        $old = $_SESSION['_flash_old'] ?? [];
        $_SESSION['_flash_new'] = array_unique(
            array_merge($_SESSION['_flash_new'] ?? [], $old)
        );
        $_SESSION['_flash_old'] = [];
        return $this;
    }

    /**
     * 지정한 flash 키만 유지
     *
     * @param list<string> $keys
     */
    public function keep(array $keys): self
    {
        $old = $_SESSION['_flash_old'] ?? [];
        $keep = array_intersect($old, $keys);
        $_SESSION['_flash_new'] = array_unique(
            array_merge($_SESSION['_flash_new'] ?? [], $keep)
        );
        $_SESSION['_flash_old'] = array_diff($old, $keys);
        return $this;
    }

    // ── 세션 관리 ──

    /** 세션 ID 재생성 (세션 고정 공격 방지) */
    public function regenerate(bool $deleteOldSession = true): self
    {
        if (session_status() === PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
            session_regenerate_id($deleteOldSession);
        }
        return $this;
    }

    /** 세션 파괴 */
    public function destroy(): void
    {
        $_SESSION = [];

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            // 세션 쿠키 삭제
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    ['expires' => time() - 42000] + $params
                );
            }
            session_destroy();
        }

        $this->started = false;
    }

    /** 현재 세션 ID */
    public function id(): string
    {
        return session_id() ?: '';
    }

    /** 세션 이름 */
    public function name(): string
    {
        return session_name() ?: 'PHPSESSID';
    }

    /** 세션이 시작되었는지 */
    public function isStarted(): bool
    {
        return $this->started;
    }

    // ── 유틸 ──

    /** 값이 없으면 설정 후 반환 */
    public function remember(string $key, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value);
        return $value;
    }

    /** 값 증가 */
    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key, 0) + $amount;
        $this->set($key, $value);
        return $value;
    }

    /** 값 감소 */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    /** CSRF 토큰 생성/가져오기 */
    public function token(): string
    {
        if (!$this->has('_token')) {
            $this->set('_token', bin2hex(random_bytes(32)));
        }
        return (string) $this->get('_token');
    }

    // ── 내부 ──

    /** flash 데이터 에이징: old 삭제, new → old 이동 */
    private function ageFlash(): void
    {
        // 이전 요청의 flash 키 삭제
        $old = $_SESSION['_flash_old'] ?? [];
        foreach ($old as $key) {
            unset($_SESSION[$key]);
        }

        // new → old 이동
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }
}
