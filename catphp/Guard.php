<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Guard — 입력 살균/공격 차단
 *
 * @config array{
 *     auto_ban?: bool,       // 공격 감지 시 자동 IP 차단 (기본 false)
 *     max_body_size?: string, // 최대 요청 크기 (기본 '10M')
 * } guard  → config('guard.auto_ban')
 */
final class Guard
{
    private static ?self $instance = null;

    /** @var ?callable 공격 감지 콜백 */
    private mixed $attackCallback = null;

    private function __construct(
        private readonly bool $autoBan,
        private readonly int $maxBodySize,
    ) {}

    public static function getInstance(): self
    {
        $sizeStr = \config('guard.max_body_size') ?? '10M';
        $size = \parse_size($sizeStr);

        return self::$instance ??= new self(
            autoBan: (bool) (\config('guard.auto_ban') ?? false),
            maxBodySize: $size,
        );
    }

    /** 경로 트래버실 차단 */
    public function path(string $input): string
    {
        // 제로폭/불가시 유니코드 선제거
        $cleaned = preg_replace('/[\x00\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00AD}]/u', '', $input) ?? $input;

        // 이중/삼중 URL 인코딩 우회 방어: 최대 3회 디코딩
        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($cleaned);
            if ($decoded === $cleaned) {
                break;
            }
            $cleaned = $decoded;
        }

        $dangerous = [
            '../', '..\\',
            '%2e%2e', '%2E%2E',                 // URL 인코딩
            '%2e%2E', '%2E%2e',                 // 혼합 케이스 URL 인코딩
            '%252e%252e',                        // 이중 인코딩
            '%c0%ae', '%c0%2e',                 // 유니코드 오버롱 .
            '%c1%1c', '%c0%af',                 // 유니코드 오버롱 / \
            '%e0%80%ae',                         // 3바이트 오버롱 .
            '%f0%80%80%ae',                      // 4바이트 오버롱 .
            '..;/',                              // Tomcat-style
            '..%5c', '..%2f',                   // 인코딩된 구분자
            '%00', "\0",                         // Null 바이트
            '....//','....\\\\',                 // 이중 트래버설
        ];
        $cleaned = str_ireplace($dangerous, '', $cleaned);

        // 반복 패턴 재검사 (치환 후 새로운 ../ 생성 방어)
        while (str_contains($cleaned, '../') || str_contains($cleaned, '..\\')) {
            $cleaned = str_replace(['../', '..\\'], '', $cleaned);
        }

        if ($cleaned !== $input) {
            $this->reportAttack('path_traversal', $input);
        }

