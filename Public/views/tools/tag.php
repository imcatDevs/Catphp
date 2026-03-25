<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--dark);">label</i>
        <div><h4 class="mb-0">Tag</h4><span class="text-muted caption">Cat\Tag — 태그 시스템</span></div>
        <span class="badge badge--dark badge--sm ms-auto">tag()</span>
    </div>

    <p class="mb-2"><strong>다형성(Polymorphic) 태그</strong> 시스템입니다. 게시글, 상품 등 다양한 모델에 태그를 부착·분리·동기화할 수 있습니다.</p>
    <p class="mb-3"><code>attach()</code>/<code>detach()</code>/<code>sync()</code>로 태그를 관리하고, <code>tagged()</code>로 특정 태그가 달린 항목을 조회합니다. <code>cloud()</code>로 태그 클라우드 데이터, <code>popular()</code>로 인기 태그를 가져옵니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:300px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>attach(string $type, int $id, array $tags)</code></td><td><code>void</code></td><td>태그 부착</td></tr>
                    <tr><td><code>detach(string $type, int $id, array $tags)</code></td><td><code>void</code></td><td>태그 분리</td></tr>
                    <tr><td><code>sync(string $type, int $id, array $tags)</code></td><td><code>void</code></td><td>태그 동기화 (기존 삭제 후 재부착)</td></tr>
                    <tr><td><code>tagged(string $tag)</code></td><td><code>array</code></td><td>태그에 해당하는 항목 조회</td></tr>
                    <tr><td><code>tags(string $type, int $id)</code></td><td><code>array</code></td><td>항목의 태그 목록</td></tr>
                    <tr><td><code>cloud()</code></td><td><code>array</code></td><td>태그 클라우드 (이름 + 개수)</td></tr>
                    <tr><td><code>popular(int $limit)</code></td><td><code>array</code></td><td>인기 태그 N개</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 게시글에 태그 부착</span>
<span class="hl-f">tag</span>()-&gt;<span class="hl-f">attach</span>(<span class="hl-s">'posts'</span>, <span class="hl-v">$postId</span>, [<span class="hl-s">'PHP'</span>, <span class="hl-s">'프레임워크'</span>, <span class="hl-s">'오픈소스'</span>]);

<span class="hl-c">// 태그 동기화 (기존 태그 제거 후 새로 설정)</span>
<span class="hl-f">tag</span>()-&gt;<span class="hl-f">sync</span>(<span class="hl-s">'posts'</span>, <span class="hl-v">$postId</span>, [<span class="hl-s">'PHP'</span>, <span class="hl-s">'CatPHP'</span>]);

<span class="hl-c">// 'PHP' 태그가 달린 모든 게시글</span>
<span class="hl-v">$posts</span> = <span class="hl-f">tag</span>()-&gt;<span class="hl-f">tagged</span>(<span class="hl-s">'PHP'</span>);

<span class="hl-c">// 인기 태그 10개</span>
<span class="hl-v">$popular</span> = <span class="hl-f">tag</span>()-&gt;<span class="hl-f">popular</span>(<span class="hl-n">10</span>);</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>다형성:</strong> <code>attach('post', $id, [...])</code>에서 첫 인자가 모델 타입입니다. 게시글, 상품, 페이지 등 다양한 모델에 동일한 태그 시스템을 사용할 수 있습니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/slug" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Slug</a>
    </div>
</div>
