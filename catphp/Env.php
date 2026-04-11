<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Env — 환경변수 관리
 *
 * .env 파일 파싱 + getenv/putenv 래퍼.
 *
 * 사용법:
 *   env('DB_HOST', '127.0.0.1');
 *   env()->load(__DIR__ . '/.env');
 *   env()->get('APP_DEBUG');
 *   env()->set('APP_KEY', 'secret');
 *   env()->required(['DB_HOST', 'DB_NAME']);
 *   env()->requiredNotEmpty(['APP_KEY']);
 *   env()->remove('OLD_KEY');
 */
final class Env
{
    private static ?self $instance = null;

    /** @var array<string, string> 로드된 환경변수 */
    private array $vars = [];

    /** @var list<string> 로드 완료된 파일 경로 (realpath 정규화) */
    private array $loadedPaths = [];

    private bool $loaded = false;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** .env 파일 로드 (멱등: 같은 파일은 1회만 파싱, force=true로 재로드) */
    public function load(string $path, bool $force = false): self
    {
        if (!is_file($path)) {
            return $this;
        }

        // 멱등성: realpath 정규화 후 중복 로드 방지
        $real = realpath($path);
        if ($real === false) {
            return $this;
        }
        if (!$force && in_array($real, $this->loadedPaths, true)) {
            return $this;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $this;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // 주석/빈 줄 건너뛰기
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            // export 접두사 제거
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // 따옴표 제거
            $value = $this->parseValue($value);

            // 변수 참조 치환: ${VAR} 또는 $VAR
            $value = $this->resolveReferences($value);

            $this->vars[$key] = $value;
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }

        $this->loadedPaths[] = $real;
        $this->loaded = true;
        return $this;
    }