        return $cleaned;
    }

    /** XSS 방지 */
    public function xss(string $input): string
    {
        // ── 0. 선처리: null 바이트 + 제로폭 유니코드 문자 제거 ──
        $cleaned = preg_replace('/[\x00\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00AD}]/u', '', $input) ?? $input;

        // ── 1. HTML 엔티티 디코딩 반복 후 재검사 (이중/삼중 인코딩 우회 방어) ──
        $decoded = $cleaned;
        for ($i = 0; $i < 3; $i++) {
            $prev = $decoded;
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $prev) {
                break; // 더 이상 디코딩할 엔티티 없음
            }
        }
        if ($decoded !== $cleaned) {
            $decoded = $this->xssClean($decoded);
            // 디코딩+살균 결과가 원본 디코딩과 다르면 위험 패턴 발견된 것
            $fullyDecoded = $cleaned;
            for ($i = 0; $i < 3; $i++) {
                $prev = $fullyDecoded;
                $fullyDecoded = html_entity_decode($fullyDecoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($fullyDecoded === $prev) {
                    break;
                }
            }
            if ($decoded !== $fullyDecoded) {
                $cleaned = $decoded;
            }
        }

        // ── 2. 태그/속성 기반 살균 ──
        $cleaned = $this->xssClean($cleaned);

        if ($cleaned !== $input) {
            $this->reportAttack('xss', $input);
        }

        return $cleaned;
    }

    /** XSS 태그/속성 살균 (내부) */
    private function xssClean(string $str): string
    {
        // 스크립트 태그 제거
        $str = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $str) ?? $str;

        // 위험 태그 제거 (콘텐츠 포함)
        $dangerousTags = 'iframe|object|embed|svg|math|base|style|applet|meta|link|form';
        $str = preg_replace('/<(' . $dangerousTags . ')\b[^>]*>(.*?)<\/\1>/is', '', $str) ?? $str;
        // 위험 태그 자체 닫힘 제거
        $selfCloseTags = 'iframe|object|embed|svg|math|base|applet|meta|link|img|video|audio|source|input|details|marquee|select|textarea';
        $str = preg_replace('/<(' . $selfCloseTags . ')\b[^>]*\/?>/i', '', $str) ?? $str;

        // 이벤트 핸들러 제거: \s 또는 / 구분자 모두 대응 (<svg/onload=... 바이패스 방어)
        $str = preg_replace('/[\s\/]+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|`[^`]*`|[^\s>]+)/i', '', $str) ?? $str;

        // style 속성 내 위험 패턴 제거 (javascript:, expression(), url() 내 javascript)
        $str = preg_replace('/style\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $str) ?? $str;

        // 위험 프로토콜 제거 (javascript:, vbscript:, data:) — 공백/탭/개행 삽입 우회 대응
        $str = preg_replace('/(?:j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t|v\s*b\s*s\s*c\s*r\s*i\s*p\s*t)\s*:/i', '', $str) ?? $str;
        // data: URI (base64 포함)
        $str = preg_replace('/data\s*:[^,]*[;,]/i', '', $str) ?? $str;

        // CSS expression() 제거
        $str = preg_replace('/expression\s*\(/i', '(', $str) ?? $str;
        // CSS -moz-binding 제거
        $str = preg_replace('/-moz-binding\s*:/i', '', $str) ?? $str;

        return $str;
    }

    /** SQL Injection 탐지 (보조적 — 경고 로그 + 알림 전용) */
    public function sql(string $input): string
    {
        $patterns = [
            // 기본 SQL 구문
            '/\bUNION\b.*\bSELECT\b/i',
            '/\bDROP\b.*\bTABLE\b/i',
            '/\bDELETE\b.*\bFROM\b/i',
            '/\bINSERT\b.*\bINTO\b/i',
            '/\b(OR|AND)\b\s+\d+\s*=\s*\d+/i',
            // 시간 기반 공격
            '/\bSLEEP\s*\(/i',
            '/\bBENCHMARK\s*\(/i',
            '/\bWAITFOR\b.*\bDELAY\b/i',
            // 파일/시스템 접근
            '/\bLOAD_FILE\s*\(/i',
            '/\bINTO\b\s+(OUT|DUMP)FILE\b/i',
            '/\b(EXEC|EXECUTE)\s*\(/i',
            '/\bxp_\w+/i',
            // 정보 탈취
            '/\bINFORMATION_SCHEMA\b/i',
            // 주석/체인
            '/--\s/',
            '/#.*$/m',
            '/\/\*.*?\*\//s',
            '/;\s*(DROP|DELETE|UPDATE|INSERT)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->reportAttack('sql_injection', $input);
                break;
            }
        }

        return $input; // SQL 탐지는 로깅만, 실제 방어는 PDO prepared statement
    }

    /**
     * 종합 살균 — 제어문자 + CRLF + XSS + SQL탐지를 한번에 처리
     *
     * 용도: 유저 입력/DB 조회 결과 등 외부 데이터를 안전하게 정리.
     * User, View 등 다른 도구에서 이 메서드를 호출하여 살균 일관성 유지.
     */
    public function clean(string $input): string
    {
        // 1. 제어문자 제거 (탭/개행 제외한 \x00-\x08, \x0B, \x0C, \x0E-\x1F, \x7F)
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input) ?? $input;

        // 2. CRLF 인젝션 방어
        $cleaned = str_replace(["\r", "\0"], '', $cleaned);

        // 3. XSS 살균
        $cleaned = $this->xss($cleaned);

        // 4. SQL 인젝션 탐지 (로깅만)
        $this->sql($cleaned);

        return $cleaned;
    }

    /**
     * 배열 종합 살균 (재귀) — 모든 문자열 값에 clean() 적용
     *
     * @param array<string, mixed> $data 살균 대상 배열
     * @param array<string> $except 살균 제외 키 (예: ['password'])
     * @return array<string, mixed>
     */
    public function cleanArray(array $data, array $except = []): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $except, true)) {
                continue;
            }
            if (is_string($value)) {
                $data[$key] = $this->clean($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->cleanArray($value, $except);
            }
        }
        return $data;
    }

    /** 헤더 인젝션 차단 */
    public function header(string $input): string
    {
        $cleaned = str_replace(["\r", "\n", "\0"], '', $input);

        if ($cleaned !== $input) {
            $this->reportAttack('header_injection', $input);
        }

        return $cleaned;
    }

    /** 파일명 살균 */
    public function filename(string $name): string
    {
        // null 바이트 선제거 (file.php\0.jpg 우회 방어)
        $name = str_replace(["\0", '%00'], '', $name);

        // 위험 확장자 차단
        $dangerous = [
            'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'phps', // PHP
            'exe', 'sh', 'bat', 'cmd', 'com', 'msi', 'scr', 'ps1', 'vbs', 'wsf',    // 실행 파일
            'cgi', 'pl', 'py', 'rb',                                                   // 스크립트
            'jsp', 'jspx', 'asp', 'aspx', 'cer', 'cfm',                                // 서버 사이드
            'htaccess', 'htpasswd', 'shtml', 'inc',                                     // 서버 설정
            'svg',                                                                       // XSS 벡터
        ];

        // 이중 확장자 방어: 파일명 내 모든 확장자 검사 (file.php.jpg → php 탐지)
        $parts = explode('.', strtolower($name));
        array_shift($parts); // 첫 번째는 파일명이므로 제외
        foreach ($parts as $part) {
            if (in_array($part, $dangerous, true)) {
                $name .= '.blocked';
                $this->reportAttack('dangerous_file', $name);
                break;
            }
        }

        // 특수문자 제거, 안전한 파일명 생성
        $name = preg_replace('/[^\w\-. ]/', '', $name) ?? $name;
        $name = preg_replace('/\.{2,}/', '.', $name) ?? $name; // 연속 마침표 제거
        $name = preg_replace('/\s+/', '_', $name) ?? $name;
        $name = trim($name, '._');

        return $name ?: 'unnamed';
    }

    /** Content-Type 검증 */
    public function contentType(array $allowed = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data']): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        foreach ($allowed as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        return $contentType === ''; // 빈 Content-Type은 GET 요청 등에서 허용
    }

    /** 전체 입력 살균 */
    public function all(): array
    {
        $data = \input() ?? [];
        $sanitized = [];

        foreach ($data as $key => $value) {
            // key 살균: 개행 제거 + 길이 제한 + 영숫자/밑줄/하이픈/마침표만 허용
            $safeKey = preg_replace('/[^\w.\-]/', '', str_replace(["\r", "\n", "\0"], '', (string) $key));
            $safeKey = mb_substr($safeKey, 0, 128);
            if ($safeKey === '') {
                continue;
            }

            if (is_string($value)) {
                $value = $this->path($value);
                $value = $this->clean($value);
            } elseif (is_array($value)) {
                $value = $this->cleanArray($value);
            }
            $sanitized[$safeKey] = $value;
        }

        return $sanitized;
    }

    /** 요청 크기 제한 확인 */
    public function maxBodySize(?string $size = null): bool
    {
        $max = $size ? \parse_size($size) : $this->maxBodySize;
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        return $contentLength <= $max;
    }

    /** 공격 감지 콜백 설정 */
    public function onAttack(callable $callback): self
    {
        $this->attackCallback = $callback;
        return $this;
    }

    /** 미들웨어: 전체 요청 자동 검사 */
    public function middleware(): callable
    {
        return function (): ?bool {
            // 요청 크기 확인
            if (!$this->maxBodySize()) {
                http_response_code(413);
                echo json_encode(['ok' => false, 'error' => ['message' => 'Payload Too Large', 'code' => 413]]);
                exit;
            }

            // 전체 입력 살균 → input() 캐시에 반영
            $sanitized = $this->all();
            \input(data: $sanitized);

            return null;
        };
    }

    /** 공격 보고 (로깅 + 콜백 + 자동 차단) */
    private function reportAttack(string $type, string $input): void
    {
        $ip = \ip()->address();

        // 로깅
        if (class_exists('Cat\\Log', false)) {
            \logger()->warn("공격 감지: [{$type}] IP={$ip}", ['input' => mb_substr($input, 0, 200)]);
        }

        // 콜백 호출
        if ($this->attackCallback !== null) {
            ($this->attackCallback)($type, $ip);
        }

        // 자동 IP 차단
        if ($this->autoBan && class_exists('Cat\\Firewall', false)) {
            \firewall()->ban($ip);
        }
    }

}
