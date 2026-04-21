<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Hash — 파일 체크섬 / 무결성 검증
 *
 * 사용법:
 *   hash()->file('/path/to/file.zip');                  // SHA-256 해시
 *   hash()->file('/path/to/file.zip', 'md5');           // MD5 해시
 *   hash()->verify('/path/to/file.zip', $expectedHash); // 무결성 검증
 *   hash()->string('hello world');                       // 문자열 해시
 *   hash()->hmac('message', 'secret');                   // HMAC 서명
 *   hash()->equals($hash1, $hash2);                     // 타이밍 안전 비교
 */
final class Hash
{
    private static ?self $instance = null;

    private string $defaultAlgo;

    private function __construct()
    {
        $this->defaultAlgo = (string) config('hash.algo', 'sha256');
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 파일 해싱 ──

    /** 파일 해시 계산 */
    public function file(string $path, ?string $algo = null): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException("파일 없음: {$path}");
        }
        $hash = hash_file($algo ?? $this->defaultAlgo, $path);
        if ($hash === false) {
            throw new \RuntimeException("해시 계산 실패: {$path}");
        }
        return $hash;
    }

    /** 파일 무결성 검증 */
    public function verify(string $path, string $expectedHash, ?string $algo = null): bool
    {
        return hash_equals($expectedHash, $this->file($path, $algo));
    }

    /** 파일 체크섬 (CRC32) */
    public function checksum(string $path): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException("파일 없음: {$path}");
        }
        return hash_file('crc32b', $path);
    }

    // ── 문자열 해싱 ──

    /** 문자열 해시 */
    public function string(string $data, ?string $algo = null): string
    {
        return hash($algo ?? $this->defaultAlgo, $data);
    }

    /** HMAC 서명 */
    public function hmac(string $data, string $key, ?string $algo = null): string
    {
        return hash_hmac($algo ?? $this->defaultAlgo, $data, $key);
    }

    /** HMAC 검증 */
    public function verifyHmac(string $data, string $expectedMac, string $key, ?string $algo = null): bool
    {
        return hash_equals($expectedMac, $this->hmac($data, $key, $algo));
    }

    // ── 비밀번호 해싱 ──

    /** 비밀번호 해시 (Argon2id/Bcrypt) */
    public function password(string $password): string
    {
        $algo = strtolower((string) config('auth.algo', 'Argon2id'));

        $phpAlgo = match ($algo) {
            'argon2id' => PASSWORD_ARGON2ID,
            'bcrypt'   => PASSWORD_BCRYPT,
            default    => PASSWORD_DEFAULT,
        };

        if ($phpAlgo === PASSWORD_BCRYPT && strlen($password) > 72) {
            throw new \InvalidArgumentException('Bcrypt는 72바이트까지만 지원합니다. 더 긴 비밀번호는 argon2id를 사용하세요.');
        }

        return password_hash($password, $phpAlgo);
    }

    /** 비밀번호 검증 */
    public function passwordVerify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /** 비밀번호 리해시 필요 여부 */
    public function passwordNeedsRehash(string $hash): bool
    {
        $algo = strtolower((string) config('auth.algo', 'Argon2id'));
        $phpAlgo = match ($algo) {
            'argon2id' => PASSWORD_ARGON2ID,
            'bcrypt'   => PASSWORD_BCRYPT,
            default    => PASSWORD_DEFAULT,
        };
        return password_needs_rehash($hash, $phpAlgo);
    }

    // ── 유틸리티 ──

    /** 타이밍 안전 해시 비교 */
    public function equals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /** 사용 가능한 해시 알고리즘 목록 */
    public function algorithms(): array
    {
        return hash_algos();
    }

    /** 디렉토리 전체 해시 (매니페스트) */
    public function directory(string $path, ?string $algo = null): array
    {
        if (!is_dir($path)) {
            throw new \RuntimeException("디렉토리 없음: {$path}");
        }

        $manifest = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($path) + 1));
                $manifest[$relative] = $this->file($file->getPathname(), $algo);
            }
        }

        ksort($manifest);
        return $manifest;
    }

    /** 디렉토리 매니페스트 기반 무결성 검증 */
    public function verifyDirectory(string $path, array $manifest, ?string $algo = null): array
    {
        $current = $this->directory($path, $algo);
        $diff = [
            'modified' => [],
            'added'    => [],
            'removed'  => [],
        ];

        foreach ($manifest as $file => $hash) {
            if (!isset($current[$file])) {
                $diff['removed'][] = $file;
            } elseif ($current[$file] !== $hash) {
                $diff['modified'][] = $file;
            }
        }

        foreach ($current as $file => $hash) {
            if (!isset($manifest[$file])) {
                $diff['added'][] = $file;
            }
        }

        return $diff;
    }
}
