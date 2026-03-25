<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--success);">auto_stories</i>
        <div><h4 class="mb-0">Paginate</h4><span class="text-muted caption">Cat\Paginate — 페이지네이션</span></div>
        <span class="badge badge--success badge--sm ms-auto">paginate()</span>
    </div>

    <p class="mb-2">DB 쿼리 결과를 <strong>페이지 단위</strong>로 분할합니다. 현재 페이지, 총 개수, 페이지당 항목 수를 설정하면 <code>offset()</code>, <code>lastPage()</code>, <code>links()</code>를 자동 계산합니다.</p>
    <p class="mb-3"><code>links()</code>는 <strong>윈도우 트렁케이션</strong>을 지원하여, 총 페이지 수가 많아도 현재 페이지 주변의 링크만 표시합니다. <code>toArray()</code>로 JSON API 응답에 바로 사용할 수 있습니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>page(int $n)</code></td><td><code>self</code></td><td>현재 페이지 설정</td></tr>
                    <tr><td><code>perPage(int $n)</code></td><td><code>self</code></td><td>페이지당 항목 수</td></tr>
                    <tr><td><code>total(int $n)</code></td><td><code>self</code></td><td>전체 항목 수</td></tr>
                    <tr><td><code>items(array $items)</code></td><td><code>self</code></td><td>현재 페이지 데이터</td></tr>
                    <tr><td><code>offset()</code></td><td><code>int</code></td><td>SQL OFFSET 계산값</td></tr>
                    <tr><td><code>lastPage()</code></td><td><code>int</code></td><td>마지막 페이지 번호</td></tr>
                    <tr><td><code>links(string $pattern = '?page={page}', int $window = 2)</code></td><td><code>string</code></td><td>페이지 링크 HTML (윈도우 트럭케이션)</td></tr>
                    <tr><td><code>toArray()</code></td><td><code>array</code></td><td>JSON API용 배열 변환</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">DB + 페이지네이션</h6>
    <pre class="demo-code mb-3"><code><span class="hl-v">$page</span> = (<span class="hl-k">int</span>)(<span class="hl-v">$_GET</span>[<span class="hl-s">'page'</span>] ?? <span class="hl-n">1</span>);
<span class="hl-v">$perPage</span> = <span class="hl-n">10</span>;

<span class="hl-v">$pager</span> = <span class="hl-f">paginate</span>()
    -&gt;<span class="hl-f">page</span>(<span class="hl-v">$page</span>)
    -&gt;<span class="hl-f">perPage</span>(<span class="hl-v">$perPage</span>)
    -&gt;<span class="hl-f">total</span>(<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)-&gt;<span class="hl-f">count</span>());

<span class="hl-v">$posts</span> = <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)
    -&gt;<span class="hl-f">limit</span>(<span class="hl-v">$perPage</span>)
    -&gt;<span class="hl-f">offset</span>(<span class="hl-v">$pager</span>-&gt;<span class="hl-f">offset</span>())
    -&gt;<span class="hl-f">all</span>();

<span class="hl-c">// API 응답</span>
<span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-v">$pager</span>-&gt;<span class="hl-f">items</span>(<span class="hl-v">$posts</span>)-&gt;<span class="hl-f">toArray</span>());</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>clone 기반:</strong> 체이닝 메서드는 원본을 변경하지 않습니다. <code>$pager = paginate()->perPage(10)</code>로 공유 설정을 만들고, 라우트별로 <code>$pager->total($n)->page($p)</code>를 호출하세요.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/json" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Json</a>
        <a data-spa="/tool/search" class="badge badge--soft badge--secondary badge--sm" style="cursor:pointer;">Search</a>
    </div>
</div>
