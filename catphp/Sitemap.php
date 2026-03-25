<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Sitemap — XML 사이트맵 생성
 *
 * 사용법:
 *   sitemap()->url('/', '2024-01-01', 'daily', 1.0)
 *            ->url('/about', '2024-01-01', 'monthly', 0.8)
 *            ->output();
 *
 *   sitemap()->fromQuery($rows, '/post/{slug}', 'updated_at')->output();
 *   sitemap()->index(['/sitemap-posts.xml', '/sitemap-pages.xml'])->output();
 *   sitemap()->url(...)->save('Public/sitemap.xml');
 *
 * @config array{
 *     base_url?: string,   // 사이트 기본 URL (예: https://example.com)
 *     cache_ttl?: int,     // 캐시 TTL (초, 기본 3600)
 * } sitemap  → config('sitemap.base_url')
 */
final class Sitemap
{
    private static ?self $instance = null;

    /** 사이트맵 스펙 최대 URL 수 (50,000개/파일). 초과 시 index()로 분할 권장 */
    private const MAX_URLS = 50_000;

    /** changefreq 허용값 (사이트맵 프로토콜 0.9 스펙) */
    private const VALID_CHANGEFREQ = [
        'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never',
    ];

    private string $baseUrl;
    private int $cacheTtl;

    /** @var array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: float}> */
    private array $urls = [];

    /** @var array<int, array{loc: string, lastmod?: string}> 사이트맵 인덱스 엔트리 */
    private array $sitemaps = [];

    /** 인덱스 모드 여부 */
    private bool $isIndex = false;

