<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Json — JSON 응답 (CatUI 통합 포맷)
 *
 * 응답 구조: {success: bool, statusCode: int, data: mixed, message: string, error: object|null, timestamp: int}
 *
 * CatUI APIUtil과 호환되는 표준 응답 형식.
 */
final class Json
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 성공 응답
     *
     * @param mixed $data 응답 데이터
     * @param string $message 성공 메시지
     * @param int $statusCode HTTP 상태 코드
     */
    public function ok(mixed $data = null, string $message = 'Success', int $statusCode = 200): never
    {
        $this->send($statusCode, [
            'success'    => true,
            'statusCode' => $statusCode,
            'data'       => $data,
            'message'    => $message,
            'error'      => null,
            'timestamp'  => time(),
        ]);
    }

    /**
     * 생성 성공 응답 (201)
     */
    public function created(mixed $data = null, string $message = 'Created'): never
    {
        $this->ok($data, $message, 201);
    }

    /**
     * 삭제 성공 응답 (204 - no content)
     */
    public function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    /**
     * 실패 응답 (검증 오류, 비즈니스 로직 오류 등)
     *
     * @param string $message 에러 메시지
     * @param int $statusCode HTTP 상태 코드
     * @param array|null $details 상세 에러 정보
     */
    public function fail(string $message, int $statusCode = 422, ?array $details = null): never
    {
        $error = [
            'message' => $message,
            'name'    => 'ValidationError',
            'type'    => 'validation',
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        $this->send($statusCode, [
            'success'    => false,
            'statusCode' => $statusCode,
            'data'       => null,
            'message'    => $message,
            'error'      => $error,
            'timestamp'  => time(),
        ]);
    }

    /**
     * 에러 응답 (서버 에러, 시스템 에러 등)
     *
     * @param string $message 에러 메시지
     * @param int $statusCode HTTP 상태 코드
     * @param string $type 에러 타입 (server, auth, notfound, forbidden 등)
     */
    public function error(string $message, int $statusCode = 500, string $type = 'server'): never
    {
        $errorNames = [
            400 => 'BadRequest',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'NotFound',
            408 => 'RequestTimeout',
            422 => 'ValidationError',
            429 => 'TooManyRequests',
            500 => 'InternalServerError',
            502 => 'BadGateway',
            503 => 'ServiceUnavailable',
        ];

        $this->send($statusCode, [
            'success'    => false,
            'statusCode' => $statusCode,
            'data'       => null,
            'message'    => $message,
            'error'      => [
                'message' => $message,
                'name'    => $errorNames[$statusCode] ?? 'Error',
                'type'    => $type,
            ],
            'timestamp'  => time(),
        ]);
    }

    /**
     * 인증 필요 응답 (401)
     */
    public function unauthorized(string $message = 'Unauthorized'): never
    {
        $this->error($message, 401, 'auth');
    }

    /**
     * 권한 없음 응답 (403)
     */
    public function forbidden(string $message = 'Forbidden'): never
    {
        $this->error($message, 403, 'forbidden');
    }

    /**
     * 찾을 수 없음 응답 (404)
     */
    public function notFound(string $message = 'Not Found'): never
    {
        $this->error($message, 404, 'notfound');
    }

    /**
     * 페이지네이션 응답
     *
     * @param array $items 아이템 배열
     * @param int $page 현재 페이지
     * @param int $limit 페이지당 아이템 수
     * @param int $total 전체 아이템 수
     * @param string $message 성공 메시지
     */
    public function paginated(array $items, int $page, int $limit, int $total, string $message = 'Success'): never
    {
        $totalPages = (int) ceil($total / max($limit, 1));

        $this->send(200, [
            'success'    => true,
            'statusCode' => 200,
            'data'       => [
                'items' => $items,
                'pagination' => [
                    'page'       => $page,
                    'limit'      => $limit,
                    'total'      => $total,
                    'totalPages' => $totalPages,
                    'hasNext'    => $page < $totalPages,
                    'hasPrev'    => $page > 1,
                ],
            ],
            'message'    => $message,
            'error'      => null,
            'timestamp'  => time(),
        ]);
    }

    /**
     * JSON 전송 (내부)
     */
    public function send(int $statusCode, array $payload): never
    {
        http_response_code($statusCode);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
