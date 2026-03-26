<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Csrf — CSRF 보호
 *
 * 토큰 생성, 폼 hidden input, 미들웨어 검증.
 */
final class Csrf
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 세션에 저장된 원본 CSRF 토큰 생성/반환 */
    public function token(): string
    {
        $token = \session('_csrf_token');
        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            \session()->set('_csrf_token', $token);
        }
        return $token;
    }

    /**
     * 마스킹된 토큰 반환 (BREACH 공격 방어)
     *
     * 매 호출마다 랜덤 마스크를 생성하여 XOR로 토큰을 마스킹.
     * HTTPS + gzip 환경에서 응답 압축률 차이를 이용한 토큰 추출 공격을 방지.
     */
    public function maskedToken(): string
    {
        $raw = $this->token();
        $token = hex2bin($raw);
        if ($token === false) {
            return $raw;
        }
        $mask = random_bytes(strlen($token));
        return bin2hex($mask) . bin2hex($mask ^ $token);
    }

    /** 폼용 hidden input HTML (마스킹된 토큰) */
    public function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($this->maskedToken()) . '">';
    }

    /** 토큰 검증 (마스킹 + 원본 모두 지원) */
    public function verify(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // GET, HEAD, OPTIONS는 검증 불필요
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $expected = \session('_csrf_token');
        if ($expected === null) {
            return false;
        }

        // POST 데이터 또는 헤더에서 토큰 추출
        $submitted = (string) ($_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '');

        if ($submitted === '') {
            return false;
        }

        // 원본 토큰 직접 비교 (하위 호환)
        if (hash_equals($expected, $submitted)) {
            return true;
        }

        // 마스킹된 토큰 언마스킹 후 비교
        return $this->verifyMasked($expected, $submitted);
    }

    /** 마스킹된 토큰 언마스킹 + 검증 */
    private function verifyMasked(string $expected, string $submitted): bool
    {
        $bytes = @hex2bin($submitted);
        if ($bytes === false) {
            return false;
        }

        $len = strlen($bytes);
        if ($len === 0 || $len % 2 !== 0) {
            return false;
        }

        $half = $len / 2;
        $mask = substr($bytes, 0, $half);
        $encrypted = substr($bytes, $half);
        $decrypted = bin2hex($mask ^ $encrypted);

        return hash_equals($expected, $decrypted);
    }

    /** 미들웨어: 검증 실패 시 403 반환 (router()->use(csrf()->middleware())) */
    public function middleware(): callable
    {
        return function (): ?bool {
            if (!$this->verify()) {
                $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
                if ($isApi) {
                    \json()->error('CSRF token mismatch', 403);
                } else {
                    http_response_code(403);
                    echo '<h1>403 Forbidden</h1><p>CSRF 토큰이 유효하지 않습니다.</p>';
                    exit;
                }
            }
            return null;
        };
    }
}
