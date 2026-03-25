<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--primary);">alt_route</i>
        <div><h4 class="mb-0">Router</h4><span class="text-muted caption">Cat\Router — HTTP 라우터</span></div>
        <span class="badge badge--primary badge--sm ms-auto">router()</span>
    </div>

    <p class="mb-2">경량 HTTP 라우터입니다. <strong>GET/POST/PUT/PATCH/DELETE/OPTIONS</strong> 메서드를 지원하며, <code>{param}</code> 동적 파라미터, 접두사 그룹, 글로벌 미들웨어를 제공합니다.</p>
    <p class="mb-3">핸들러가 <strong>string을 반환</strong>하면 Router가 자동으로 <code>echo</code> 출력하고, <strong>void</strong>이면 핸들러 내부에서 직접 출력합니다(<code>json()->ok()</code> 등). 트레일링 슬래시는 자동 정규화되며, <code>HEAD</code> 요청은 <code>GET</code> 핸들러로 폴백합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:260px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>get(string $path, callable $fn)</code></td><td><code>self</code></td><td>GET 라우트 등록</td></tr>
                    <tr><td><code>post(string $path, callable $fn)</code></td><td><code>self</code></td><td>POST 라우트 등록</td></tr>
                    <tr><td><code>put(string $path, callable $fn)</code></td><td><code>self</code></td><td>PUT 라우트 등록</td></tr>
                    <tr><td><code>patch(string $path, callable $fn)</code></td><td><code>self</code></td><td>PATCH 라우트 등록</td></tr>
                    <tr><td><code>delete(string $path, callable $fn)</code></td><td><code>self</code></td><td>DELETE 라우트 등록</td></tr>
                    <tr><td><code>options(string $path, callable $fn)</code></td><td><code>self</code></td><td>OPTIONS 라우트 등록</td></tr>
                    <tr><td><code>any(string $path, callable $fn)</code></td><td><code>self</code></td><td>모든 HTTP 메서드 매칭</td></tr>
                    <tr><td><code>group(string $prefix, callable $fn)</code></td><td><code>self</code></td><td>접두사 그룹. 콜백 안에서 라우트 등록</td></tr>
                    <tr><td><code>use(callable $mw)</code></td><td><code>self</code></td><td>글로벌 미들웨어 (모든 요청 전에 실행)</td></tr>
                    <tr><td><code>redirect(string $from, string $to, int $code)</code></td><td><code>self</code></td><td>리다이렉트 라우트. 기본 302</td></tr>
                    <tr><td><code>notFound(callable $fn)</code></td><td><code>self</code></td><td>404 핸들러 등록</td></tr>
                    <tr><td><code>dispatch()</code></td><td><code>void</code></td><td>요청 매칭 + 핸들러 실행</td></tr>
                    <tr><td><code>render(string $tpl, array $data)</code></td><td><code>string</code></td><td>뷰 템플릿 렌더링 (ob_start + require)</td></tr>
                    <tr><td><code>setViewPath(string $path)</code></td><td><code>self</code></td><td>뷰 디렉토리 경로 변경</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 라우트 등록</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 정적 라우트</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-f">render</span>(<span class="hl-s">'index'</span>));

<span class="hl-c">// 동적 파라미터 — 핸들러 인자로 주입</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/posts/{slug}'</span>, <span class="hl-k">fn</span>(<span class="hl-k">string</span> <span class="hl-v">$slug</span>) =&gt;
    <span class="hl-f">render</span>(<span class="hl-s">'post'</span>, [<span class="hl-s">'slug'</span> =&gt; <span class="hl-v">$slug</span>])
);

<span class="hl-c">// POST — 폼 처리</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">post</span>(<span class="hl-s">'/contact'</span>, <span class="hl-k">function</span>() {
    <span class="hl-v">$data</span> = <span class="hl-f">input</span>();
    <span class="hl-c">// 처리 로직...</span>
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>([<span class="hl-s">'message'</span> =&gt; <span class="hl-s">'전송 완료'</span>]);
});</code></pre>

    <h6 class="mb-2">그룹 + 미들웨어</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 글로벌 미들웨어 — 모든 요청에 적용</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">guard</span>()-&gt;<span class="hl-f">middleware</span>());

<span class="hl-c">// API 그룹 — /api 접두사 자동 부여</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">group</span>(<span class="hl-s">'/api'</span>, <span class="hl-k">function</span>() {
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/users'</span>, <span class="hl-k">fn</span>() =&gt;
        <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">all</span>())
    );
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">post</span>(<span class="hl-s">'/users'</span>, <span class="hl-k">fn</span>() =&gt;
        <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">insert</span>(<span class="hl-f">input</span>()), <span class="hl-n">201</span>)
    );
});

<span class="hl-c">// 리다이렉트 + 404</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">redirect</span>(<span class="hl-s">'/old-page'</span>, <span class="hl-s">'/new-page'</span>, <span class="hl-n">301</span>);
<span class="hl-f">router</span>()-&gt;<span class="hl-f">notFound</span>(<span class="hl-k">fn</span>() =&gt; <span class="hl-f">render</span>(<span class="hl-s">'404'</span>));

<span class="hl-c">// 라우트 실행</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">dispatch</span>();</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>응답 생명주기:</strong> ① <code>dispatch()</code> → 매칭 ② 핸들러가 string 반환 → Router가 echo ③ 핸들러가 void → 내부에서 직접 출력 (<code>json()->ok()</code>는 <code>header()</code> + <code>echo</code> + <code>exit</code>) ④ 매칭 실패 → 404 핸들러</span>
    </div>
    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> <code>render()</code>는 뷰 디렉토리 밖의 파일 접근을 <code>realpath()</code> 검증으로 차단합니다. <code>'..'</code>과 null 바이트는 자동 제거됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
        <a data-spa="/tool/cors" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Cors</a>
        <a data-spa="/tool/csrf" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Csrf</a>
        <a data-spa="/tool/json" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Json</a>
    </div>
</div>
