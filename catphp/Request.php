<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Request — HTTP 요청 추상화
 *
 * $_GET, $_POST, $_FILES, $_SERVER, $_COOKIE, php://input 통합 래퍼.
 *
 * 사용법:
 *   request()->input('name', 'default');
 *   request()->query('page', '1');
 *   request()->method();
 *   request()->isAjax();
 *   request()->file('avatar');
 *   request()->header('Authorization');
 *   request()->all();
 *   request()->only(['name', 'email']);
 *   request()->bearerToken();
 */
final class Request
{
    private static ?self $instance = null;

    /** @var array<string, mixed> 병합된 입력 데이터 */
    private array $inputCache;

    /** @var ?string raw body 캐시 */
    private ?string $rawBody = null;

    /** @var ?array<string, mixed> JSON body 캐시 */
    private ?array $jsonCache = null;

    private function __construct()
    {
        $this->inputCache = array_merge($_GET, $_POST);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 입력 캐시 덮어쓰기 (테스트용 모킹 또는 런타임 갱신)
     *
     * @param array<string, mixed> $data
     */
    public function override(array $data): self
    {
        $this->inputCache = $data;
        $this->jsonCache = null;
        $this->rawBody = null;
        return $this;
    }

    /** 입력 캐시 현재 슈퍼글로벌로 리프레시 */
    public function refresh(): self
    {
        $this->inputCache = array_merge($_GET, $_POST);
        $this->jsonCache = null;
        $this->rawBody = null;
        return $this;
    }

    // ── 입력 데이터 ──

    /** GET + POST + JSON body 에서 값 가져오기 */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->inputCache[$key] ?? $this->json($key, $default);
    }

