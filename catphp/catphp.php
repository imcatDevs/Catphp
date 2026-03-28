<?php declare(strict_types=1);

/**
 * CatPHP 코어 — autoloader + config + shortcuts + helpers
 *
 * require 1회로 프레임워크 전체 부팅.
 * 도구는 사용 시에만 로드 (지연 싱글턴).
 */

defined('CATPHP') || define('CATPHP', true);
define('CATPHP_VERSION', '1.0.3');

// ──────────────────────────────────────────────
// Autoloader: Cat\X → catphp/X.php 플랫 매핑
// ──────────────────────────────────────────────

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'Cat\\')) {
        return;
    }
    $file = __DIR__ . '/' . substr($class, 4) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ──────────────────────────────────────────────
// 설정: config(key, default) / config(array)
// ──────────────────────────────────────────────

/** @var array<string, mixed> 전역 설정 저장소 */
$_CATPHP_CONFIG = [];

/**
 * 설정 읽기/쓰기
 *
 * - config('db.host')           → dot notation 읽기
 * - config('db.host', '127.0.0.1') → 기본값 포함 읽기
 * - config([...])               → 배열 병합 (초기화)
 *
 * @param string|array<string, mixed> $key
 */
function config(string|array $key = '', mixed $default = null): mixed
{
    global $_CATPHP_CONFIG;
    static $_cache = [];

    // 배열이면 병합 (초기화) + 캐시 무효화
    if (is_array($key)) {
        $_CATPHP_CONFIG = array_replace_recursive($_CATPHP_CONFIG, $key);
        $_cache = [];
        return null;
    }

    // 빈 키면 전체 반환
    if ($key === '') {
        return $_CATPHP_CONFIG;
    }

    // 캐시 히트 (센티넬로 "키 없음"과 "값이 null"을 구분)
    static $_miss = null;
    $_miss ??= new \stdClass();

    if (array_key_exists($key, $_cache)) {
        return $_cache[$key] === $_miss ? $default : $_cache[$key];
    }

    // dot notation 읽기
    $segments = explode('.', $key);
    $value = $_CATPHP_CONFIG;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            $_cache[$key] = $_miss;
            return $default;
        }
        $value = $value[$segment];
    }
    $_cache[$key] = $value;
    return $value;
}

// ──────────────────────────────────────────────
// 범용 로더: cat(name) → 지연 싱글턴
// ──────────────────────────────────────────────

/** @var array<string, object> 도구 인스턴스 캐시 */
$_CATPHP_INSTANCES = [];

/**
 * 도구 로드 + 지연 싱글턴
 *
 * @template T of object
 * @param string $name 도구 클래스명 (예: 'DB', 'Router')
 * @return T
 */
function cat(string $name): object
{
    global $_CATPHP_INSTANCES;

    if (isset($_CATPHP_INSTANCES[$name])) {
        return $_CATPHP_INSTANCES[$name];
    }

    $class = 'Cat\\' . $name;

    if (!class_exists($class)) {
        throw new \RuntimeException(
            "catphp/{$name}.php 파일을 생성하세요. (Cat\\{$name} 클래스를 찾을 수 없습니다)"
        );
    }

    $_CATPHP_INSTANCES[$name] = $class::getInstance();
    return $_CATPHP_INSTANCES[$name];
}

// ──────────────────────────────────────────────
// 에러 핸들러: errors(debug)
// ──────────────────────────────────────────────