    private function __construct()
    {
        $this->baseUrl = rtrim((string) \config('sitemap.base_url', ''), '/');
        $this->cacheTtl = (int) \config('sitemap.cache_ttl', 3600);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * URL 엔트리 추가
     *
     * @param string      $loc        URL 경로 (base_url에 자동 결합)
     * @param string|null $lastmod    최종 수정일 (Y-m-d 형식)
     * @param string|null $changefreq 변경 빈도 (always|hourly|daily|weekly|monthly|yearly|never)
     * @param float|null  $priority   우선순위 (0.0 ~ 1.0)
     */
    public function url(string $loc, ?string $lastmod = null, ?string $changefreq = null, ?float $priority = null): self
    {
        $c = clone $this;
        $entry = ['loc' => $loc];
        if ($lastmod !== null) {
            $entry['lastmod'] = $lastmod;
        }
        if ($changefreq !== null) {
            if (!in_array($changefreq, self::VALID_CHANGEFREQ, true)) {
                throw new \InvalidArgumentException(
                    "changefreq는 " . implode('|', self::VALID_CHANGEFREQ) . " 중 하나여야 합니다: {$changefreq}"
                );
            }
            $entry['changefreq'] = $changefreq;
        }
        if ($priority !== null) {
            $entry['priority'] = max(0.0, min(1.0, $priority));
        }
        $c->urls[] = $entry;
        return $c;
    }

    /**
     * 복수 URL 일괄 추가
     *
     * @param array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: float}> $urls
     */
    public function urls(array $urls): self
    {
        $c = clone $this;
        foreach ($urls as $entry) {
            if (isset($entry['loc'])) {
                $c->urls[] = $entry;
            }
        }
        return $c;
    }

    /**
     * DB 쿼리 결과에서 URL 자동 생성
     *
     * @param array<int, array<string, mixed>> $rows    DB 행 배열
     * @param string $urlPattern  URL 패턴 (예: '/post/{slug}', '/page/{id}')
     * @param string $dateCol     날짜 컬럼명
     * @param string $changefreq  변경 빈도
     * @param float  $priority    우선순위
     */
    public function fromQuery(
        array $rows,
        string $urlPattern,
        string $dateCol = 'updated_at',
        string $changefreq = 'weekly',
        float $priority = 0.7,
    ): self {
        if (!in_array($changefreq, self::VALID_CHANGEFREQ, true)) {
            throw new \InvalidArgumentException(
                "changefreq는 " . implode('|', self::VALID_CHANGEFREQ) . " 중 하나여야 합니다: {$changefreq}"
            );
        }
        $priority = max(0.0, min(1.0, $priority));
        $c = clone $this;

        foreach ($rows as $row) {
            // URL 패턴에서 {key} 치환
            $loc = preg_replace_callback('/\{(\w+)\}/', function (array $m) use ($row): string {
                return (string) ($row[$m[1]] ?? '');
            }, $urlPattern) ?? $urlPattern;

            $entry = [
                'loc'        => $loc,
                'changefreq' => $changefreq,
                'priority'   => $priority,
            ];

            if (isset($row[$dateCol]) && $row[$dateCol] !== '') {
                $ts = strtotime((string) $row[$dateCol]);
                $entry['lastmod'] = date('Y-m-d', $ts !== false ? $ts : time());
            }

            $c->urls[] = $entry;
        }

        return $c;
    }

    /**
     * 사이트맵 인덱스 생성
     *
     * @param array<int, string|array{loc: string, lastmod?: string}> $sitemaps
     */
    public function index(array $sitemaps): self
    {
        $c = clone $this;
        $c->isIndex = true;
        $c->sitemaps = [];

        foreach ($sitemaps as $entry) {
            if (is_string($entry)) {
                $c->sitemaps[] = ['loc' => $entry];
            } elseif (is_array($entry) && isset($entry['loc'])) {
                $c->sitemaps[] = $entry;
            }
        }

        return $c;
    }

    /** XML 문자열 반환 */
    public function render(): string
    {
        return $this->isIndex ? $this->buildIndex() : $this->buildUrlset();
    }

    /** Content-Type + echo + exit */
    public function output(): never
    {
        $xml = $this->render();

        // 캐시 저장
        if (class_exists('Cat\\Cache', false)) {
            $cacheKey = $this->isIndex ? 'sitemap:index' : 'sitemap:urlset';
            \cache()->set($cacheKey, $xml, $this->cacheTtl);
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
        exit;
    }

    /** 파일로 저장 */
    public function save(string $path): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($path, $this->render(), LOCK_EX) !== false;
    }

    /** URL 수 반환 */
    public function count(): int
    {
        return $this->isIndex ? count($this->sitemaps) : count($this->urls);
    }

    // ── 내부 ──

    /** URL 사이트맵 빌드 (50,000개 초과 시 RuntimeException) */
    private function buildUrlset(): string
    {
        if (count($this->urls) > self::MAX_URLS) {
            throw new \RuntimeException(
                "사이트맵 URL 수가 " . self::MAX_URLS . "개를 초과합니다 (" . count($this->urls) . "개). index()로 분할하세요."
            );
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->urls as $entry) {
            $loc = $this->resolveUrl($entry['loc']);
            $xml .= "<url>\n";
            $xml .= "  <loc>" . $this->esc($loc) . "</loc>\n";
            if (isset($entry['lastmod'])) {
                $xml .= "  <lastmod>" . $this->esc($entry['lastmod']) . "</lastmod>\n";
            }
            if (isset($entry['changefreq'])) {
                $xml .= "  <changefreq>" . $this->esc($entry['changefreq']) . "</changefreq>\n";
            }
            if (isset($entry['priority'])) {
                $xml .= "  <priority>" . number_format($entry['priority'], 1) . "</priority>\n";
            }
            $xml .= "</url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /** 사이트맵 인덱스 빌드 */
    private function buildIndex(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->sitemaps as $entry) {
            $loc = $this->resolveUrl($entry['loc']);
            $xml .= "<sitemap>\n";
            $xml .= "  <loc>" . $this->esc($loc) . "</loc>\n";
            if (isset($entry['lastmod'])) {
                $xml .= "  <lastmod>" . $this->esc($entry['lastmod']) . "</lastmod>\n";
            }
            $xml .= "</sitemap>\n";
        }

        $xml .= '</sitemapindex>';
        return $xml;
    }

    /** 상대 경로 → 절대 URL 변환 */
    private function resolveUrl(string $loc): string
    {
        // 이미 절대 URL이면 그대로 반환
        if (str_starts_with($loc, 'http://') || str_starts_with($loc, 'https://')) {
            return $loc;
        }
        return $this->baseUrl . '/' . ltrim($loc, '/');
    }

    /** XML 이스케이프 */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }
}
