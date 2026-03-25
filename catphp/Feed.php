<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Feed — RSS/Atom 피드
 *
 * @config array{
 *     limit?: int,        // 피드 항목 수 (기본 20)
 *     cache_ttl?: int,    // 피드 캐시 TTL (기본 3600)
 * } feed  → config('feed.limit')
 */
final class Feed
{
    private static ?self $instance = null;

    private string $titleStr = '';
    private string $descriptionStr = '';
    private string $link = '';
    private array $feedItems = [];

    private function __construct(
        private readonly int $limit,
        private readonly int $cacheTtl,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            limit: (int) (\config('feed.limit') ?? 20),
            cacheTtl: (int) (\config('feed.cache_ttl') ?? 3600),
        );
    }

    public function title(string $title): self
    {
        $c = clone $this;
        $c->titleStr = $title;
        return $c;
    }

    public function description(string $description): self
    {
        $c = clone $this;
        $c->descriptionStr = $description;
        return $c;
    }

    public function link(string $url): self
    {
        $c = clone $this;
        $c->link = $url;
        return $c;
    }

    /** 피드 아이템 설정 */
    public function items(array $items): self
    {
        $c = clone $this;
        $c->feedItems = $items;
        return $c;
    }

    /** DB 쿼리 결과에서 피드 아이템 생성 */
    public function fromQuery(array $rows, string $titleCol = 'title', string $contentCol = 'content', string $dateCol = 'created_at', string $slugCol = 'slug'): self
    {
        $c = clone $this;
        $c->feedItems = [];

        foreach (array_slice($rows, 0, $this->limit) as $row) {
            $c->feedItems[] = [
                'title'       => \guard()->clean($row[$titleCol] ?? ''),
                'description' => \guard()->clean(mb_substr(strip_tags($row[$contentCol] ?? ''), 0, 300)),
                'link'        => \guard()->clean($row[$slugCol] ?? ''),
                'pubDate'     => $row[$dateCol] ?? date('Y-m-d H:i:s'),
            ];
        }

        return $c;
    }

    /** RSS 2.0 출력 */
    public function rss(): never
    {
        $xml = $this->buildRss();

        // 캐시 저장
        if (class_exists('Cat\\Cache', false)) {
            \cache()->set('feed:rss', $xml, $this->cacheTtl);
        }

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $xml;
        exit;
    }

    /** Atom 출력 */
    public function atom(): never
    {
        $xml = $this->buildAtom();

        header('Content-Type: application/atom+xml; charset=utf-8');
        echo $xml;
        exit;
    }

    /** RSS 2.0 문자열 반환 */
    public function render(string $format = 'rss'): string
    {
        return $format === 'atom' ? $this->buildAtom() : $this->buildRss();
    }

    private function buildRss(): string
    {
        $title = $this->esc($this->titleStr);
        $desc = $this->esc($this->descriptionStr);
        $link = $this->esc($this->link);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0">' . "\n";
        $xml .= "<channel>\n<title>{$title}</title>\n<description>{$desc}</description>\n<link>{$link}</link>\n";

        foreach ($this->feedItems as $item) {
            $xml .= "<item>\n";
            $xml .= "  <title>" . $this->esc($item['title'] ?? '') . "</title>\n";
            $xml .= "  <description>" . $this->esc($item['description'] ?? '') . "</description>\n";
            $itemLink = $this->esc($item['link'] ?? '');
            $xml .= "  <link>{$itemLink}</link>\n";
            $xml .= "  <guid isPermaLink=\"true\">{$itemLink}</guid>\n";
            if (isset($item['pubDate'])) {
                $ts = strtotime($item['pubDate']);
                $xml .= "  <pubDate>" . date('r', $ts !== false ? $ts : time()) . "</pubDate>\n";
            }
            $xml .= "</item>\n";
        }

        $xml .= "</channel>\n</rss>";
        return $xml;
    }

    private function buildAtom(): string
    {
        $title = $this->esc($this->titleStr);
        $link = $this->esc($this->link);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "<title>{$title}</title>\n<id>{$link}</id>\n<link href=\"{$link}\"/>\n<updated>" . date('c') . "</updated>\n";

        foreach ($this->feedItems as $item) {
            $xml .= "<entry>\n";
            $xml .= "  <title>" . $this->esc($item['title'] ?? '') . "</title>\n";
            $xml .= "  <summary>" . $this->esc($item['description'] ?? '') . "</summary>\n";
            $itemLink = $this->esc($item['link'] ?? '');
            $xml .= "  <id>{$itemLink}</id>\n";
            $xml .= "  <link href=\"{$itemLink}\"/>\n";
            if (isset($item['pubDate'])) {
                $ts = strtotime($item['pubDate']);
                $xml .= "  <updated>" . date('c', $ts !== false ? $ts : time()) . "</updated>\n";
            }
            $xml .= "</entry>\n";
        }

        $xml .= '</feed>';
        return $xml;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }
}
