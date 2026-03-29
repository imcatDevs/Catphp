<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Cors — CORS 관리
 *
 * @config array{
 *     origins?: array<string>,   // 허용 오리진 (기본 ['*'])
 *     methods?: array<string>,   // 허용 메서드
 *     headers?: array<string>,   // 허용 헤더
 *     max_age?: int,             // Preflight 캐시 (초)
 * } cors  → config('cors.origins')
 */
final class Cors
{
    private static ?self $instance = null;

    private function __construct(
        private readonly array $origins,
        private readonly array $methods,
        private readonly array $allowedHeaders,
        private readonly int $maxAge,
    ) {}

    public static function getInstance(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $origins = \config('cors.origins') ?? ['*'];

        // 운영환경 wildcard origin 경고 — credentials 미사용 + 보안 위험
        if (in_array('*', $origins, true) && !(bool) \config('app.debug', false)) {
            if (class_exists('Cat\\Log', false)) {
                \logger()->warn(
                    'cors.origins가 와일드카드(*)입니다. 운영환경에서는 허용 도메인을 명시적으로 설정하세요.'
                );
            }
        }

        return self::$instance = new self(
            origins: $origins,
            methods: \config('cors.methods') ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowedHeaders: \config('cors.headers') ?? ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
            maxAge: (int) (\config('cors.max_age') ?? 86400),
        );
    }

    /** CORS 헤더 전송 + Preflight 자동 처리 */
    public function handle(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        // CRLF 인젝션 방어 + URL 형식 검증
        $origin = str_replace(["\r", "\n", "\0"], '', $origin);
        if ($origin !== '' && !preg_match('#^https?://#i', $origin)) {
            $origin = '';
        }

        if ($origin !== '' && $this->isOriginAllowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif (in_array('*', $this->origins, true)) {
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $this->methods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->allowedHeaders));
        header('Access-Control-Max-Age: ' . $this->maxAge);

        // Credentials는 와일드카드 Origin과 동시 사용 불가 (CORS 스펙)
        if (!in_array('*', $this->origins, true) && $origin !== '') {
            header('Access-Control-Allow-Credentials: true');
        }

        // Preflight OPTIONS 요청 자동 처리
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            if (!headers_sent()) {
                header('Content-Length: 0');
            }
            exit;
        }
    }

    /** 오리진 허용 확인 */
    private function isOriginAllowed(string $origin): bool
    {
        if (in_array('*', $this->origins, true)) {
            return true;
        }
        return in_array($origin, $this->origins, true);
    }

    /** 허용 오리진 설정 (체이닝) */
    public function origins(array $origins): self
    {
        return $this->cloneWith(origins: $origins);
    }

    /** 허용 메서드 설정 (체이닝) */
    public function methods(array $methods): self
    {
        return $this->cloneWith(methods: $methods);
    }

    /** 허용 헤더 설정 (체이닝) */
    public function headers(array $headers): self
    {
        return $this->cloneWith(allowedHeaders: $headers);
    }

    /** 허용 목록 설정 (체이닝 종합) */
    public function allow(array $origins = [], array $methods = [], array $headers = []): self
    {
        return $this->cloneWith(
            origins: $origins ?: null,
            methods: $methods ?: null,
            allowedHeaders: $headers ?: null,
        );
    }

    /** readonly 프로퍼티 호환 복제 + 싱글턴 캐시 갱신 */
    private function cloneWith(?array $origins = null, ?array $methods = null, ?array $allowedHeaders = null): self
    {
        $new = new self(
            $origins ?? $this->origins,
            $methods ?? $this->methods,
            $allowedHeaders ?? $this->allowedHeaders,
            $this->maxAge,
        );

        // 싱글턴 캐시 갱신 — cors()->origins([...])->handle() 이후 cors()->handle() 호출 시에도 설정 유지
        self::$instance = $new;

        return $new;
    }
}
