<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Auth — 인증 (JWT + Argon2id/Bcrypt)
 *
 * 사용법:
 *   auth()->hashPassword($pw);               // 비밀번호 해싱
 *   auth()->verifyPassword($pw, $hash);       // 비밀번호 검증
 *   auth()->createToken(['sub' => $userId]);   // JWT 생성
 *   auth()->verifyToken($token);              // JWT 검증
 *   auth()->bearer();                          // Bearer 토큰 추출
 *   auth()->login($user);                      // 세션 로그인
 *   auth()->user();                            // 세션 사용자
 *   auth()->apiUser();                         // API 인증 사용자
 *   auth()->id();                              // 현재 사용자 ID
 *   auth()->needsRehash($hash);               // 리해시 필요 여부
 *
 * @config array{
 *     secret: string,   // JWT 서명 비밀키
 *     ttl?: int,        // 토큰 유효시간 (초, 기본 86400)
 *     algo?: string,    // 해싱 알고리즘 ('argon2id' | 'bcrypt' | 'argon2i')
 * } auth  → config('auth.secret')
 */
final class Auth
{
    private static ?self $instance = null;

    /** @var ?array JWT 검증 후 저장된 API 사용자 페이로드 */
    private ?array $apiPayload = null;

    private function __construct(
        private readonly string $secret,
        private readonly int $ttl,
        private readonly string $passwordAlgo,
    ) {}

    public static function getInstance(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $secret = \config('auth.secret') ?? '';
        if ($secret === '') {
            throw new \RuntimeException('auth.secret 설정이 비어 있습니다. config/app.php에서 설정하세요.');
        }
        // HMAC-SHA256 권장 키 길이: 32바이트 이상 — 짧은 키는 브루트포스 위험
        if (strlen($secret) < 32) {
            $debug = (bool) \config('app.debug', false);
            if (!$debug) {
                // 운영환경: 짧은 시크릿은 보안 위협 — 부팅 차단
                throw new \RuntimeException(
                    'auth.secret이 32바이트 미만입니다. 운영환경에서는 32바이트 이상 필수입니다.'
                );
            }
            // 개발환경: 경고만 기록
            if (class_exists('Cat\\Log', false)) {
                \logger()->warn('auth.secret이 32바이트 미만입니다. 보안을 위해 32바이트 이상 설정을 권장합니다.');
            }
        }

        $algoStr = strtolower((string) (\config('auth.algo') ?? 'argon2id'));
        $algo = match ($algoStr) {
            'bcrypt'           => PASSWORD_BCRYPT,
            'argon2i'          => PASSWORD_ARGON2I,
            default            => PASSWORD_ARGON2ID,
        };

        // 해싱 알고리즘 가용성 검증 (PHP 빌드에 따라 argon2 미지원 가능)
        $available = password_algos();
        if (!in_array($algo, $available, true)) {
            // Bcrypt로 자동 폴백 + 경고
            if (class_exists('Cat\\Log', false)) {
                \logger()->warn("auth.algo '{$algoStr}'은(는) 이 PHP 빌드에서 지원되지 않습니다. bcrypt로 대체합니다.", [
                    'available' => $available,
                ]);
            }
            $algo = PASSWORD_BCRYPT;
        }

        return self::$instance = new self(
            secret: $secret,
            ttl: (int) (\config('auth.ttl') ?? 86400),
            passwordAlgo: $algo,
        );
    }

    /**
     * Swoole 요청 간 상태 초기화 (프레임워크 내부용)
     *
     * 이전 요청의 JWT 페이로드 캐시 제거로 인증 우회 방지.
     * `Swoole.php::handleRequest()` 시작부에서 호출.
     */
    public static function resetInstance(): void
    {
        if (self::$instance === null) {
            return;
        }
        self::$instance->apiPayload = null;
    }

    // ── 비밀번호 ──

