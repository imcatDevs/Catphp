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

    /** CSRF 토큰 생성/반환 */
    public function token(): string
    {
        $token = \session('_csrf_token');
        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            \session()->set('_csrf_token', $token);
        }
        return $token;
    }

    /** 폼용 hidden input HTML */
    public function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($this->token()) . '">';
    }

    /** 토큰 검증 (bool 반환) */
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
        $token = $_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';

        return hash_equals($expected, (string) $token);
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
