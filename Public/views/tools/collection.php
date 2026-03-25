<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--primary);">view_list</i>
        <div><h4 class="mb-0">Collection</h4><span class="text-muted caption">Cat\Collection — 배열 체이닝</span></div>
        <span class="badge badge--primary badge--sm ms-auto">collect()</span>
    </div>

    <p class="mb-2"><strong>배열 체이닝</strong> 도구입니다. map, filter, sort, group, pluck, reduce 등 40+ 메서드를 파이프라인으로 연결합니다.</p>
    <p class="mb-3">원본 배열을 변경하지 않으며(이뮤터블), 모든 체이닝 메서드는 새 Collection을 반환합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">주요 메서드 (40+)</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th>카테고리</th><th>메서드</th></tr></thead>
                <tbody>
                    <tr><td><strong>변환</strong></td><td><code>map</code>, <code>filter</code>, <code>reject</code>, <code>reduce</code>, <code>flatten</code>, <code>flatMap</code>, <code>pluck</code></td></tr>
                    <tr><td><strong>필터</strong></td><td><code>where</code>, <code>whereIn</code>, <code>whereNull</code>, <code>whereNotNull</code>, <code>only</code>, <code>except</code></td></tr>
                    <tr><td><strong>정렬</strong></td><td><code>sort</code>, <code>sortDesc</code>, <code>sortBy</code>, <code>sortKeys</code>, <code>reverse</code></td></tr>
                    <tr><td><strong>접근</strong></td><td><code>first</code>, <code>last</code>, <code>nth</code>, <code>take</code>, <code>skip</code>, <code>slice</code>, <code>chunk</code></td></tr>
                    <tr><td><strong>집계</strong></td><td><code>sum</code>, <code>avg</code>, <code>min</code>, <code>max</code>, <code>median</code>, <code>count</code></td></tr>
                    <tr><td><strong>조건</strong></td><td><code>contains</code>, <code>every</code>, <code>some</code>, <code>isEmpty</code>, <code>isNotEmpty</code></td></tr>
                    <tr><td><strong>결합</strong></td><td><code>merge</code>, <code>unique</code>, <code>groupBy</code>, <code>values</code>, <code>keys</code>, <code>flip</code></td></tr>
                    <tr><td><strong>출력</strong></td><td><code>toArray</code>, <code>toJson</code>, <code>implode</code>, <code>each</code>, <code>tap</code></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// DB 결과 → 체이닝</span>
<span class="hl-v">$result</span> = <span class="hl-f">collect</span>(<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">all</span>())
    -&gt;<span class="hl-f">where</span>(<span class="hl-s">'status'</span>, <span class="hl-s">'active'</span>)
    -&gt;<span class="hl-f">sortBy</span>(<span class="hl-s">'name'</span>)
    -&gt;<span class="hl-f">pluck</span>(<span class="hl-s">'email'</span>)
    -&gt;<span class="hl-f">unique</span>()
    -&gt;<span class="hl-f">toArray</span>();

<span class="hl-c">// 집계</span>
<span class="hl-v">$avg</span> = <span class="hl-f">collect</span>(<span class="hl-v">$orders</span>)-&gt;<span class="hl-f">avg</span>(<span class="hl-s">'total'</span>);
<span class="hl-v">$top</span> = <span class="hl-f">collect</span>(<span class="hl-v">$orders</span>)-&gt;<span class="hl-f">sortBy</span>(<span class="hl-s">'total'</span>, <span class="hl-s">'desc'</span>)-&gt;<span class="hl-f">take</span>(<span class="hl-n">5</span>);

<span class="hl-c">// 그룹핑</span>
<span class="hl-v">$byCity</span> = <span class="hl-f">collect</span>(<span class="hl-v">$users</span>)-&gt;<span class="hl-f">groupBy</span>(<span class="hl-s">'city'</span>);</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>이뮤터블:</strong> 모든 체이닝은 새 인스턴스를 반환합니다. 원본 컬렉션은 변경되지 않으므로 안전하게 파이프라인을 구성할 수 있습니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/paginate" class="badge badge--soft badge--success badge--sm" style="cursor:pointer;">Paginate</a>
    </div>
</div>
