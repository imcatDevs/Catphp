<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Router — 라우터 + 미들웨어
 *
 * 해시 O(1) 정확 매칭 우선, regex fallback.
 * {param} → named args 자동 매핑.
 */

enum HttpMethod: string
{
    case GET     = 'GET';
    case POST    = 'POST';
    case PUT     = 'PUT';
    case PATCH   = 'PATCH';
    case DELETE  = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD    = 'HEAD';
}

final class Router
{
    private static ?self $instance = null;

    /** @var array<string, array<string, callable>> 정적 라우트 [method][path] => handler */
    private array $staticRoutes = [];

    /** @var array<int, array{method: string, pattern: string, handler: callable, paramNames: array<int, string>, paramTypes: array<string, ?string>}> 동적 라우트 */
    private array $dynamicRoutes = [];

    /**
     * 타입별 정규식 매핑
     *
     * 사용법:
     *   {id}             → 기본 ([^/]+) — 모든 문자
     *   {id:int}         → 숫자만, 핸들러에 int 캐스팅
     *   {name:alpha}     → 영숫자/언더스코어/하이픈
     *   {slug:slug}      → URL 슬러그 (영숫자+하이픈)
     *   {uuid:uuid}      → UUID v4 포맷
     *   {d:date}         → 날짜 YYYY-MM-DD
     *   {y:year}         → 연도 4자리
     *   {m:month}        → 월 01~12
     *   {tel:phone}      → 전화번호 (숫자/하이픈/플러스)
     *   {code:zip}       → 우편번호 (한국 5자리 / 미국 5-9자리)
     *   {addr:email}     → 이메일 주소
     *   {h:hex}          → 16진수 문자열
     *   {text:korean}    → 한글+영숫자+공백+하이픈
     *   {addr:ip}        → IPv4 주소
     *   {flag:bool}      → true/false/1/0, 핸들러에 bool 캐스팅
     *   {code:regex(\d{2,4})} → 커스텀 정규식
     */
    private const TYPE_PATTERNS = [
        // 기본
        'int'     => '(\d+)',
        'alpha'   => '([a-zA-Z0-9_\-]+)',
        'slug'    => '([a-zA-Z0-9\-]+)',
        'uuid'    => '([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})',
        // 날짜/시간
        'date'    => '(\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01]))',
        'year'    => '(\d{4})',
        'month'   => '(0[1-9]|1[0-2])',
        // 연락처/주소
        'phone'   => '(\+?[\d\-]{7,15})',
        'zip'     => '(\d{5}(?:\-\d{4})?)',
        'email'   => '([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})',
        'ip'      => '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})',
        // 기타
        'hex'     => '([0-9a-fA-F]+)',
        'korean'  => '([\p{Hangul}a-zA-Z0-9\-\s]+)',
        'bool'    => '(true|false|1|0)',
    ];

    /** @var array<int, callable> 글로벌 미들웨어 */
    private array $middlewares = [];

    /** @var string 현재 그룹 prefix */
    private string $groupPrefix = '';

    /** @var ?callable 404 핸들러 */
    private mixed $notFoundHandler = null;

    /** 뷰 디렉토리 경로 (render용) */
    private ?string $viewPath = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 미들웨어 등록 */
    public function use(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /** 라우트 그룹 */
    public function group(string $prefix, callable $callback): self
    {
        $prevPrefix = $this->groupPrefix;
        $this->groupPrefix = $prevPrefix . $prefix;
        $callback();
        $this->groupPrefix = $prevPrefix;
        return $this;
    }

    /** 404 핸들러 설정 */
    public function notFound(callable $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    // ── HTTP 메서드별 라우트 등록 ──

    public function get(string $path, callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function options(string $path, callable $handler): self
    {
        return $this->addRoute('OPTIONS', $path, $handler);
    }

    /** 리디렉트 라우트 등록 */
    public function redirect(string $from, string $to, int $code = 301): self
    {
        return $this->any($from, function () use ($to, $code): never {
            \redirect($to, $code);
        });
    }

    /** 복수 HTTP 메서드에 라우트 등록 */
    public function match(array $methods, string $path, callable $handler): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $handler);
        }
        return $this;
    }

    /** 모든 HTTP 메서드에 라우트 등록 */
    public function any(string $path, callable $handler): self
    {
        foreach (HttpMethod::cases() as $method) {
            $this->addRoute($method->value, $path, $handler);
        }
        return $this;
    }

    /** 라우트 등록 (내부) */
    private function addRoute(string $method, string $path, callable $handler): self
    {
        $fullPath = $this->groupPrefix . $path;

        // 동적 파라미터 {param} 또는 {param:type} 감지
        if (str_contains($fullPath, '{')) {
            $paramNames = [];
            $paramTypes = [];
            $pattern = preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', function (array $matches) use (&$paramNames, &$paramTypes): string {
                $name = $matches[1];
                $type = $matches[2] ?? null;
                $paramNames[] = $name;
                $paramTypes[$name] = $type;
                return $this->resolveParamPattern($type);
            }, $fullPath);
            $pattern = '#^' . $pattern . '$#u';

