<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Spider — 콘텐츠 파서 (웹 스크래핑)
 *
 * 토큰 기반 패턴 매칭으로 웹 페이지에서 구조화된 데이터를 추출.
 * Http 도구 활용 cURL 기반 HTTP 요청 + Guard 살균 연동.
 *
 * 기본 사용:
 *   $items = spider()
 *       ->pattern('title', '<h2>', '</h2>')
 *       ->pattern('price', '<span class="price">', '</span>', ['$', ','])
 *       ->startAt('<div class="list">')
 *       ->parse('https://example.com/products');
 *
 * 정규식 사용:
 *   $items = spider()
 *       ->regex('email', '/[\w.+-]+@[\w-]+\.[\w.]+/')
 *       ->parse('https://example.com/contacts');
 *
 * 페이지네이션:
 *   $items = spider()
 *       ->pattern('title', '<h2>', '</h2>')
 *       ->paginate('page', 1, 10)
 *       ->delay(2)
 *       ->parse('https://example.com/list');
 *
 * 콜백 처리:
 *   spider()
 *       ->pattern('title', '<h2>', '</h2>')
 *       ->paginate('page')
 *       ->each(function(array $pageItems, int $pageNo) {
 *           db()->table('items')->insert($pageItems);
 *       })
 *       ->parse('https://example.com/list');
 *
 * @config array{
 *     user_agent?: string,  // 기본 User-Agent
 *     timeout?: int,        // 기본 타임아웃 (초)
 * } spider  → config('spider.user_agent')
 */
final class Spider
{
    private static ?self $instance = null;

    /** @var array<string, array{0: string, 1: string, 2: string|array<string>}> 토큰 패턴 */
    private array $patterns = [];

    /** @var array<string, array{0: string, 1: int}> 정규식 패턴 (패턴, 그룹번호) */
    private array $regexPatterns = [];

    /** @var array<string> 필드 등록 순서 보존 */
    private array $fieldOrder = [];

    /** @var array<string, string> 필드별 건너뛰기 토큰 */
    private array $skipTokens = [];

    /** 파싱 시작 토큰 (_default_ 역할) */
    private ?string $startToken = null;

    /** @var array<string, string> 커스텀 헤더 */
    private array $customHeaders = [];

    /** @var array<string, string> 쿠키 */
    private array $cookies = [];

    private ?string $refererUrl = null;
    private ?string $userAgentStr = null;
    private int $timeoutSec = 30;
    private int $delaySec = 0;
    private bool $doSanitize = true;

    /** 페이지네이션 설정 */
    private ?string $pageParam = null;
    private int $pageStart = 1;
    private ?int $pageMax = null;

    /** 콜백 (페이지 단위 처리) */
    private ?\Closure $eachCallback = null;

    /** 인코딩 변환 */
    private ?string $fromEncoding = null;
    private ?string $toEncoding = null;

    /** 마지막 요청 결과 */
    private string $lastBody = '';
    private string $lastRawHeaders = '';
    private int $lastStatus = 0;

    private function __construct(
        private readonly string $defaultUa,
        private readonly int $defaultTimeout,
    ) {
        $this->timeoutSec = $defaultTimeout;
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            defaultUa: \config('spider.user_agent') ?? 'CatPHP Spider/1.0',
            defaultTimeout: (int) (\config('spider.timeout') ?? 30),
        );
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 패턴 설정
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * 토큰 패턴 등록 — 시작/끝 토큰 사이 텍스트 추출
     *
     * @param string               $field  필드명 (결과 배열 키)
     * @param string               $start  시작 토큰
     * @param string               $end    끝 토큰
     * @param string|array<string> $remove 결과에서 제거할 문자(열)
     */
    public function pattern(string $field, string $start, string $end, string|array $remove = ''): self
    {
        if ($start === '' || $end === '') {
            throw new \RuntimeException(
                'pattern() 시작/끝 토큰은 빈 문자열일 수 없습니다.'
            );
        }
        $c = clone $this;
        $c->patterns[$field] = [$start, $end, $remove];
        if (!in_array($field, $c->fieldOrder, true)) {
            $c->fieldOrder[] = $field;
        }
        return $c;
    }