    /** .env 파일이 로드되었는지 확인 */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /** 환경변수 가져오기 */
    public function get(string $key, mixed $default = null): mixed
    {
        // 내부 캐시 → $_ENV → getenv 순서
        if (array_key_exists($key, $this->vars)) {
            return $this->castValue($this->vars[$key]);
        }

        if (array_key_exists($key, $_ENV)) {
            return $this->castValue((string) $_ENV[$key]);
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return $this->castValue($value);
    }

    /** 환경변수 설정 */
    public function set(string $key, string $value): self
    {
        $this->vars[$key] = $value;
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        return $this;
    }

    /** 환경변수 존재 확인 */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->vars)
            || array_key_exists($key, $_ENV)
            || getenv($key) !== false;
    }

    /**
     * 필수 환경변수 존재 확인 (빈 문자열도 "존재"로 인정)
     *
     * @param list<string> $keys
     * @throws \RuntimeException 누락 시
     */
    public function required(array $keys): self
    {
        $missing = [];
        foreach ($keys as $key) {
            if (!$this->has($key)) {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException(
                '필수 환경변수 누락: ' . implode(', ', $missing)
            );
        }
        return $this;
    }

    /**
     * 필수 환경변수 존재 + 비어있지 않은 값 확인
     *
     * @param list<string> $keys
     * @throws \RuntimeException 누락 또는 빈 값
     */
    public function requiredNotEmpty(array $keys): self
    {
        $missing = [];
        foreach ($keys as $key) {
            $raw = $this->vars[$key] ?? $_ENV[$key] ?? getenv($key);
            if ($raw === false || $raw === null || $raw === '') {
                $missing[] = $key;
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException(
                '필수 환경변수 누락 또는 빈 값: ' . implode(', ', $missing)
            );
        }
        return $this;
    }

    /** 로드된 모든 변수 반환 */
    public function all(): array
    {
        return $this->vars;
    }

    /** 환경변수 삭제 */
    public function remove(string $key): self
    {
        unset($this->vars[$key], $_ENV[$key]);
        putenv($key);
        return $this;
    }

    /**
     * .env 파일에 키=값 쓰기 (추가/수정)
     *
     * 기존 파일의 해당 키가 있으면 값 교체, 없으면 마지막에 추가.
     */
    public function write(string $path, string $key, string $value): bool
    {
        $key = trim($key);
        $escapedValue = $this->escapeValue($value);
        $newLine = "{$key}={$escapedValue}";

        if (!is_file($path)) {
            return (bool) file_put_contents($path, $newLine . PHP_EOL, LOCK_EX);
        }

        // flock으로 원자적 read-modify-write
        $fp = fopen($path, 'c+');
        if ($fp === false) {
            return false;
        }

        flock($fp, LOCK_EX);
        // 잠금된 핸들에서 직접 읽기 (Windows mandatory lock 호환)
        $raw = stream_get_contents($fp);
        $lines = ($raw !== false && $raw !== '')
            ? explode("\n", str_replace("\r\n", "\n", $raw))
            : [];
        // 마지막 빈 요소 제거 (trailing newline)
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $found = false;
        $pattern = '/^(export\s+)?' . preg_quote($key, '/') . '\s*=/';
        foreach ($lines as $i => $line) {
            $trimmed = trim($line);
            // 주석 줄 보호
            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                continue;
            }
            if (preg_match($pattern, $trimmed, $m)) {
                $prefix = $m[1] ?? '';
                $lines[$i] = $prefix . $newLine;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $lines[] = $newLine;
        }

        $content = implode(PHP_EOL, $lines) . PHP_EOL;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $content);
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->set($key, $value);
        return true;
    }

    // ── 내부 ──

    /** 값 파싱: 따옴표 제거 */
    private function parseValue(string $value): string
    {
        $len = strlen($value);

        // 큰따옴표: 이스케이프 처리
        if ($len >= 2 && $value[0] === '"' && $value[$len - 1] === '"') {
            $value = substr($value, 1, $len - 2);
            return str_replace(
                ['\\n', '\\r', '\\t', '\\"', '\\\\'],
                ["\n",  "\r",  "\t",  '"',   '\\'],
                $value
            );
        }

        // 작은따옴표: 그대로
        if ($len >= 2 && $value[0] === "'" && $value[$len - 1] === "'") {
            return substr($value, 1, $len - 2);
        }

        // 인라인 주석 제거 (# 앞의 공백이 있을 때)
        $commentPos = strpos($value, ' #');
        if ($commentPos !== false) {
            $value = rtrim(substr($value, 0, $commentPos));
        }

        return $value;
    }

    /** 변수 참조 치환: ${VAR} 또는 $VAR */
    private function resolveReferences(string $value): string
    {
        return (string) preg_replace_callback(
            '/\$\{([a-zA-Z_][a-zA-Z0-9_]*)\}|\$([a-zA-Z_][a-zA-Z0-9_]*)/',
            function (array $m): string {
                $name = $m[1] !== '' ? $m[1] : $m[2];
                if (array_key_exists($name, $this->vars)) {
                    return $this->vars[$name];
                }
                if (array_key_exists($name, $_ENV)) {
                    return (string) $_ENV[$name];
                }
                $env = getenv($name);
                if ($env !== false) {
                    return $env;
                }
                // 미정의 참조는 원본 문자열 유지
                return $m[0];
            },
            $value
        );
    }

    /** 문자열 → PHP 타입 캐스팅 */
    private function castValue(string $value): mixed
    {
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    /** 값을 .env 파일 형식으로 이스케이프 */
    private function escapeValue(string $value): string
    {
        // 공백, #, 따옴표 포함 시 큰따옴표로 감싸기
        if (preg_match('/[\s#"\'\\\\]/', $value) || $value === '') {
            $escaped = str_replace(
                ['\\',   '"',   "\n",  "\r",  "\t"],
                ['\\\\', '\\"', '\\n', '\\r', '\\t'],
                $value
            );
            return '"' . $escaped . '"';
        }
        return $value;
    }
}