            $this->dynamicRoutes[] = [
                'method'     => $method,
                'pattern'    => $pattern,
                'handler'    => $handler,
                'paramNames' => $paramNames,
                'paramTypes' => $paramTypes,
            ];
        } else {
            $this->staticRoutes[$method][$fullPath] = $handler;
        }

        return $this;
    }

    /** 타입 문자열 → 정규식 패턴 변환 */
    private function resolveParamPattern(?string $type): string
    {
        if ($type === null) {
            return '([^/]+)';
        }

        // 커스텀 regex: {param:regex(\d{2,4})}
        if (str_starts_with($type, 'regex(') && str_ends_with($type, ')')) {
            $customPattern = substr($type, 6, -1);
            return '(' . $customPattern . ')';
        }

        // 내장 타입
        return self::TYPE_PATTERNS[$type]
            ?? throw new \InvalidArgumentException("알 수 없는 라우트 파라미터 타입: {$type}. 사용 가능: int, alpha, slug, uuid, regex(...)");
    }

    /** 파라미터 타입 캐스팅 */
    private function castParams(array $params, array $paramTypes): array
    {
        foreach ($params as $name => $value) {
            $params[$name] = match ($paramTypes[$name] ?? null) {
                'int'   => (int) $value,
                'bool'  => ($value === 'true' || $value === '1'),
                default => $value,
            };
        }
        return $params;
    }

    /** 요청 디스패치 */
    public function dispatch(): void
    {
        // 운영환경 미들웨어 누락 경고 (1회만 실행)
        static $auditDone = false;
        if (!$auditDone) {
            $auditDone = true;
            $this->auditMiddlewares();
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // _method 오버라이드 (HTML 폼에서 PUT/PATCH/DELETE 지원)
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
            if ($override !== null) {
                $upper = strtoupper($override);
                // 허용된 HTTP 메서드만 오버라이드 (임의 문자열 차단)
                if (in_array($upper, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
                    $method = $upper;
                }
            }
        }

        $rawUri = $_SERVER['REQUEST_URI'] ?? '/';
        // null 바이트 제거 + parse_url 실패 방어
        $rawUri = str_replace("\0", '', $rawUri);
        $uri = parse_url($rawUri, PHP_URL_PATH);
        if (!is_string($uri) || $uri === '') {
            $uri = '/';
        }

        // 트레일링 슬래시 정규화 (루트 '/' 제외)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        // 미들웨어 실행
        foreach ($this->middlewares as $mw) {
            $result = $mw($method, $uri);
            if ($result === false) {
                return; // 미들웨어가 중단
            }
        }

        // 1. 정적 라우트 O(1) 매칭
        if (isset($this->staticRoutes[$method][$uri])) {
            $this->executeHandler($this->staticRoutes[$method][$uri], []);
            return;
        }

        // 2. 동적 라우트 regex 매칭
        $matched = $this->matchDynamic($method, $uri);
        if ($matched !== null) {
            $this->executeHandler($matched['handler'], $matched['params']);
            return;
        }

        // 3. HEAD → GET 폴백 (정적 + 동적 라우트)
        if ($method === 'HEAD') {
            if (isset($this->staticRoutes['GET'][$uri])) {
                ob_start();
                $this->executeHandler($this->staticRoutes['GET'][$uri], []);
                ob_end_clean();
                return;
            }
            $matched = $this->matchDynamic('GET', $uri);
            if ($matched !== null) {
                ob_start();
                $this->executeHandler($matched['handler'], $matched['params']);
                ob_end_clean();
                return;
            }
        }

        // 4. 404 — 무차별 경로 탐색 공격 방어
        $this->handle404($uri);
    }

    /** 동적 라우트 매칭 (내부) — 타입 캐스팅 포함 */
    private function matchDynamic(string $method, string $uri): ?array
    {
        foreach ($this->dynamicRoutes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['paramNames'], $matches);
                $params = $this->castParams($params, $route['paramTypes']);
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }
        return null;
    }

    /** 핸들러 실행 (string 반환 → echo, void → 직접 출력) */
    private function executeHandler(callable $handler, array $params): void
    {
        $result = $handler(...$params);
        if (is_string($result)) {
            echo $result;
        }
    }

    // ── 템플릿 렌더링 ──

    /**
     * PHP 템플릿 렌더링 (SPA 프래그먼트 서빙용)
     *
     * View 도구를 대체하는 경량 렌더러.
     * 경로 보안 검증 포함 (null 바이트, 디렉토리 탈출 차단).
     *
     * @param array<string, mixed> $data 템플릿에 전달할 변수
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->resolveViewPath($template);

        // 격리 클로저: extract 변수가 $this, $file 등 로컬 변수와 충돌 방지
        $render = static function (string $_file_, array $_data_): string {
            extract($_data_, EXTR_SKIP);
            ob_start();
            require $_file_;
            return ob_get_clean() ?: '';
        };

        return $render($file, $data);
    }

    /** 뷰 디렉토리 설정 */
    public function setViewPath(string $path): self
    {
        $this->viewPath = $path;
        return $this;
    }

    /**
     * 템플릿 경로 검증 (뷰 디렉토리 밖 탈출 차단)
     *
     * @throws \RuntimeException 파일 없음 또는 경로 탈출 시
     */
    private function resolveViewPath(string $template): string
    {
        $basePath = $this->viewPath ?? (\config('view.path') ?: dirname(__DIR__) . '/views');

        // null 바이트 + '..' 살균
        $template = str_replace(["\0", '..'], '', $template);
        $file = $basePath . '/' . str_replace('.', '/', $template) . '.php';

        // realpath 검증 — 뷰 디렉토리 안에 있는지 확인
        $realFile = realpath($file);
        $realBase = realpath($basePath);

        if ($realFile === false || $realBase === false) {
            throw new \RuntimeException("템플릿 파일을 찾을 수 없습니다: {$template}");
        }

        if (!str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR) && $realFile !== $realBase) {
            throw new \RuntimeException("템플릿 경로가 허용 범위를 벗어났습니다: {$template}");
        }

        return $realFile;
    }

    /**
     * 404 처리 + 무차별 경로 탐색 공격 방어
     *
     * - IP 기준 1분간 30회 404 → 일시 차단
     * - IP 기준 10분간 100회 404 → Firewall 자동 밴
     * - 위험 경로 패턴(.env, wp-admin 등) → 즉시 카운트 가중
     */
    private function handle404(string $uri): void
    {
        // 위험 경로 패턴 탐지 (스캐너/봇이 자주 탐색하는 경로)
        $suspicious = (bool) preg_match(
            '#(?:\.env|wp-(?:admin|login|content)|phpmyadmin|\.git|\.DS_Store|/\.ht|/config\.|/vendor/|/node_modules/)#i',
            $uri
        );

        // 의심 경로는 가중치 적용: 1회 = 5회 카운팅 (scan/ban 모두)
        $weight = $suspicious ? 5 : 1;
        for ($i = 0; $i < $weight; $i++) {
            \rate()->limit('404ban', 600, 100);
        }

        // IP 기준 레이트 리미트 (1분/30회)
        if (!\rate()->limit('404scan', 60, $suspicious ? 6 : 30)) {
            // 극단적 남용: 10분/100회 초과 시 Firewall 자동 밴
            // limit()으로 기록 + 검사 (check()는 조회만 하여 카운터 미증가)
            if (class_exists('Cat\\Firewall', false) && !\rate()->limit('404ban', 600, 100)) {
                \firewall()->ban(\ip()->address(), '무차별 경로 탐색 공격 자동 차단');
            }

            // 차단: 403 상태 코드 + 최소 응답
            http_response_code(403);
            echo '<!DOCTYPE html><html><body><h1>403 Forbidden</h1></body></html>';
            return;
        }

        // 일반 404 응답
        http_response_code(404);
        if ($this->notFoundHandler) {
            $result = ($this->notFoundHandler)();
            if (is_string($result)) {
                echo $result;
            }
            return;
        }

        $isApi = str_starts_with($uri, '/api/');
        if ($isApi) {
            \json()->notFound();
        } else {
            echo '<h1>404 Not Found</h1>';
        }
    }

    /**
     * 미들웨어 누락 감사 — 운영환경에서 API 라우트에 보안 미들웨어 없으면 경고
     *
     * Guard(입력 살균)와 Rate(레이트 리미트) 미들웨어가 등록되어 있는지 확인.
     * 개발환경(debug=true)에서는 실행하지 않음.
     */
    private function auditMiddlewares(): void
    {
        if ((bool) \config('app.debug', false)) {
            return;
        }
        if (!class_exists('Cat\\Log', false)) {
            return;
        }

        // API 라우트가 등록되어 있는지 확인
        $hasApiRoute = false;
        foreach ($this->staticRoutes as $methods) {
            foreach (array_keys($methods) as $path) {
                if (str_starts_with($path, '/api/')) {
                    $hasApiRoute = true;
                    break 2;
                }
            }
        }
        if (!$hasApiRoute) {
            foreach ($this->dynamicRoutes as $route) {
                if (str_contains($route['pattern'], '/api/')) {
                    $hasApiRoute = true;
                    break;
                }
            }
        }

        if (!$hasApiRoute) {
            return;
        }

        // 글로벌 미들웨어 수가 0이면 보안 미들웨어가 전혀 없는 것
        if ($this->middlewares === []) {
            \logger()->warn(
                'Router: API 라우트가 등록되어 있지만 글로벌 미들웨어가 없습니다. ' .
                'Guard, Rate, Cors 미들웨어 등록을 권장합니다.'
            );
        }
    }
}
