<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Cookie — 쿠키 관리
 *
 * @config array{
 *     encrypt?: bool,     // 암호화 여부 (기본 true)
 *     samesite?: string,  // SameSite 속성 (기본 'Lax')
 *     secure?: bool,      // Secure 속성 (기본 false)
 * } cookie  → config('cookie.encrypt')
 */
final class Cookie
{
    private static ?self $instance = null;

    private function __construct(
        private readonly bool $doEncrypt,
        private readonly string $sameSite,
        private readonly bool $secure,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            doEncrypt: (bool) (\config('cookie.encrypt') ?? true),
            sameSite: \config('cookie.samesite') ?? 'Lax',
            secure: (bool) (\config('cookie.secure') ?? false),
        );
    }

    /** 쿠키 읽기 */
    public function get(string $name, mixed $default = null): mixed
    {
        $raw = $_COOKIE[$name] ?? null;
        if ($raw === null) {
            return $default;
        }

        if ($this->doEncrypt) {
            $decrypted = \encrypt()->open($raw);
            if ($decrypted === null) {
                return $default;
            }
            $decoded = json_decode($decrypted, true);
            $result = is_array($decoded) ? $decoded : $decrypted;
        } else {
            $result = $raw;
        }

        // Guard 살균 — 쿠키값은 클라이언트 조작 가능
        if (is_string($result)) {
            return \guard()->clean($result);
        }
        if (is_array($result)) {
            return \guard()->cleanArray($result);
        }
        return $result;
    }

    /** 쿠키 설정 */
    public function set(string $name, mixed $value, int $ttl = 86400): bool
    {
        $raw = is_string($value) ? $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');

        if ($this->doEncrypt) {
            $raw = \encrypt()->seal($raw);
        }

        return setcookie($name, $raw, [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'secure'   => $this->secure,
            'httponly'  => true,
            'samesite' => $this->sameSite,
        ]);
    }

    /** 쿠키 삭제 */
    public function del(string $name): bool
    {
        unset($_COOKIE[$name]);
        return setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $this->secure,
            'httponly'  => true,
            'samesite' => $this->sameSite,
        ]);
    }

    /** 쿠키 존재 확인 */
    public function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }
}
