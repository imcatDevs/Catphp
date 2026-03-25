<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--warning);">link</i>
        <div><h4 class="mb-0">Slug</h4><span class="text-muted caption">Cat\Slug — URL 슬러그 생성</span></div>
        <span class="badge badge--warning badge--sm ms-auto">slug()</span>
    </div>

    <p class="mb-2">문자열을 <strong>URL 친화적인 슬러그</strong>로 변환합니다. 한국어·일본어·중국어 등 비라틴 문자는 음역(transliterate) 처리하고, 특수 문자는 하이픈으로 대체합니다.</p>
    <p class="mb-3"><code>unique()</code>는 DB 테이블의 기존 슬러그와 중복을 확인하여 자동으로 숫자 접미사(-2, -3, ...)를 붙입니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:320px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>make(string $text)</code></td><td><code>string</code></td><td>슬러그 생성</td></tr>
                    <tr><td><code>unique(string $text, callable $existsCheck, string $separator = '-', int $maxAttempts = 100)</code></td><td><code>string</code></td><td>콜백으로 중복 확인 후 유니크 슬러그</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 기본 슬러그 생성</span>
<span class="hl-f">slug</span>()-&gt;<span class="hl-f">make</span>(<span class="hl-s">'Hello World! 123'</span>);  <span class="hl-c">// → "hello-world-123"</span>
<span class="hl-f">slug</span>()-&gt;<span class="hl-f">make</span>(<span class="hl-s">'PHP 8.2 새 기능'</span>);     <span class="hl-c">// → "php-82-sae-gineung"</span>

<span class="hl-c">// DB 유니크 슬러그 (게시글 작성 시)</span>
<span class="hl-v">$slug</span> = <span class="hl-f">slug</span>()-&gt;<span class="hl-f">unique</span>(<span class="hl-v">$title</span>, <span class="hl-k">fn</span>(<span class="hl-k">string</span> <span class="hl-v">$s</span>) =&gt;
    <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'slug'</span>, <span class="hl-v">$s</span>)-&gt;<span class="hl-f">count</span>() &gt; <span class="hl-n">0</span>
);
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)-&gt;<span class="hl-f">insert</span>([
    <span class="hl-s">'title'</span> =&gt; <span class="hl-v">$title</span>,
    <span class="hl-s">'slug'</span>  =&gt; <span class="hl-v">$slug</span>,
]);</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>팁:</strong> <code>unique()</code>는 콜백으로 중복을 확인하여 <code>-2</code>, <code>-3</code> 접미사를 붙입니다. <code>maxAttempts</code>(기본 100)를 초과하면 예외를 던져 무한루프를 방지합니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/tag" class="badge badge--soft badge--dark badge--sm" style="cursor:pointer;">Tag</a>
        <a data-spa="/tool/feed" class="badge badge--soft badge--dark badge--sm" style="cursor:pointer;">Feed</a>
    </div>
</div>