function errors(bool $debug = false): void
{
    error_reporting(E_ALL);

    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($debug): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        // E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE는 예외 변환하지 않음 (서드파티 호환)
        if (in_array($errno, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)) {
            return false;
        }
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    set_exception_handler(function (\Throwable $e) use ($debug): void {
        // 기존 output buffer 정리 (불완전 HTML 출력 방지)
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $isApi = isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/api/');

        if ($isApi) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => [
                    'message' => $debug ? $e->getMessage() : '서버 오류가 발생했습니다',
                    'code' => 500,
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit(1);
        }

        if ($debug) {
            http_response_code(500);
            echo '<h1>CatPHP Error</h1>';
            echo '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
            echo '<p>' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        } else {
            http_response_code(500);
            echo '<h1>서버 오류</h1><p>잠시 후 다시 시도해 주세요.</p>';
        }

        // 로그 기록 시도
        try {
            if (class_exists('Cat\\Log', false)) {
                logger()->error($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        } catch (\Throwable) {
            // 로거 실패 시 무시
        }

        exit(1);
    });
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 기본 도구 4개
// ──────────────────────────────────────────────

if (!function_exists('db')) {
    function db(): \Cat\DB { return cat('DB'); }
}

if (!function_exists('router')) {
    function router(): \Cat\Router { return cat('Router'); }
}

if (!function_exists('cache')) {
    function cache(): \Cat\Cache { return cat('Cache'); }
}

if (!function_exists('logger')) {
    function logger(): \Cat\Log { return cat('Log'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 보안 도구 6개
// ──────────────────────────────────────────────

if (!function_exists('auth')) {
    function auth(): \Cat\Auth { return cat('Auth'); }
}

if (!function_exists('csrf')) {
    function csrf(): \Cat\Csrf { return cat('Csrf'); }
}

if (!function_exists('encrypt')) {
    function encrypt(): \Cat\Encrypt { return cat('Encrypt'); }
}

if (!function_exists('firewall')) {
    function firewall(): \Cat\Firewall { return cat('Firewall'); }
}

if (!function_exists('ip')) {
    function ip(): \Cat\Ip { return cat('Ip'); }
}

if (!function_exists('guard')) {
    function guard(): \Cat\Guard { return cat('Guard'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 네트워크 도구 3개
// ──────────────────────────────────────────────

if (!function_exists('http')) {
    function http(): \Cat\Http { return cat('Http'); }
}

if (!function_exists('rate')) {
    function rate(): \Cat\Rate { return cat('Rate'); }
}

if (!function_exists('cors')) {
    function cors(): \Cat\Cors { return cat('Cors'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — API 도구 2개
// ──────────────────────────────────────────────

if (!function_exists('json')) {
    function json(): \Cat\Json { return cat('Json'); }
}

if (!function_exists('api')) {
    function api(): \Cat\Api { return cat('Api'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 데이터 도구 4개 + 헬퍼 2개
// ──────────────────────────────────────────────

if (!function_exists('valid')) {
    function valid(array $rules = []): \Cat\Valid {
        $instance = cat('Valid');
        return $rules ? $instance->rules($rules) : $instance;
    }
}

if (!function_exists('render')) {
    /**
     * PHP 템플릿 렌더링 (Router의 render() 위임)
     *
     * @param array<string, mixed> $data
     */
    function render(string $template, array $data = []): string
    {
        return router()->render($template, $data);
    }
}

if (!function_exists('e')) {
    /** HTML 이스케이프 */
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('upload')) {
    function upload(): \Cat\Upload { return cat('Upload'); }
}

if (!function_exists('paginate')) {
    function paginate(): \Cat\Paginate { return cat('Paginate'); }
}

if (!function_exists('cookie')) {
    function cookie(): \Cat\Cookie { return cat('Cookie'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 웹/CMS 도구 8개 + 헬퍼 1개
// ──────────────────────────────────────────────

if (!function_exists('user')) {
    function user(): \Cat\User { return cat('User'); }
}

if (!function_exists('telegram')) {
    function telegram(): \Cat\Telegram { return cat('Telegram'); }
}

if (!function_exists('image')) {
    function image(): \Cat\Image { return cat('Image'); }
}

if (!function_exists('flash')) {
    function flash(): \Cat\Flash { return cat('Flash'); }
}

if (!function_exists('perm')) {
    function perm(): \Cat\Perm { return cat('Perm'); }
}

if (!function_exists('search')) {
    function search(): \Cat\Search { return cat('Search'); }
}

if (!function_exists('meta')) {
    function meta(): \Cat\Meta { return cat('Meta'); }
}

if (!function_exists('geo')) {
    function geo(): \Cat\Geo { return cat('Geo'); }
}

if (!function_exists('trans')) {
    /** 번역 shortcut — geo()->t() 위임 */
    function trans(string $key, array $replace = []): string { return geo()->t($key, $replace); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 블로그 도구 3개
// ──────────────────────────────────────────────

if (!function_exists('tag')) {
    function tag(): \Cat\Tag { return cat('Tag'); }
}

if (!function_exists('feed')) {
    function feed(): \Cat\Feed { return cat('Feed'); }
}

if (!function_exists('text')) {
    function text(): \Cat\Text { return cat('Text'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 유틸+CLI 도구 4개
// ──────────────────────────────────────────────

if (!function_exists('event')) {
    function event(): \Cat\Event { return cat('Event'); }
}

if (!function_exists('slug')) {
    function slug(): \Cat\Slug { return cat('Slug'); }
}

if (!function_exists('cli')) {
    function cli(): \Cat\Cli { return cat('Cli'); }
}

if (!function_exists('spider')) {
    function spider(): \Cat\Spider { return cat('Spider'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 인프라 도구 8개
// ──────────────────────────────────────────────

if (!function_exists('redis')) {
    function redis(): \Cat\Redis { return cat('Redis'); }
}

if (!function_exists('mailer')) {
    function mailer(): \Cat\Mail { return cat('Mail'); }
}

if (!function_exists('queue')) {
    function queue(): \Cat\Queue { return cat('Queue'); }
}

if (!function_exists('storage')) {
    function storage(): \Cat\Storage { return cat('Storage'); }
}

if (!function_exists('schedule')) {
    function schedule(): \Cat\Schedule { return cat('Schedule'); }
}

if (!function_exists('notify')) {
    function notify(): \Cat\Notify { return cat('Notify'); }
}

if (!function_exists('hasher')) {
    function hasher(): \Cat\Hash { return cat('Hash'); }
}

if (!function_exists('excel')) {
    function excel(): \Cat\Excel { return cat('Excel'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 관리/연동 도구 4개
// ──────────────────────────────────────────────

if (!function_exists('sitemap')) {
    function sitemap(): \Cat\Sitemap { return cat('Sitemap'); }
}

if (!function_exists('backup')) {
    function backup(): \Cat\Backup { return cat('Backup'); }
}

if (!function_exists('dbview')) {
    function dbview(): \Cat\DbView { return cat('DbView'); }
}

if (!function_exists('webhook')) {
    function webhook(): \Cat\Webhook { return cat('Webhook'); }
}

if (!function_exists('swoole')) {
    function swoole(): \Cat\Swoole { return cat('Swoole'); }
}

// ──────────────────────────────────────────────
// Shortcut 함수 — 실용 도구 9개
// ──────────────────────────────────────────────

if (!function_exists('env')) {
    function env(?string $key = null, mixed $default = null): mixed
    {
        $instance = cat('Env');
        if ($key === null) {
            return $instance;
        }
        return $instance->get($key, $default);
    }
}

if (!function_exists('request')) {
    function request(): \Cat\Request { return cat('Request'); }
}

if (!function_exists('response')) {
    function response(): \Cat\Response { return cat('Response'); }
}

if (!function_exists('session')) {
    /**
     * @return ($key is null ? \Cat\Session : mixed)
     */
    function session(?string $key = null, mixed $default = null): mixed
    {
        $instance = cat('Session');
        if ($key === null) {
            return $instance;
        }
        return $instance->get($key, $default);
    }
}

if (!function_exists('collect')) {
    /** @param iterable<mixed> $items */
    function collect(iterable $items = []): \Cat\Collection
    {
        return new \Cat\Collection($items);
    }
}

if (!function_exists('migration')) {
    function migration(): \Cat\Migration { return cat('Migration'); }
}

if (!function_exists('debug')) {
    function debug(): \Cat\Debug { return cat('Debug'); }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never { debug()->dd(...$vars); }
}

if (!function_exists('dump')) {
    function dump(mixed ...$vars): void { debug()->dump(...$vars); }
}

if (!function_exists('captcha')) {
    function captcha(): \Cat\Captcha { return cat('Captcha'); }
}

if (!function_exists('faker')) {
    function faker(): \Cat\Faker { return cat('Faker'); }
}

// ──────────────────────────────────────────────
// 웹 헬퍼
// ──────────────────────────────────────────────

if (!function_exists('input')) {
    /**
     * 요청 입력값 통합 읽기 ($_GET + $_POST + JSON body)
     *
     * - input('key')           → 단일 값
     * - input()                → 전체 배열
     * - input(data: $arr)      → 내부 캐시 교체 (Guard 살균 결과 반영용)
     */
    function input(?string $key = null, mixed $default = null, ?array $data = null): mixed
    {
        static $_cache = null;

        // 캐시 교체 (Guard::all() 살균 결과 주입)
        if ($data !== null) {
            $_cache = $data;
            return null;
        }

        if ($_cache === null) {
            $_cache = array_merge($_GET, $_POST);
            // JSON body 감지
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $body = file_get_contents('php://input');
                // JSON body 크기 제한 (1MB) — DoS 방어
                if ($body !== false) {
                    if (strlen($body) > 1_048_576) {
                        // 크기 초과 로깅 (보안 감사)
                        if (class_exists('Cat\\Log', false)) {
                            \logger()->warn('JSON body 크기 초과 (1MB): ' . strlen($body) . ' bytes');
                        }
                    } else {
                        $json = json_decode($body, true, 32);
                        if (is_array($json)) {
                            $_cache = array_merge($_cache, $json);
                        }
                    }
                }
            }
        }

        if ($key === null) {
            return $_cache;
        }

        return $_cache[$key] ?? $default;
    }
}

if (!function_exists('redirect')) {
    /**
     * URL 리디렉트 + exit
     */
    function redirect(string $url, int $code = 302): never
    {
        // CRLF 헤더 인젝션 방어
        $url = str_replace(["\r", "\n", "\0", "\t"], '', $url);

        // Protocol-relative URL 차단 (//attacker.com 우회 방어)
        if (str_starts_with($url, '//')) {
            $url = '/';
        }

        // 오픈 리다이렉트 방어: 외부 URL 차단
        if (preg_match('#^https?://#i', $url)) {
            $allowed = (array) config('response.allowed_hosts', []);
            $host = (string) parse_url($url, PHP_URL_HOST);
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($host !== $currentHost && !in_array($host, $allowed, true)) {
                $url = '/';
            }
        }

        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }
}

// ──────────────────────────────────────────────
// 유틸리티 헬퍼
// ──────────────────────────────────────────────

if (!function_exists('parse_size')) {
    /**
     * 사이즈 문자열 파싱 ('10M' → bytes)
     */
    function parse_size(string $size): int
    {
        $size = trim($size);
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;
        return match ($unit) {
            'G' => $value * 1073741824,
            'M' => $value * 1048576,
            'K' => $value * 1024,
            default => $value,
        };
    }
}

if (!function_exists('is_cli')) {
    /**
     * CLI 환경 감지
     */
    function is_cli(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}
