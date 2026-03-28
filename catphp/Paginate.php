<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Paginate — 페이지네이션
 *
 * DB 쿼리 연동, 페이지 링크 HTML, JSON API용 toArray().
 */
final class Paginate
{
    private static ?self $instance = null;

    private int $currentPage = 1;
    private int $perPageVal = 20;
    private int $totalVal = 0;
    private array $items = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 현재 페이지 설정 */
    public function page(?int $page = null): self
    {
        $c = clone $this;
        $c->currentPage = max(1, $page ?? (int) (\input('page') ?? 1));
        return $c;
    }

    /** 페이지당 항목 수 */
    public function perPage(int $perPage): self
    {
        $c = clone $this;
        $c->perPageVal = max(1, $perPage);
        return $c;
    }

    /** 전체 항목 수 설정 */
    public function total(int $total): self
    {
        $c = clone $this;
        $c->totalVal = $total;
        return $c;
    }

    /** 항목 설정 */
    public function items(array $items): self
    {
        $c = clone $this;
        $c->items = $items;
        return $c;
    }

    /** 오프셋 계산 */
    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPageVal;
    }

    /** 마지막 페이지 */
    public function lastPage(): int
    {
        return (int) ceil($this->totalVal / max($this->perPageVal, 1));
    }

    /** 페이지 링크 HTML (웹용, 윈도우 트렁케이션) */
    public function links(string $urlPattern = '?page={page}', int $window = 2): string
    {
        $last = $this->lastPage();
        if ($last <= 1) {
            return '';
        }

        $html = '<nav class="pagination">';

        // 페이지 수가 적으면 전체 출력
        $threshold = ($window * 2) + 5;
        if ($last <= $threshold) {
            for ($i = 1; $i <= $last; $i++) {
                $html .= $this->pageLink($i, $urlPattern);
            }
        } else {
            // 첫 페이지
            $html .= $this->pageLink(1, $urlPattern);

            // 윈도우 시작 계산
            $start = max(2, $this->currentPage - $window);
            $end = min($last - 1, $this->currentPage + $window);

            if ($start > 2) {
                $html .= '<span class="ellipsis">...</span> ';
            }
            for ($i = $start; $i <= $end; $i++) {
                $html .= $this->pageLink($i, $urlPattern);
            }
            if ($end < $last - 1) {
                $html .= '<span class="ellipsis">...</span> ';
            }

            // 마지막 페이지
            $html .= $this->pageLink($last, $urlPattern);
        }

        $html .= '</nav>';
        return $html;
    }

    /** 개별 페이지 링크 HTML */
    private function pageLink(int $page, string $urlPattern): string
    {
        $url = htmlspecialchars(str_replace('{page}', (string) $page, $urlPattern), ENT_QUOTES, 'UTF-8');
        $active = $page === $this->currentPage ? ' class="active"' : '';
        return "<a href=\"{$url}\"{$active}>{$page}</a> ";
    }

    // ── 편의 게터 ──

    /** 다음 페이지 존재 여부 */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /** 이전 페이지 존재 여부 */
    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }

    /** 현재 페이지 번호 */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /** 페이지당 항목 수 */
    public function getPerPage(): int
    {
        return $this->perPageVal;
    }

    /** 전체 항목 수 */
    public function getTotal(): int
    {
        return $this->totalVal;
    }

    // ── DB 연동 ──

    /**
     * DB 쿼리 결과로 페이지네이션 생성
     *
     * 주의: 내부적으로 2회 쿼리가 실행됩니다 (count + select).
     * 대용량 테이블에서는 total 값을 캐싱하거나
     * fromArray()를 사용하여 미리 계산된 total을 전달하는 것을 권장합니다.
     */
    public function fromQuery(DB $query, int $perPage = 20): self
    {
        $c = clone $this;
        $c->perPageVal = max(1, $perPage);
        $c->currentPage = max(1, (int) (\input('page') ?? 1));
        $c->totalVal = $query->count();
        $c->items = $query->limit($c->perPageVal)->offset($c->offset())->all();
        return $c;
    }

    /** API용 배열 변환 (Json.php의 paginated()에 전달) */
    public function toArray(): array
    {
        return [
            'data'  => $this->items,
            'total' => $this->totalVal,
            'page'  => $this->currentPage,
            'per_page' => $this->perPageVal,
            'last_page' => $this->lastPage(),
        ];
    }
}
