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
        // ── 0. 제로폭/불가시 유니코드 + null 바이트 선제거 ──
        $cleaned = preg_replace('/[\x00\x{200B}\x{200C}\x{200D}\x{FEFF}\x{00AD}]/u', '', $input) ?? $input;

        // ── 1. 완전 디코딩: URL 인코딩이 수렴할 때까지 반복 (최대 10회, 무한 루프 방지) ──
        for ($i = 0; $i < 10; $i++) {
            $decoded = rawurldecode($cleaned);
            if ($decoded === $cleaned) {
                break;
            }
            $cleaned = $decoded;
        }

        // ── 2. 오버롱 UTF-8 / Tomcat-style / null 잔여 패턴 제거 ──
        // 디코딩 완료 후에도 남을 수 있는 바이너리/비표준 시퀀스 치환
        $dangerous = [
            "\xc0\xae", "\xc0\x2e",             // 오버롱 .
            "\xc1\x1c", "\xc0\xaf",             // 오버롱 / \
            "\xe0\x80\xae",                      // 3바이트 오버롱 .
            "\xf0\x80\x80\xae",                  // 4바이트 오버롱 .
            '..;',                               // Tomcat-style semicolon
        ];
        $cleaned = str_replace($dangerous, '', $cleaned);

        // ── 3. 경로 구분자 정규화 (\ → /) ──
        $cleaned = str_replace('\\', '/', $cleaned);

        // ── 4. 반복 치환: ../ 패턴이 완전히 사라질 때까지 ──
        $prev = '';
        while ($prev !== $cleaned) {
            $prev = $cleaned;
            $cleaned = str_replace('../', '', $cleaned);
        }

        // ── 5. 세그먼트 기반 최종 검증: '..' 세그먼트 완전 제거 ──
        // explode 후 '..' 세그먼트가 남아 있으면 우회 시도로 판단
        $segments = explode('/', $cleaned);
        $hasDotDot = false;
        $safe = [];
        foreach ($segments as $seg) {
            if ($seg === '..') {
                $hasDotDot = true;
                continue; // 제거
            }
            $safe[] = $seg;
        }
        if ($hasDotDot) {
            $cleaned = implode('/', $safe);
        }

        // ── 6. 공격 리포트 ──
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
            // 공격 탐지 일시 차단 확인
            if ($this->autoBan && $this->isBlocked()) {
                http_response_code(403);
                echo '<!DOCTYPE html><html><body><h1>403 Forbidden</h1></body></html>';
                exit;
            }

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

    /**
     * 공격 유형별 위험도 가중치
     *
     * 높을수록 위험 — 적은 횟수로 차단 임계값 도달.
     * 예: sql_injection(가중치 5) 2회 = 누적 10점 → LEVEL 2 차단 돌파.
     */
    private const ATTACK_WEIGHT = [
        'sql_injection'    => 5,
        'path_traversal'   => 4,
        'header_injection' => 4,
        'dangerous_file'   => 3,
        'xss'              => 2,
    ];

    /**
     * 단계적 차단 룰 (누적 점수 기준)
     *
     * 각 레벨은 [누적 점수 임계값, 차단 시간(초), 설명].
     * 0 = 영구 밴 (Firewall).
     */
    private const BAN_LEVELS = [
        ['score' =>  5, 'duration' =>  300, 'label' => 'LEVEL 1: 5분 일시 차단'],
        ['score' => 15, 'duration' => 1800, 'label' => 'LEVEL 2: 30분 차단'],
        ['score' => 30, 'duration' => 3600, 'label' => 'LEVEL 3: 1시간 차단'],
        ['score' => 50, 'duration' =>    0, 'label' => 'LEVEL 4: 영구 밴'],
    ];

    /**
     * 공격 보고 — 횟수 기반 단계적 차단
     *
     * 1. 공격 유형별 가중치로 누적 점수 계산
     * 2. 점수 구간에 따라 단계적 대응 (일시 차단 → 영구 밴)
     * 3. 로깅 + 콜백 + Rate/Firewall 연동
     */
    private function reportAttack(string $type, string $input): void
    {
        $ip = \ip()->address();
        $weight = self::ATTACK_WEIGHT[$type] ?? 2;

        // 로깅
        if (class_exists('Cat\\Log', false)) {
            \logger()->warn("공격 감지: [{$type}] IP={$ip} (가중치:{$weight})", [
                'input' => mb_substr($input, 0, 200),
            ]);
        }

        // 콜백 호출
        if ($this->attackCallback !== null) {
            ($this->attackCallback)($type, $ip);
        }

        if (!$this->autoBan) {
            return;
        }

        // 누적 점수 기록 (Rate 파일 기반 — 10분 윈도우)
        $score = $this->recordAttackScore($ip, $weight);

        // 단계적 차단 적용 (높은 레벨부터 검사)
        foreach (array_reverse(self::BAN_LEVELS) as $level) {
            if ($score >= $level['score']) {
                if ($level['duration'] === 0) {
                    // 영구 밴
                    if (class_exists('Cat\\Firewall', false)) {
                        \firewall()->ban($ip, "자동 차단: {$level['label']} (점수:{$score})");
                    }
                } else {
                    // 일시 차단: Rate limit으로 해당 시간 동안 차단
                    $this->enforceBlock($ip, $level['duration']);
                }

                if (class_exists('Cat\\Log', false)) {
                    \logger()->error("{$level['label']} — IP={$ip}, 누적={$score}점", ['type' => $type]);
                }
                break;
            }
        }
    }

    /**
     * 공격 점수 기록 + 누적 합산 반환
     *
     * storage/rate/attack_{hash}.json에 [timestamp, weight] 쌍 기록.
     * 10분 윈도우 슬라이딩.
     */
    private function recordAttackScore(string $ip, int $weight): int
    {
        $dir = \config('rate.path') ?? __DIR__ . '/../storage/rate';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/attack_' . md5($ip) . '.json';
        $now = time();
        $window = 600; // 10분

        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return $weight;
        }

        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;

        if (!is_array($data) || !isset($data['hits'])) {
            $data = ['hits' => []];
        }

        // 윈도우 밖 기록 제거 + 새 기록 추가
        $data['hits'] = array_values(array_filter(
            $data['hits'],
            fn(array $h) => $h[0] > ($now - $window)
        ));
        $data['hits'][] = [$now, $weight];

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        flock($fp, LOCK_UN);
        fclose($fp);

        // 누적 점수 합산
        return array_sum(array_column($data['hits'], 1));
    }

    /** 일시 차단 적용 (차단 만료 시각을 파일에 기록) */
    private function enforceBlock(string $ip, int $duration): void
    {
        $dir = \config('rate.path') ?? __DIR__ . '/../storage/rate';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file = $dir . '/block_' . md5($ip) . '.lock';
        file_put_contents($file, (string) (time() + $duration), LOCK_EX);
    }

    /** IP가 현재 일시 차단 상태인지 확인 */
    public function isBlocked(?string $ip = null): bool
    {
        $ip ??= \ip()->address();
        $dir = \config('rate.path') ?? __DIR__ . '/../storage/rate';
        $file = $dir . '/block_' . md5($ip) . '.lock';

        if (!is_file($file)) {
            return false;
        }

        $expiresAt = (int) file_get_contents($file);
        if ($expiresAt > time()) {
            return true;
        }

        // 만료된 블록 파일 정리
        @unlink($file);
        return false;
    }
}
