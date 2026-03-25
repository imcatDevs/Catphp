<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Api — API 미들웨어 번들
 *
 * CORS + Rate Limit + Auth + Guard + JSON 검증을 한 줄로 적용.
 *
 * 사용법:
 *   api()->cors()->rateLimit(60, 100)->guard()->apply();
 *   api()->cors()->auth()->jsonOnly()->apply();
 *   api()->rateLimit(60, 100, 'posts')->apply();  // 엔드포인트별 키
 *   api()->preset()->apply();                       // config 기본값 사용
 *
 * @config array{
 *     rate_window?: int,   // 기본 Rate Limit 윈도우 (초)
 *     rate_max?: int,      // 기본 Rate Limit 최대 횟수
 *     cors?: bool,         // 기본 CORS 적용 여부
 *     guard?: bool,        // 기본 Guard 적용 여부
 *     json_only?: bool,    // 기본 JSON Content-Type 강제 여부
 * } api  → config('api.rate_window')
 */
final class Api
{
    private static ?self $instance = null;

    private bool $applyCors = false;
    private ?int $rateWindow = null;
    private ?int $rateMax = null;
    private string $rateKey = 'api';
    private bool $applyAuth = false;
    private bool $applyGuard = false;
    private bool $applyJsonOnly = false;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** CORS 적용 */
    public function cors(): self
    {
        $c = clone $this;
        $c->applyCors = true;
        return $c;
    }

    /**
     * 레이트 리미트 적용
     *
     * @param int    $window 시간 윈도우 (초)
     * @param int    $max    최대 허용 횟수
     * @param string $key    Rate Limit 키 (엔드포인트별 분리 가능)
     */
    public function rateLimit(int $window, int $max, string $key = 'api'): self
    {
        $c = clone $this;
        $c->rateWindow = $window;
        $c->rateMax = $max;
        $c->rateKey = $key;
        return $c;
    }

    /** 인증 적용 */
    public function auth(): self
    {
        $c = clone $this;
        $c->applyAuth = true;
        return $c;
    }

    /** Guard 적용 */
    public function guard(): self
    {
        $c = clone $this;
        $c->applyGuard = true;
        return $c;
    }

    /** JSON Content-Type 강제 (POST/PUT/PATCH 요청에 application/json 필수) */
    public function jsonOnly(): self
    {
        $c = clone $this;
        $c->applyJsonOnly = true;
        return $c;
    }

    /** config 기본값으로 프리셋 로드 */
    public function preset(): self
    {
        $c = clone $this;

        if ((bool) \config('api.cors', false)) {
            $c->applyCors = true;
        }
        if ((bool) \config('api.guard', false)) {
            $c->applyGuard = true;
        }
        if ((bool) \config('api.json_only', false)) {
            $c->applyJsonOnly = true;
        }

        $rateWindow = \config('api.rate_window');
        $rateMax = \config('api.rate_max');
        if ($rateWindow !== null && $rateMax !== null) {
            $c->rateWindow = (int) $rateWindow;
            $c->rateMax = (int) $rateMax;
        }

        return $c;
    }

    /** 설정된 미들웨어 모두 실행 */
    public function apply(): void
    {
        // CORS (OPTIONS preflight 시 여기서 exit)
        if ($this->applyCors) {
            \cors()->handle();
        }

        // JSON Content-Type 강제 (POST/PUT/PATCH)
        if ($this->applyJsonOnly) {
            $this->enforceJsonContentType();
        }

        // Rate Limit + 표준 응답 헤더
        if ($this->rateWindow !== null && $this->rateMax !== null) {
            $this->applyRateLimit();
        }

        // Auth (Bearer 토큰 검증 → 페이로드 저장)
        if ($this->applyAuth) {
            $this->applyAuthentication();
        }

        // Guard (공격 감지 + 요청 크기 제한)
        if ($this->applyGuard) {
            $this->applyGuardMiddleware();
        }
    }

    // ── 내부 미들웨어 ──

    /** Rate Limit 실행 + RFC 6585 표준 헤더 전송 */
    private function applyRateLimit(): void
    {
        $window = $this->rateWindow;
        $max = $this->rateMax;
        $key = $this->rateKey;

        $allowed = \rate()->limit($key, $window, $max);
        $remaining = \rate()->remaining($key, $window, $max);

        // 표준 Rate Limit 헤더 (RFC 6585 + draft-ietf-httpapi-ratelimit-headers)
        if (!headers_sent()) {
            header("X-RateLimit-Limit: {$max}");
            header("X-RateLimit-Remaining: {$remaining}");
            header("X-RateLimit-Reset: {$window}");
        }

        if (!$allowed) {
            if (!headers_sent()) {
                header("Retry-After: {$window}");
            }
            \json()->error('Too Many Requests', 429);
        }
    }

    /** Bearer 토큰 검증 + 페이로드 저장 */
    private function applyAuthentication(): void
    {
        $token = \auth()->bearer();
        if ($token === null) {
            \json()->error('Unauthorized', 401);
        }
        $payload = \auth()->verifyToken($token);
        if ($payload === null) {
            \json()->error('Invalid or expired token', 401);
        }
        \auth()->setApiUser($payload);
    }

    /** Guard 미들웨어: 요청 크기 제한 + 입력 살균 */
    private function applyGuardMiddleware(): void
    {
        if (!\guard()->maxBodySize()) {
            \json()->error('Payload Too Large', 413);
        }
        $sanitized = \guard()->all();
        \input(data: $sanitized);
    }

    /** POST/PUT/PATCH 요청의 Content-Type이 JSON인지 검증 */
    private function enforceJsonContentType(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, '/json') && !str_contains($contentType, '+json')) {
            \json()->error('Content-Type must be application/json', 415);
        }
    }
}
