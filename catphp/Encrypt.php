<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Encrypt — 암호화 (Sodium)
 *
 * @config array{
 *     key: string,  // base64 인코딩된 암호화 키
 * } encrypt  → config('encrypt.key')
 */
final class Encrypt
{
    private static ?self $instance = null;

    private function __construct(
        private string $key,
    ) {}

    public static function getInstance(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $rawKey = \config('encrypt.key') ?? '';
        if ($rawKey === '') {
            throw new \RuntimeException('encrypt.key 설정이 비어 있습니다. config/app.php에서 설정하세요.');
        }

        $key = str_starts_with($rawKey, 'base64:')
            ? base64_decode(substr($rawKey, 7), true) // strict mode
            : $rawKey;

        if ($key === false || $key === '') {
            throw new \RuntimeException('encrypt.key의 Base64 형식이 올바르지 않습니다.');
        }

        // 키 길이가 SODIUM_CRYPTO_SECRETBOX_KEYBYTES가 아니면 해시로 맞춤
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $key = sodium_crypto_generichash($key, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }

        return self::$instance = new self(key: $key);
    }

    /** 대칭키 암호화 (nonce + ciphertext → base64) */
    public function seal(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        return base64_encode($nonce . $ciphertext);
    }

    /** 대칭키 복호화 */
    public function open(string $encrypted): ?string
    {
        $decoded = base64_decode($encrypted, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        return $plaintext === false ? null : $plaintext;
    }

    /** HMAC 서명 */
    public function sign(string $message): string
    {
        return sodium_crypto_auth($message, $this->key);
    }

    /** HMAC 서명 검증 */
    public function verify(string $message, string $signature): bool
    {
        return sodium_crypto_auth_verify($signature, $message, $this->key);
    }

    /** 소멸 시 키 메모리 안전 정리 */
    public function __destruct()
    {
        sodium_memzero($this->key);
    }
}
