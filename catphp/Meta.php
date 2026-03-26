<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Meta — SEO 메타 태그
 *
 * title, description, Open Graph, Twitter Card, JSON-LD, sitemap.
 */
final class Meta
{
    private static ?self $instance = null;

    private string $titleStr = '';
    private string $descriptionStr = '';
    private string $canonicalUrl = '';
    /** @var array<string, string> */
    private array $ogTags = [];
    /** @var array<string, string> */
    private array $twitterTags = [];
    private ?array $jsonLdData = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function title(string $title): self
    {
        $this->titleStr = $title;
        return $this;
    }

    public function description(string $description): self
    {
        $this->descriptionStr = $description;
        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonicalUrl = $url;
        return $this;
    }

    public function og(string $property, string $content): self
    {
        $this->ogTags[$property] = $content;
        return $this;
    }

    public function twitter(string $name, string $content): self
    {
        $this->twitterTags[$name] = $content;
        return $this;
    }

    public function jsonLd(array $data): self
    {
        $this->jsonLdData = $data;
        return $this;
    }

    /** 상태 초기화 (새 페이지 렌더링 전 호출) */
    public function reset(): self
    {
        $this->titleStr = '';
        $this->descriptionStr = '';
        $this->canonicalUrl = '';
        $this->ogTags = [];
        $this->twitterTags = [];
        $this->jsonLdData = null;
        return $this;
    }

    /** 메타 태그 HTML 렌더링 */
    public function render(): string
    {
        $html = '';

        if ($this->titleStr !== '') {
            $t = htmlspecialchars($this->titleStr, ENT_QUOTES, 'UTF-8');
            $html .= "<title>{$t}</title>\n";
            $html .= "<meta property=\"og:title\" content=\"{$t}\">\n";
        }

        if ($this->descriptionStr !== '') {
            $d = htmlspecialchars($this->descriptionStr, ENT_QUOTES, 'UTF-8');
            $html .= "<meta name=\"description\" content=\"{$d}\">\n";
            $html .= "<meta property=\"og:description\" content=\"{$d}\">\n";
        }

        if ($this->canonicalUrl !== '') {
            $cu = htmlspecialchars($this->canonicalUrl, ENT_QUOTES, 'UTF-8');
            $html .= "<link rel=\"canonical\" href=\"{$cu}\">\n";
        }

        foreach ($this->ogTags as $prop => $content) {
            $p = htmlspecialchars($prop, ENT_QUOTES, 'UTF-8');
            $c = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            $html .= "<meta property=\"og:{$p}\" content=\"{$c}\">\n";
        }

        foreach ($this->twitterTags as $name => $content) {
            $n = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $c = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
            $html .= "<meta name=\"twitter:{$n}\" content=\"{$c}\">\n";
        }

        if ($this->jsonLdData !== null) {
            $json = json_encode($this->jsonLdData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?: '{}';
            $html .= "<script type=\"application/ld+json\">{$json}</script>\n";
        }

        return $html;
    }

    /** sitemap.xml 생성 */
    public function sitemap(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $loc = htmlspecialchars($url['loc'] ?? '', ENT_XML1, 'UTF-8');
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n";
            if (isset($url['lastmod'])) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }
            if (isset($url['priority'])) {
                $xml .= "    <priority>{$url['priority']}</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }
}
