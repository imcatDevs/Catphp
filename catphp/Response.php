<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Response — HTTP 응답 빌더
 *
 * 헤더, 상태 코드, 리다이렉트, 다운로드, 스트리밍 등 응답 생성.
 *
 * 사용법:
 *   response()->html('<h1>Hello</h1>');
 *   response()->redirect('/login');
 *   response()->download('/path/file.pdf', 'report.pdf');
 *   response()->stream(fopen('big.csv', 'r'), 'data.csv');
 *   response()->noCache()->html($content);
 *   response()->status(404)->html('Not Found');
 */
final class Response
{
    private static ?self $instance = null;

    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<array{name:string, value:string, options:array}> */
    private array $cookies = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 상태 코드 ──

    /** HTTP 상태 코드 설정 */
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /** 현재 상태 코드 */
    public function getStatus(): int
    {
        return $this->statusCode;
    }

    // ── 헤더 ──

    /** 응답 헤더 설정 */
    public function header(string $name, string $value): self
    {
        // CRLF 인젝션 방어 (HTTP Response Splitting 차단)
        $name = str_replace(["\r", "\n"], '', $name);
        $value = str_replace(["\r", "\n"], '', $value);
        $this->headers[$name] = $value;
        return $this;
    }

    /** 여러 헤더 한 번에 설정 */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->header((string) $name, (string) $value);
        }
        return $this;
    }

    /** Content-Type 설정 */
    public function contentType(string $type, string $charset = 'UTF-8'): self
    {
        $this->headers['Content-Type'] = "{$type}; charset={$charset}";
        return $this;
    }

    /** 캐시 비활성화 */
    public function noCache(): self
    {
        $this->headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
        $this->headers['Pragma'] = 'no-cache';
        $this->headers['Expires'] = '0';
        return $this;
    }

    /** 캐시 활성화 */
    public function cache(int $seconds): self
    {
        $this->headers['Cache-Control'] = "public, max-age={$seconds}";
        $this->headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT';
        return $this;
    }

    // ── 쿠키 ──

    /** 응답 쿠키 추가 */
    public function cookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $this->cookies[] = [
            'name'  => $name,
            'value' => $value,
            'options' => [
                'expires'  => $expires > 0 ? time() + $expires : 0,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly'  => $httpOnly,
                'samesite' => $sameSite,
            ],
        ];
        return $this;
    }

    /** 쿠키 삭제 */
    public function forgetCookie(string $name, string $path = '/', string $domain = ''): self
    {
        $this->cookies[] = [
            'name'  => $name,
            'value' => '',
            'options' => [
                'expires'  => time() - 3600,
                'path'     => $path,
                'domain'   => $domain,
                'secure'   => false,
                'httponly'  => true,
                'samesite' => 'Lax',
            ],
        ];
        return $this;
    }

    // ── 응답 본문 ──

    /** HTML 응답 */
    public function html(string $content, int $status = 0): never
    {
        if ($status > 0) {
            $this->statusCode = $status;
        }
        $this->contentType('text/html');
        $this->send($content);
    }

    /** 텍스트 응답 */
    public function text(string $content, int $status = 0): never
    {
        if ($status > 0) {
            $this->statusCode = $status;
        }
        $this->contentType('text/plain');
        $this->send($content);
    }

    /** XML 응답 */
    public function xml(string $content, int $status = 0): never
    {
        if ($status > 0) {
            $this->statusCode = $status;
        }
        $this->contentType('application/xml');
        $this->send($content);
    }

    /** 빈 응답 */
    public function noContent(): never
    {
        $this->statusCode = 204;
        $this->send('');
    }

    // ── 리다이렉트 ──

    /** 리다이렉트 */
    public function redirect(string $url, int $status = 302): never
    {
        // 오픈 리다이렉트 방어: 외부 URL 차단 (config으로 허용 도메인 설정 가능)
        if (preg_match('#^https?://#i', $url)) {
            $allowed = (array) \config('response.allowed_hosts', []);
            $host = (string) parse_url($url, PHP_URL_HOST);
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            // 빈 allowed_hosts = 외부 리다이렉트 불허 (자기 자신만 허용)
            if ($host !== $currentHost && !in_array($host, $allowed, true)) {
                $url = '/';
            }
        }

        $this->statusCode = $status;
        $this->headers['Location'] = str_replace(["\r", "\n"], '', $url);
        $this->send('');
    }

    /** 이전 페이지로 리다이렉트 */
    public function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        // Referer도 오픈 리다이렉트 방어 적용
        $this->redirect($referer);
    }

    /** 영구 리다이렉트 (301) */
    public function permanentRedirect(string $url): never
    {
        $this->redirect($url, 301);
    }

    // ── 다운로드 / 스트리밍 ──

    /** 파일 다운로드 */
    public function download(string $filePath, ?string $fileName = null, array $headers = []): never
    {
        if (!is_file($filePath)) {
            $this->statusCode = 404;
            $this->send('File not found');
        }

        $fileName ??= basename($filePath);
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        $this->headers['Content-Type'] = $mime;
        $this->headers['Content-Disposition'] = 'attachment; filename="' . rawurlencode($fileName) . '"';
        $this->headers['Content-Length'] = (string) filesize($filePath);
        $this->headers['Content-Transfer-Encoding'] = 'binary';

        foreach ($headers as $k => $v) {
            $this->headers[(string) $k] = (string) $v;
        }

        $this->applyHeaders();

        readfile($filePath);
        exit;
    }

    /** 인라인 파일 표시 (PDF 등 브라우저에서 열기) */
    public function inline(string $filePath, ?string $fileName = null): never
    {
        if (!is_file($filePath)) {
            $this->statusCode = 404;
            $this->send('File not found');
        }

        $fileName ??= basename($filePath);
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        $this->headers['Content-Type'] = $mime;
        $this->headers['Content-Disposition'] = 'inline; filename="' . rawurlencode($fileName) . '"';
        $this->headers['Content-Length'] = (string) filesize($filePath);

        $this->applyHeaders();

        readfile($filePath);
        exit;
    }

    /**
     * 스트리밍 응답
     *
     * @param resource|callable $source 파일 핸들 또는 콜백
     */
    public function stream(mixed $source, ?string $fileName = null, string $contentType = 'application/octet-stream'): never
    {
        $this->headers['Content-Type'] = $contentType;
        if ($fileName !== null) {
            $this->headers['Content-Disposition'] = 'attachment; filename="' . rawurlencode($fileName) . '"';
        }
        $this->headers['Transfer-Encoding'] = 'chunked';

        $this->applyHeaders();

        if (is_callable($source)) {
            $source();
        } elseif (is_resource($source)) {
            while (!feof($source)) {
                echo fread($source, 8192);
                flush();
            }
            fclose($source);
        }

        exit;
    }

    // ── CORS 헬퍼 ──

    /** CORS 프리플라이트 응답 */
    public function corsPreflightOk(): never
    {
        $this->statusCode = 204;
        $this->send('');
    }

    // ── 내부 ──

    /** 헤더 + 쿠키 적용 */
    private function applyHeaders(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            foreach ($this->cookies as $c) {
                setcookie($c['name'], $c['value'], $c['options']);
            }
        }

        // 상태 초기화 (싱글턴이므로)
        $this->reset();
    }

    /** 응답 전송 */
    private function send(string $body): never
    {
        $this->applyHeaders();
        echo $body;
        exit;
    }

    /** 상태 초기화 */
    private function reset(): void
    {
        $this->statusCode = 200;
        $this->headers = [];
        $this->cookies = [];
    }
}
