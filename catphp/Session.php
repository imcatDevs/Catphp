<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Session — 세션 관리 (다중 드라이버)
 *
 * 드라이버:
 *   - `native` (기본): PHP 내장 `$_SESSION` 사용. FPM 환경에 최적화.
 *   - `redis`: Redis 기반 외부 저장소. Swoole/상주 프로세스 환경 필수.
 *
 * 자동 드라이버 선택:
 *   - `\Cat\Swoole::inRequest()` 감지 시 강제 `redis` 사용 (네이티브 세션은 Swoole 호환 불가)
 *   - CLI 환경: in-memory 배열 (테스트/스크립트용)
 *   - 그 외: `config('session.driver')` 값 사용
 *
 * 사용법:
 *   session()->get('user_id');
 *   session()->set('cart', $items);
 *   session()->flash('success', '저장 완료');
 *   session()->regenerate();
 *   session()->destroy();
 *   session()->save(); // Redis 드라이버에서 수동 커밋 (보통 Swoole 브리지가 자동 호출)
 */
final class Session
{
    private static ?self $instance = null;

    /** 'native' | 'redis' | 'memory' */
    private string $driver = 'memory';
    private bool $started = false;
    private string $id = '';

    /** @var array<string, mixed> 세션 데이터 (native 드라이버에서는 $_SESSION 참조) */
    private array $data = [];

    /** Redis 드라이버 쓰기 필요 여부 */
    private bool $dirty = false;

    /** 세션 쿠키 이름 (redis 드라이버용) */
    private string $cookieName = 'CATPHP_SID';

    /** 세션 ID 정규식 (64자 hex) */
    private const ID_PATTERN = '/^[a-f0-9]{64}$/';

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Swoole 요청 간 상태 초기화 (프레임워크 내부용)
     *
     * 이전 요청의 세션 데이터/ID를 완전 제거하여 요청 격리 보장.
     * `Swoole.php::handleRequest()` 시작부에서 호출.
     */
    public static function resetInstance(): void
    {
        if (self::$instance === null) {
            return;
        }
        self::$instance->started = false;
        self::$instance->id = '';
        self::$instance->data = [];
        self::$instance->dirty = false;
    }

    // ══════════════════════════════════════════════
    // 드라이버 해석 + 부팅
    // ══════════════════════════════════════════════

    /** 드라이버 자동 결정 */
    private function resolveDriver(): string
    {
        // Swoole HTTP 컨텍스트에서는 무조건 redis (네이티브 세션 불가)
        if (class_exists('\\Cat\\Swoole', false) && \Cat\Swoole::inRequest()) {
            return 'redis';
        }

        // CLI: in-memory 배열 (테스트/스크립트용)
        if (PHP_SAPI === 'cli') {
            return 'memory';
        }

        return (string) \config('session.driver', 'native');
    }

    /** 세션 시작 (지연 호출 — 첫 접근 시 자동 실행) */
    public function start(): self
    {
        if ($this->started) {
            return $this;
        }

        $this->cookieName = (string) \config('session.cookie', 'CATPHP_SID');
        $this->driver = $this->resolveDriver();

        switch ($this->driver) {
            case 'redis':
                $this->startRedis();
                break;
            case 'native':
                $this->startNative();
                break;
            case 'memory':
            default:
                $this->startMemory();
                break;
        }

        $this->ageFlash();
        $this->started = true;
        return $this;
    }