    /** GET 파라미터 */
    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /** POST 파라미터 */
    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /** JSON body 파싱 후 키 접근 */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonCache === null) {
            $raw = $this->raw();
            // JSON body 크기 제한 (1MB) + 깊이 제한 (32) — DoS 방어
            if ($raw === '' || strlen($raw) > 1_048_576) {
                $this->jsonCache = [];
            } else {
                $decoded = json_decode($raw, true, 32);
                $this->jsonCache = is_array($decoded) ? $decoded : [];
            }
        }

        if ($key === null) {
            return $this->jsonCache;
        }

        return $this->jsonCache[$key] ?? $default;
    }

    /** 모든 입력 (GET + POST + JSON) */
    public function all(): array
    {
        return array_merge($this->inputCache, $this->json() ?? []);
    }

    /**
     * 지정된 키만 가져오기
     *
     * @param list<string> $keys
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }
        return $result;
    }

    /**
     * 지정된 키 제외하고 가져오기
     *
     * @param list<string> $keys
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    /** 입력 값 존재 확인 */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** 입력 값이 비어있지 않은지 확인 */
    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    /** Raw body 가져오기 */
    public function raw(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string) file_get_contents('php://input');
        }
        return $this->rawBody;
    }

    // ── 파일 ──

    /**
     * 업로드 파일 정보
     *
     * @return array{name:string, type:string, tmp_name:string, error:int, size:int}|null
     */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /** 파일 업로드 존재 확인 */
    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && ($_FILES[$key]['error'] ?? 4) !== UPLOAD_ERR_NO_FILE;
    }

    // ── HTTP 메서드 ──

    /** HTTP 메서드 (대문자) */
    public function method(): string
    {
        // _method 필드로 메서드 오버라이드 지원
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? $this->header('X-HTTP-Method-Override');
            if ($override !== null) {
                return strtoupper($override);
            }
        }
        return $method;
    }

    /** 특정 HTTP 메서드인지 확인 */
    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    /** GET 요청인지 */
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    /** POST 요청인지 */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    // ── 요청 정보 ──

    /** 요청 URI (쿼리스트링 제외) */
    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        return $pos !== false ? substr($uri, 0, $pos) : $uri;
    }

    /** 전체 URL */
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        return $scheme . '://' . $this->safeHost() . $this->path();
    }

    /** 전체 URL + 쿼리스트링 */
    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $scheme . '://' . $this->safeHost() . $uri;
    }

    /** 쿼리스트링 */
    public function queryString(): string
    {
        return $_SERVER['QUERY_STRING'] ?? '';
    }

    // ── 헤더 ──

    /** 요청 헤더 값 */
    public function header(string $name, ?string $default = null): ?string
    {
        // HTTP_X_CUSTOM_HEADER 형식으로 변환
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        // Content-Type, Content-Length 특별 처리
        if ($key === 'HTTP_CONTENT_TYPE') {
            return $_SERVER['CONTENT_TYPE'] ?? $default;
        }
        if ($key === 'HTTP_CONTENT_LENGTH') {
            return $_SERVER['CONTENT_LENGTH'] ?? $default;
        }

        return $_SERVER[$key] ?? $default;
    }

    /**
     * 모든 요청 헤더
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['CONTENT-TYPE'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['CONTENT-LENGTH'] = $_SERVER['CONTENT_LENGTH'];
        }
        return $headers;
    }

    /** Bearer 토큰 추출 */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    /** Content-Type */
    public function contentType(): ?string
    {
        return $this->header('Content-Type');
    }

    /** JSON 요청인지 */
    public function isJson(): bool
    {
        $ct = $this->contentType() ?? '';
        return str_contains($ct, '/json') || str_contains($ct, '+json');
    }

    /** AJAX 요청인지 */
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    /** HTTPS 요청인지 */
    public function isSecure(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') === 'on') {
            return true;
        }
        // 리버스 프록시
        if ($this->header('X-Forwarded-Proto') === 'https') {
            return true;
        }
        return (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }

    // ── 클라이언트 정보 ──

    /** 클라이언트 IP (Ip 도구 존재 시 위임) */
    public function ip(): string
    {
        // Ip 도구가 로드되어 있으면 trusted_proxies 설정을 존중하여 위임
        if (class_exists('\Cat\Ip', false)) {
            return \ip()->address();
        }

        // 폴백: 직접 판단
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            $val = $_SERVER[$h] ?? null;
            if ($val !== null && $val !== '') {
                $ip = trim(explode(',', $val)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /** User-Agent */
    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /** Referer */
    public function referer(): ?string
    {
        return $_SERVER['HTTP_REFERER'] ?? null;
    }

    /** Accept 헤더 파싱 */
    public function accepts(string $type): bool
    {
        $accept = $this->header('Accept') ?? '';
        return str_contains($accept, $type) || str_contains($accept, '*/*');
    }

    /** 요청이 JSON을 원하는지 */
    public function wantsJson(): bool
    {
        return $this->accepts('application/json');
    }

    // ── 쿠키 ──

    /** 쿠키 값 가져오기 */
    public function cookie(string $key, ?string $default = null): ?string
    {
        return $_COOKIE[$key] ?? $default;
    }

    /** 쿠키 존재 확인 */
    public function hasCookie(string $key): bool
    {
        return isset($_COOKIE[$key]);
    }

    // ── 서버 정보 ──

    /** 서버 변수 */
    public function server(string $key, ?string $default = null): ?string
    {
        return $_SERVER[$key] ?? $default;
    }

    /** 서버 포트 */
    public function port(): int
    {
        return (int) ($_SERVER['SERVER_PORT'] ?? 80);
    }

    /** 호스트명 */
    public function host(): string
    {
        return $this->safeHost();
    }

    /** Host 헤더 살균 (Host 오염 공격 방어) */
    private function safeHost(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // config('app.url') 설정이 있으면 해당 호스트만 신뢰
        $appUrl = \config('app.url');
        if ($appUrl !== null && $appUrl !== '') {
            $trusted = (string) parse_url($appUrl, PHP_URL_HOST);
            if ($trusted !== '' && $trusted !== $host) {
                // 포트 포함 비교
                $trustedPort = parse_url($appUrl, PHP_URL_PORT);
                $trustedFull = $trustedPort ? "{$trusted}:{$trustedPort}" : $trusted;
                if ($trustedFull !== $host) {
                    return $trustedFull;
                }
            }
        }

        // 유효한 호스트 형식만 허용 (도메인/IP + 선택적 포트)
        if (!preg_match('/^[a-zA-Z0-9.\-]+(:\d{1,5})?$/', $host)) {
            return 'localhost';
        }

        return $host;
    }

    /** 스키마 (http/https) */
    public function scheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }
}
