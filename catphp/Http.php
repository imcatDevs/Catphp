<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Http — HTTP 클라이언트 (cURL)
 *
 * 체이닝 API로 외부 HTTP 요청을 보낸다.
 */
final class Http
{
    private static ?self $instance = null;

    /** @var array<string, string> 요청 헤더 */
    private array $headers = [];
    private int $timeoutSec = 30;
    private ?string $baseUrl = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 기본 URL 설정 */
    public function base(string $url): self
    {
        $c = clone $this;
        $c->baseUrl = rtrim($url, '/');
        return $c;
    }

    /** 요청 헤더 설정 (CRLF 인젝션 방어) */
    public function header(string $name, string $value): self
    {
        $c = clone $this;
        $c->headers[str_replace(["\r", "\n", "\0"], '', $name)] = str_replace(["\r", "\n", "\0"], '', $value);
        return $c;
    }

    /** 타임아웃 설정 */
    public function timeout(int $seconds): self
    {
        $c = clone $this;
        $c->timeoutSec = $seconds;
        return $c;
    }

    /** GET 요청 */
    public function get(string $url, array $query = []): HttpResponse
    {
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    /** POST 요청 */
    public function post(string $url, array|string $data = []): HttpResponse
    {
        return $this->request('POST', $url, $data);
    }

    /** PUT 요청 */
    public function put(string $url, array|string $data = []): HttpResponse
    {
        return $this->request('PUT', $url, $data);
    }

    /** DELETE 요청 */
    public function delete(string $url, array|string $data = []): HttpResponse
    {
        return $this->request('DELETE', $url, $data);
    }

    /** PATCH 요청 */
    public function patch(string $url, array|string $data = []): HttpResponse
    {
        return $this->request('PATCH', $url, $data);
    }

    /** JSON POST (Content-Type 자동 설정) */
    public function json(string $url, array $data): HttpResponse
    {
        return $this->header('Content-Type', 'application/json')
            ->post($url, json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /** 파일 업로드 (multipart/form-data) */
    public function upload(string $url, string $filePath, string $fieldName = 'file', array $extra = []): HttpResponse
    {
        if (!is_file($filePath)) {
            return new HttpResponse(0, '', "파일을 찾을 수 없습니다: {$filePath}");
        }

        $postData = $extra;
        $postData[$fieldName] = new \CURLFile($filePath, mime_content_type($filePath) ?: 'application/octet-stream', basename($filePath));

        $fullUrl = ($this->baseUrl !== null && !str_starts_with($url, 'http'))
            ? $this->baseUrl . '/' . ltrim($url, '/')
            : $url;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSec,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HEADER         => true,
        ]);

        if (!empty($this->headers)) {
            $formatted = [];
            foreach ($this->headers as $name => $value) {
                $formatted[] = "{$name}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted);
        }

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return new HttpResponse(0, '', $error);
        }

        return new HttpResponse($statusCode, substr((string) $raw, $headerSize), '', substr((string) $raw, 0, $headerSize));
    }

    /** 파일 다운로드 */
    public function download(string $url, string $savePath): bool
    {
        $response = $this->get($url);
        if ($response->status() >= 200 && $response->status() < 300) {
            return file_put_contents($savePath, $response->body(), LOCK_EX) !== false;
        }
        return false;
    }

    /** cURL 요청 실행 */
    private function request(string $method, string $url, array|string $data = []): HttpResponse
    {
        if ($this->baseUrl !== null && !str_starts_with($url, 'http')) {
            $url = $this->baseUrl . '/' . ltrim($url, '/');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeoutSec,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HEADER         => true,
        ]);

        // 헤더 설정
        if (!empty($this->headers)) {
            $formatted = [];
            foreach ($this->headers as $name => $value) {
                $formatted[] = "{$name}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted);
        }

        // 요청 바디
        if ($method !== 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return new HttpResponse(0, '', $error);
        }

        $headerStr = substr((string) $raw, 0, $headerSize);
        $body = substr((string) $raw, $headerSize);

        return new HttpResponse($statusCode, $body, '', $headerStr);
    }
}

/**
 * HTTP 응답 값 객체
 */
final class HttpResponse
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $bodyStr,
        private readonly string $error = '',
        private readonly string $rawHeaders = '',
    ) {}

    public function status(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->bodyStr;
    }

    /** JSON 디코딩 */
    public function json(): ?array
    {
        $data = json_decode($this->bodyStr, true);
        return is_array($data) ? $data : null;
    }

    public function ok(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function error(): string
    {
        return $this->error;
    }

    /** 응답 헤더 파싱 */
    public function headers(): array
    {
        $headers = [];
        foreach (explode("\r\n", $this->rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[trim($name)] = trim($value);
            }
        }
        return $headers;
    }
}
