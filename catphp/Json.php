<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Json — JSON 응답 (통일 포맷)
 *
 * 응답 구조: {"ok": bool, "data": ..., "error": ..., "meta": ...}
 */
final class Json
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 성공 응답 */
    public function ok(mixed $data = null, int $code = 200): never
    {
        $this->send($code, ['ok' => true, 'data' => $data]);
    }

    /** 실패 응답 (검증 오류 등) */
    public function fail(string $message, mixed $details = null, int $code = 422): never
    {
        $error = ['message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        $this->send($code, ['ok' => false, 'error' => $error]);
    }

    /** 에러 응답 */
    public function error(string $message, int $code = 500): never
    {
        $this->send($code, [
            'ok' => false,
            'error' => ['message' => $message, 'code' => $code],
        ]);
    }

    /** 페이지네이션 응답 */
    public function paginated(array $data, int $total, int $page, int $perPage, int $code = 200): never
    {
        $lastPage = (int) ceil($total / max($perPage, 1));
        $this->send($code, [
            'ok'   => true,
            'data' => $data,
            'meta' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
        ]);
    }

    /** JSON 전송 (내부) */
    public function send(int $code, array $payload): never
    {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