    /** 비밀번호 해싱 (Bcrypt 72바이트 제한 검증 포함) */
    public function hashPassword(
        string $password
    ): string {
        if ($this->passwordAlgo === PASSWORD_BCRYPT && strlen($password) > 72) {
            throw new \InvalidArgumentException('Bcrypt는 72바이트까지만 지원합니다. 더 긴 비밀번호는 argon2id를 사용하세요.');
        }
        return password_hash($password, $this->passwordAlgo);
    }

    /** 비밀번호 검증 */
    public function verifyPassword(
        string $password,
        string $hash
    ): bool {
        return password_verify($password, $hash);
    }

    /** 비밀번호 리해시 필요 여부 (알고리즘/옵션 변경 시) */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->passwordAlgo);
    }

    // ── JWT ──

    /** JWT 토큰 생성 */
    public function createToken(array $payload, ?int $ttl = null): string
    {
        $headerJson = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        if ($headerJson === false) {
            throw new \RuntimeException('JWT 헤더 JSON 인코딩 실패');
        }
        $header = $this->base64UrlEncode($headerJson);

        $payload['iat'] = time();
        $payload['exp'] = time() + ($ttl ?? $this->ttl);

        $payloadJson = json_encode($payload);
        if ($payloadJson === false) {
            throw new \RuntimeException('JWT 페이로드 JSON 인코딩 실패');
        }
        $payloadEncoded = $this->base64UrlEncode($payloadJson);

        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payloadEncoded}", $this->secret, true)
        );

        return "{$header}.{$payloadEncoded}.{$signature}";
    }

    /** JWT 토큰 검증 (페이로드 반환 또는 null) */
    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // 서명 검증 (hash_equals로 타이밍 공격 방지)
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $data = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($data)) {
            return null;
        }

        $now = time();

        // 만료 확인
        if (isset($data['exp']) && $data['exp'] < $now) {
            return null;
        }

        // nbf (not before) 확인 — 아직 유효하지 않은 토큰 거부
        if (isset($data['nbf']) && $data['nbf'] > $now) {
            return null;
        }

        return $data;
    }

    /**
     * Authorization: Bearer 토큰 추출
     *
     * Apache CGI/FastCGI 환경에서도 Authorization 헤더를 감지합니다.
     */
    public function bearer(): ?string
    {
        // 표준 서버 변수
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        // Apache mod_rewrite 환경 폴백
        if ($header === '' && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if ($apacheHeaders !== false) {
                // apache_request_headers()는 원본 케이스 유지 — 대소문자 무관 검색
                foreach ($apacheHeaders as $name => $value) {
                    if (strcasecmp($name, 'Authorization') === 0) {
                        $header = $value;
                        break;
                    }
                }
            }
        }

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    // ── API 인증 ──

    /** JWT 검증 후 API 사용자 페이로드 저장 */
    public function setApiUser(array $payload): void
    {
        $this->apiPayload = $payload;
    }

    /** API 인증된 사용자 페이로드 반환 */
    public function apiUser(): ?array
    {
        return $this->apiPayload;
    }

    // ── 세션 인증 ──

    /** 세션 기반 현재 로그인 사용자 */
    public function user(): ?array
    {
        return \session('_catphp_user');
    }

    /** 현재 인증 사용자 ID (세션 또는 API, 없으면 null) */
    public function id(): int|string|null
    {
        // API 인증 우선
        if ($this->apiPayload !== null) {
            return $this->apiPayload['sub'] ?? $this->apiPayload['id'] ?? null;
        }
        $user = $this->user();
        return $user['id'] ?? null;
    }

    /** 로그인 상태 확인 (세션 또는 API) */
    public function check(): bool
    {
        return $this->user() !== null || $this->apiPayload !== null;
    }

    /** 비로그인 상태 확인 */
    public function guest(): bool
    {
        return !$this->check();
    }

    /** 세션에 사용자 저장 (로그인) */
    public function login(array $user): void
    {
        \session()->set('_catphp_user', $user);
        \session()->regenerate();
    }

    /** 세션에서 사용자 제거 (로그아웃) */
    public function logout(): void
    {
        $this->apiPayload = null;
        \session()->forget('_catphp_user');
        \session()->destroy();
    }

    // ── 내부 ──

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