    /** Redis 드라이버: 쿠키 → ID → Redis 로드 */
    private function startRedis(): void
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis 세션 드라이버는 ext-redis 확장이 필요합니다.');
        }

        $cookieId = (string) ($_COOKIE[$this->cookieName] ?? '');
        if ($cookieId !== '' && preg_match(self::ID_PATTERN, $cookieId)) {
            $this->id = $cookieId;
            $loaded = \redis()->get($this->redisKey());
            $this->data = is_array($loaded) ? $loaded : [];
        } else {
            $this->id = $this->generateId();
            $this->data = [];
            $this->sendCookie($this->cookieName, $this->id);
        }
    }

    /** 네이티브 드라이버: PHP `session_start()` + `$_SESSION` 참조 */
    private function startNative(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->data = &$_SESSION;
            $this->id = session_id() ?: '';
            return;
        }

        $lifetime = (int) \config('session.lifetime', 7200);
        $path     = (string) \config('session.path', '/');
        $secure   = (bool) \config('session.secure', false);
        $httpOnly = (bool) \config('session.httponly', true);
        $sameSite = (string) \config('session.samesite', 'Lax');

        // 운영환경 쿠키 보안 경고
        $debug = (bool) \config('app.debug', false);
        if (!$debug && class_exists('Cat\\Log', false)) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
            if ($isHttps && !$secure) {
                \logger()->warn('session.secure가 false입니다. HTTPS 환경에서는 true 권장.');
            }
            if (!$httpOnly) {
                \logger()->warn('session.httponly가 false입니다. JavaScript 접근 허용은 XSS 위험.');
            }
            if (strtolower($sameSite) === 'none' && !$secure) {
                \logger()->warn('session.samesite=None은 secure=true 필수.');
            }
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $path,
            'secure'   => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ]);
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        if (!headers_sent()) {
            session_start();
        }
        $this->id = session_id() ?: '';
        $this->data = &$_SESSION;
    }

    /** 메모리 드라이버: CLI/테스트용 in-memory 배열 */
    private function startMemory(): void
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $this->data = &$_SESSION;
        $this->id = '';
    }

    // ══════════════════════════════════════════════
    // 기본 CRUD
    // ══════════════════════════════════════════════

    /** 세션 값 읽기 */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /** 세션 값 쓰기 */
    public function set(string $key, mixed $value): self
    {
        $this->start();
        $this->data[$key] = $value;
        $this->dirty = true;
        return $this;
    }

    /** 키 존재 여부 */
    public function has(string $key): bool
    {
        $this->start();
        return array_key_exists($key, $this->data);
    }

    /** 키 삭제 */
    public function forget(string $key): self
    {
        $this->start();
        unset($this->data[$key]);
        $this->dirty = true;
        return $this;
    }

    /** 값 읽은 뒤 삭제 */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    /** 전체 데이터 */
    public function all(): array
    {
        $this->start();
        return $this->data;
    }

    /** 전체 초기화 */
    public function clear(): self
    {
        $this->start();
        $this->data = [];
        $this->dirty = true;
        return $this;
    }

    // ══════════════════════════════════════════════
    // Flash 데이터
    // ══════════════════════════════════════════════

    /** flash 데이터 설정 (다음 요청까지만 유지) */
    public function flash(string $key, mixed $value): self
    {
        $this->start();
        $this->data[$key] = $value;
        $newFlash = $this->data['_flash_new'] ?? [];
        if (!in_array($key, $newFlash, true)) {
            $newFlash[] = $key;
        }
        $this->data['_flash_new'] = $newFlash;
        $this->dirty = true;
        return $this;
    }

    /** flash 데이터 읽기 (소비하지 않음 — 자동 에이징) */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $this->data[$key] ?? $default;
    }

    /** flash 키 존재 */
    public function hasFlash(string $key): bool
    {
        $this->start();
        $old = $this->data['_flash_old'] ?? [];
        $new = $this->data['_flash_new'] ?? [];
        return in_array($key, $old, true) || in_array($key, $new, true);
    }

    /** 현재 flash를 다음 요청까지 한 번 더 유지 */
    public function reflash(): self
    {
        $this->start();
        $old = $this->data['_flash_old'] ?? [];
        $this->data['_flash_new'] = array_values(array_unique(
            array_merge($this->data['_flash_new'] ?? [], $old)
        ));
        $this->data['_flash_old'] = [];
        $this->dirty = true;
        return $this;
    }

    /**
     * 지정 flash 키만 유지
     *
     * @param list<string> $keys
     */
    public function keep(array $keys): self
    {
        $this->start();
        $old = $this->data['_flash_old'] ?? [];
        $keep = array_intersect($old, $keys);
        $this->data['_flash_new'] = array_values(array_unique(
            array_merge($this->data['_flash_new'] ?? [], $keep)
        ));
        $this->data['_flash_old'] = array_values(array_diff($old, $keys));
        $this->dirty = true;
        return $this;
    }

    // ══════════════════════════════════════════════
    // 세션 관리
    // ══════════════════════════════════════════════

    /** 세션 ID 재생성 (세션 고정 공격 방지) */
    public function regenerate(bool $deleteOldSession = true): self
    {
        $this->start();

        if ($this->driver === 'redis') {
            $oldId = $this->id;
            $this->id = $this->generateId();
            if ($deleteOldSession && $oldId !== '') {
                \redis()->del($this->redisPrefix() . $oldId);
            }
            $this->sendCookie($this->cookieName, $this->id);
            $this->dirty = true;
        } elseif ($this->driver === 'native' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
            $this->id = session_id() ?: '';
        }
        return $this;
    }

    /** 세션 파괴 */
    public function destroy(): void
    {
        $this->start();

        if ($this->driver === 'redis') {
            if ($this->id !== '') {
                \redis()->del($this->redisKey());
            }
            // 쿠키 만료
            $this->sendCookie($this->cookieName, '', time() - 42000);
        } elseif ($this->driver === 'native' && session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    ['expires' => time() - 42000] + $params,
                );
            }
            session_destroy();
        }

        $this->data = [];
        $this->id = '';
        $this->started = false;
        $this->dirty = false;
    }

    /** 현재 세션 ID */
    public function id(): string
    {
        $this->start();
        return $this->id;
    }

    /** 세션 쿠키 이름 */
    public function name(): string
    {
        return $this->driver === 'redis'
            ? $this->cookieName
            : (session_name() ?: 'PHPSESSID');
    }

    /** 세션 시작 여부 */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /** 현재 드라이버 이름 */
    public function driver(): string
    {
        $this->start();
        return $this->driver;
    }

    // ══════════════════════════════════════════════
    // 영속화 (Redis 드라이버 전용)
    // ══════════════════════════════════════════════

    /**
     * 변경된 세션 데이터를 Redis에 저장 (Swoole 브리지가 요청 종료 시 자동 호출).
     *
     * 네이티브/메모리 드라이버에서는 no-op.
     */
    public function save(): void
    {
        if (!$this->started || !$this->dirty || $this->driver !== 'redis' || $this->id === '') {
            return;
        }
        $ttl = (int) \config('session.lifetime', 7200);
        \redis()->set($this->redisKey(), $this->data, $ttl);
        $this->dirty = false;
    }

    // ══════════════════════════════════════════════
    // 유틸
    // ══════════════════════════════════════════════

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

    /** 정수 증가 */
    public function increment(string $key, int $amount = 1): int
    {
        $value = (int) $this->get($key, 0) + $amount;
        $this->set($key, $value);
        return $value;
    }

    /** 정수 감소 */
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }

    /** CSRF 토큰 위임 */
    public function token(): string
    {
        return \csrf()->token();
    }

    // ══════════════════════════════════════════════
    // 내부
    // ══════════════════════════════════════════════

    /** flash 에이징: old 삭제, new → old 이동 */
    private function ageFlash(): void
    {
        $old = $this->data['_flash_old'] ?? [];
        foreach ($old as $key) {
            unset($this->data[$key]);
        }
        $this->data['_flash_old'] = $this->data['_flash_new'] ?? [];
        $this->data['_flash_new'] = [];
        // ageFlash 자체는 dirty로 간주하지 않음 — 실제 사용자 쓰기 없으면 저장 불필요
    }

    /** 세션 ID 생성 (256비트 엔트로피) */
    private function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** Redis 키 prefix (redis 키 이름만, 전역 prefix는 Redis 드라이버가 자동 적용) */
    private function redisPrefix(): string
    {
        return (string) \config('session.redis_prefix', 'sess:');
    }

    /** 현재 세션의 Redis 키 */
    private function redisKey(): string
    {
        return $this->redisPrefix() . $this->id;
    }

    /**
     * 세션 쿠키 전송 (Swoole / FPM 자동 분기)
     */
    private function sendCookie(string $name, string $value, ?int $expires = null): void
    {
        $lifetime = (int) \config('session.lifetime', 7200);
        $path     = (string) \config('session.path', '/');
        $secure   = (bool) \config('session.secure', false);
        $httpOnly = (bool) \config('session.httponly', true);
        $sameSite = (string) \config('session.samesite', 'Lax');
        $expires ??= ($value === '' ? time() - 42000 : time() + $lifetime);

        // Swoole 컨텍스트 직접 전송
        if (class_exists('\\Cat\\Swoole', false)) {
            $res = \Cat\Swoole::currentResponse();
            if ($res !== null) {
                $res->cookie($name, $value, $expires, $path, '', $secure, $httpOnly, $sameSite);
                return;
            }
        }

        // FPM
        if (!headers_sent()) {
            setcookie($name, $value, [
                'expires'  => $expires,
                'path'     => $path,
                'secure'   => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ]);
        }
    }
}