    /**
     * 정규식 패턴 등록 — 정규식 매칭으로 텍스트 추출
     *
     * 주의: 대용량 콘텐츠에서 복잡한 정규식은 성능 저하 가능 (백트래킹).
     * 가능하면 토큰 패턴을 우선 사용하세요.
     *
     * @param string $field   필드명
     * @param string $regex   PCRE 정규식 (예: '/price:\s*(\d+)/i')
     * @param int    $group   캡처 그룹 번호 (0 = 전체 매치)
     */
    public function regex(string $field, string $regex, int $group = 0): self
    {
        $c = clone $this;
        $c->regexPatterns[$field] = [$regex, $group];
        if (!in_array($field, $c->fieldOrder, true)) {
            $c->fieldOrder[] = $field;
        }
        return $c;
    }

    /**
     * 필드 파싱 후 건너뛸 토큰 설정
     *
     * 패턴과 동일한 문자가 중간에 존재하여 그 뒤부터 파싱을 원할 때 사용.
     */
    public function skipAfter(string $field, string $token): self
    {
        $c = clone $this;
        $c->skipTokens[$field] = $token;
        return $c;
    }

    /**
     * 파싱 시작점 — 이 토큰 이후부터 패턴 매칭 시작
     *
     * 레거시 voidSetSkipOffset('_default_', token)과 동일.
     */
    public function startAt(string $token): self
    {
        $c = clone $this;
        $c->startToken = $token;
        return $c;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // HTTP 설정
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** 쿠키 추가 */
    public function cookie(string $name, string $value): self
    {
        $c = clone $this;
        $c->cookies[$name] = $value;
        return $c;
    }

    /** 리퍼러 설정 */
    public function referer(string $url): self
    {
        $c = clone $this;
        $c->refererUrl = $url;
        return $c;
    }

    /** User-Agent 설정 */
    public function userAgent(string $ua): self
    {
        $c = clone $this;
        $c->userAgentStr = $ua;
        return $c;
    }

    /** 커스텀 헤더 추가 */
    public function header(string $name, string $value): self
    {
        $c = clone $this;
        $c->customHeaders[$name] = $value;
        return $c;
    }

    /** 타임아웃 (초) */
    public function timeout(int $seconds): self
    {
        $c = clone $this;
        $c->timeoutSec = $seconds;
        return $c;
    }

    /** 페이지 간 대기 시간 (초) */
    public function delay(int $seconds): self
    {
        $c = clone $this;
        $c->delaySec = $seconds;
        return $c;
    }

    /** Guard 살균 on/off (기본 on) */
    public function sanitize(bool $enabled): self
    {
        $c = clone $this;
        $c->doSanitize = $enabled;
        return $c;
    }

    /** 문자 인코딩 변환 (예: 'EUC-KR' → 'UTF-8') */
    public function encoding(string $from, string $to = 'UTF-8'): self
    {
        $c = clone $this;
        $c->fromEncoding = $from;
        $c->toEncoding = $to;
        return $c;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 페이지네이션
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * 페이지네이션 설정
     *
     * URL에 &pageParam=1,2,3... 식으로 연속 페이지 파싱.
     *
     * @param string   $param    쿼리 파라미터명
     * @param int      $start    시작 페이지 번호 (기본 1)
     * @param int|null $maxPages 최대 페이지 수 (null = 데이터 소진까지)
     */
    public function paginate(string $param, int $start = 1, ?int $maxPages = null): self
    {
        $c = clone $this;
        $c->pageParam = $param;
        $c->pageStart = $start;
        $c->pageMax = $maxPages;
        return $c;
    }

    /**
     * 페이지 단위 콜백 — 페이지마다 결과를 즉시 처리 (메모리 절약)
     *
     * 페이지네이션 없이 사용해도 콜백이 호출됩니다 (페이지 번호 = 1).
     *
     * @param \Closure(array<int, array<string, string>>, int): void $callback
     *        인자: (페이지 결과 배열, 현재 페이지 번호)
     */
    public function each(\Closure $callback): self
    {
        $c = clone $this;
        $c->eachCallback = $callback;
        return $c;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 실행
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /**
     * URL에서 콘텐츠 가져오기 (파싱 없이 원본 반환)
     */
    public function fetch(string $url, string $method = 'GET', array $data = []): string
    {
        $http = $this->buildHttp();

        $response = match (strtoupper($method)) {
            'POST'   => $http->post($url, $data),
            'PUT'    => $http->put($url, $data),
            'DELETE' => $http->delete($url, $data),
            default  => $http->get($url),
        };

        $this->lastStatus = $response->status();
        $this->lastRawHeaders = implode("\r\n", array_map(
            fn(string $k, string $v) => "{$k}: {$v}",
            array_keys($response->headers()),
            array_values($response->headers()),
        ));
        $this->lastBody = $response->body();

        // 인코딩 변환
        if ($this->fromEncoding !== null && $this->toEncoding !== null) {
            $converted = mb_convert_encoding($this->lastBody, $this->toEncoding, $this->fromEncoding);
            if ($converted !== false) {
                $this->lastBody = $converted;
            }
        }

        return $this->lastBody;
    }

    /**
     * 패턴 기반 파싱 — 핵심 메서드
     *
     * @param string $url    대상 URL
     * @param string $method HTTP 메서드 (GET|POST)
     * @param array  $data   POST 데이터
     * @return array<int, array<string, string>> 파싱 결과
     */
    public function parse(string $url, string $method = 'GET', array $data = []): array
    {
        if (empty($this->patterns) && empty($this->regexPatterns)) {
            throw new \RuntimeException(
                '파싱 패턴을 설정하세요: spider()->pattern("field", "<start>", "<end>")'
            );
        }

        // 단일 페이지 모드
        if ($this->pageParam === null) {
            $this->fetch($url, $method, $data);
            $results = $this->extractAll($this->lastBody);

            if ($this->eachCallback !== null) {
                ($this->eachCallback)($results, 1);
            }

            return $results;
        }

        // 페이지네이션 모드
        return $this->parsePages($url, $method, $data);
    }

    /**
     * 문자열에서 직접 파싱 (HTTP 요청 없이)
     *
     * @param string $content 파싱 대상 문자열
     * @return array<int, array<string, string>> 파싱 결과
     */
    public function parseContent(string $content): array
    {
        if (empty($this->patterns) && empty($this->regexPatterns)) {
            throw new \RuntimeException(
                '파싱 패턴을 설정하세요: spider()->pattern("field", "<start>", "<end>")'
            );
        }

        // 인코딩 변환
        if ($this->fromEncoding !== null && $this->toEncoding !== null) {
            $converted = mb_convert_encoding($content, $this->toEncoding, $this->fromEncoding);
            if ($converted !== false) {
                $content = $converted;
            }
        }

        return $this->extractAll($content);
    }

    /**
     * 단일 값 빠른 추출 — 반복 없이 첫 번째 매칭만 반환
     *
     * @param string               $start  시작 토큰
     * @param string               $end    끝 토큰
     * @param string|array<string> $remove 제거 문자
     */
    public function find(string $content, string $start, string $end, string|array $remove = ''): ?string
    {
        $spos = strpos($content, $start);
        if ($spos === false) {
            return null;
        }
        $spos += strlen($start);

        $epos = strpos($content, $end, $spos);
        if ($epos === false) {
            return null;
        }

        $value = trim(substr($content, $spos, $epos - $spos));

        if ($remove !== '' && $remove !== []) {
            $removeArr = is_array($remove) ? $remove : [$remove];
            $value = str_replace($removeArr, '', $value);
        }

        return $this->doSanitize ? \guard()->clean($value) : $value;
    }

    /**
     * 마지막 응답 상태 코드
     *
     * 이뮤터블 체이닝이므로, 같은 인스턴스에서 호출해야 합니다:
     *   $s = spider()->pattern(...); $s->parse(url); $s->status();
     */
    public function status(): int
    {
        return $this->lastStatus;
    }

    /** 마지막 응답 헤더 (원본) */
    public function responseHeaders(): string
    {
        return $this->lastRawHeaders;
    }

    /** 마지막 응답 본문 */
    public function body(): string
    {
        return $this->lastBody;
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // 내부 메서드
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    /** Http 인스턴스 구성 */
    private function buildHttp(): Http
    {
        $http = \http()->timeout($this->timeoutSec);

        // User-Agent
        $ua = $this->userAgentStr ?? $this->defaultUa;
        $http = $http->header('User-Agent', $ua);

        // 리퍼러
        if ($this->refererUrl !== null) {
            $http = $http->header('Referer', $this->refererUrl);
        }

        // 쿠키
        if (!empty($this->cookies)) {
            $pairs = [];
            foreach ($this->cookies as $k => $v) {
                $pairs[] = $k . '=' . urlencode($v);
            }
            $http = $http->header('Cookie', implode('; ', $pairs));
        }

        // 커스텀 헤더
        foreach ($this->customHeaders as $name => $value) {
            $http = $http->header($name, $value);
        }

        return $http;
    }

    /**
     * 콘텐츠에서 모든 패턴 반복 추출
     *
     * @return array<int, array<string, string>>
     */
    private function extractAll(string $content): array
    {
        // 토큰 패턴만 있으면 토큰 모드
        if (!empty($this->patterns) && empty($this->regexPatterns)) {
            return $this->extractByToken($content);
        }

        // 정규식 패턴만 있으면 정규식 모드
        if (empty($this->patterns) && !empty($this->regexPatterns)) {
            return $this->extractByRegex($content);
        }

        // 혼합: 토큰 결과에 정규식 필드 병합 (행 수가 적은 쪽 기준 — 불완전 행 방지)
        $tokenResults = $this->extractByToken($content);
        $regexResults = $this->extractByRegex($content);
        $maxRows = min(count($tokenResults), count($regexResults));

        $merged = [];
        for ($i = 0; $i < $maxRows; $i++) {
            $row = ($tokenResults[$i] ?? []) + ($regexResults[$i] ?? []);
            // 필드 순서 정렬
            $ordered = [];
            foreach ($this->fieldOrder as $field) {
                if (isset($row[$field])) {
                    $ordered[$field] = $row[$field];
                }
            }
            $merged[] = $ordered;
        }

        return $merged;
    }

    /**
     * 토큰 패턴 기반 반복 추출
     *
     * @return array<int, array<string, string>>
     */
    private function extractByToken(string $content): array
    {
        $results = [];
        $offset = 0;
        $fields = array_keys($this->patterns);
        $fieldCount = count($fields);

        // 시작점 이동
        if ($this->startToken !== null) {
            $pos = strpos($content, $this->startToken, $offset);
            if ($pos === false) {
                return [];
            }
            $offset = $pos + strlen($this->startToken);
        }

        // 반복 추출 (maxIterations 안전장치: 무한 루프 방지)
        $maxIterations = 100_000;
        $iteration = 0;

        while ($iteration++ < $maxIterations) {
            $row = [];

            for ($i = 0; $i < $fieldCount; $i++) {
                $field = $fields[$i];
                $result = $this->extractField($content, $field, $offset);

                if ($result === null) {
                    // 불완전 행 무시 (레거시 동작)
                    return $results;
                }

                [$value, $offset] = $result;
                $row[$field] = $value;
            }

            // Guard 살균
            if ($this->doSanitize) {
                $row = \guard()->cleanArray($row);
            }

            $results[] = $row;
        }

        return $results;
    }

    /**
     * 정규식 패턴 기반 추출
     *
     * @return array<int, array<string, string>>
     */
    private function extractByRegex(string $content): array
    {
        // 시작점 이동
        $searchContent = $content;
        if ($this->startToken !== null) {
            $pos = strpos($content, $this->startToken);
            if ($pos === false) {
                return [];
            }
            $searchContent = substr($content, $pos + strlen($this->startToken));
        }

        /** @var array<string, array<string>> 필드별 매치 배열 */
        $fieldMatches = [];
        $maxCount = 0;

        foreach ($this->regexPatterns as $field => [$regex, $group]) {
            if (preg_match_all($regex, $searchContent, $matches)) {
                $fieldMatches[$field] = $matches[$group] ?? $matches[0];
                $maxCount = max($maxCount, count($fieldMatches[$field]));
            } else {
                $fieldMatches[$field] = [];
            }
        }

        $results = [];
        for ($i = 0; $i < $maxCount; $i++) {
            $row = [];
            foreach ($this->regexPatterns as $field => $_) {
                $row[$field] = $fieldMatches[$field][$i] ?? '';
            }
            if ($this->doSanitize) {
                $row = \guard()->cleanArray($row);
            }
            $results[] = $row;
        }

        return $results;
    }

    /**
     * 단일 필드 토큰 추출
     *
     * @return array{0: string, 1: int}|null [값, 새 오프셋]
     */
    private function extractField(string $content, string $field, int $offset): ?array
    {
        [$start, $end, $remove] = $this->patterns[$field];

        // 시작 토큰 검색
        $spos = strpos($content, $start, $offset);
        if ($spos === false) {
            return null;
        }
        $spos += strlen($start);

        // 끝 토큰 검색
        $epos = strpos($content, $end, $spos);
        if ($epos === false) {
            return null;
        }

        $newOffset = $epos + strlen($end);
        $value = substr($content, $spos, $epos - $spos);

        // 개행 제거 + trim
        $value = str_replace(["\r\n", "\r"], '', trim($value));

        // 제거 문자 처리
        if ($remove !== '' && $remove !== []) {
            $removeArr = is_array($remove) ? $remove : [$remove];
            $value = str_replace($removeArr, '', $value);
        }

        // 건너뛰기 토큰
        if (isset($this->skipTokens[$field])) {
            $skipPos = strpos($content, $this->skipTokens[$field], $newOffset);
            if ($skipPos !== false) {
                $newOffset = $skipPos + strlen($this->skipTokens[$field]);
            }
        }

        return [$value, $newOffset];
    }

    /**
     * 페이지네이션 파싱
     *
     * @return array<int, array<string, string>>
     */
    private function parsePages(string $url, string $method, array $data): array
    {
        $allResults = [];
        $pageNo = $this->pageStart;
        $pagesProcessed = 0;

        while (true) {
            $pageUrl = $this->appendQuery($url, $this->pageParam, (string) $pageNo);
            $this->fetch($pageUrl, $method, $data);

            $pageResults = $this->extractAll($this->lastBody);

            if (empty($pageResults)) {
                break;
            }

            // 콜백 모드: 즉시 처리 후 메모리 해제
            if ($this->eachCallback !== null) {
                ($this->eachCallback)($pageResults, $pageNo);
            } else {
                array_push($allResults, ...$pageResults);
            }

            $pagesProcessed++;
            $pageNo++;

            // 최대 페이지 도달
            if ($this->pageMax !== null && $pagesProcessed >= $this->pageMax) {
                break;
            }

            // 대기
            if ($this->delaySec > 0) {
                sleep($this->delaySec);
            }
        }

        return $allResults;
    }

    /** URL에 쿼리 파라미터 추가 */
    private function appendQuery(string $url, string $param, string $value): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . urlencode($param) . '=' . urlencode($value);
    }
}
