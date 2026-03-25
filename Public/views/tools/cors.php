<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--info);">public</i>
        <div><h4 class="mb-0">Cors</h4><span class="text-muted caption">Cat\Cors — CORS 헤더 관리</span></div>
        <span class="badge badge--info badge--sm ms-auto">cors()</span>
    </div>

    <p class="mb-2"><strong>Cross-Origin Resource Sharing</strong> 헤더를 관리합니다. 허용 Origin, HTTP 메서드, 커스텀 헤더를 체이닝으로 설정하고, <code>handle()</code>로 Preflight(OPTIONS) 요청을 자동 처리합니다.</p>
    <p class="mb-3">체이닝 메서드는 <strong>clone 기반</strong>으로 싱글턴 상태를 변경하지 않으므로, 라우트별로 다른 CORS 정책을 적용할 수 있습니다. 와일드카드 Origin(<code>*</code>)과 Credentials 헤더의 동시 사용은 보안상 차단됩니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:260px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>origins(array $origins)</code></td><td><code>self</code></td><td>허용 Origin 설정 (<code>['*']</code> 또는 도메인 배열)</td></tr>
                    <tr><td><code>methods(array $methods)</code></td><td><code>self</code></td><td>허용 HTTP 메서드 배열</td></tr>
                    <tr><td><code>headers(array $headers)</code></td><td><code>self</code></td><td>허용 커스텀 헤더 배열</td></tr>
                    <tr><td><code>allow(array $origins, array $methods, array $headers)</code></td><td><code>self</code></td><td>종합 설정 (빈 배열 시 기존 값 유지)</td></tr>
                    <tr><td><code>handle()</code></td><td><code>void</code></td><td>CORS 헤더 출력 + OPTIONS 자동 응답</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 사용법</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 모든 Origin 허용</span>
<span class="hl-f">cors</span>()-&gt;<span class="hl-f">origins</span>([<span class="hl-s">'*'</span>])-&gt;<span class="hl-f">handle</span>();

<span class="hl-c">// 특정 도메인만 허용</span>
<span class="hl-f">cors</span>()
    -&gt;<span class="hl-f">origins</span>([<span class="hl-s">'https://myapp.com'</span>, <span class="hl-s">'https://admin.myapp.com'</span>])
    -&gt;<span class="hl-f">methods</span>([<span class="hl-s">'GET'</span>, <span class="hl-s">'POST'</span>, <span class="hl-s">'PUT'</span>, <span class="hl-s">'DELETE'</span>])
    -&gt;<span class="hl-f">headers</span>([<span class="hl-s">'Authorization'</span>, <span class="hl-s">'X-Custom-Header'</span>])
    -&gt;<span class="hl-f">handle</span>();

<span class="hl-c">// 종합 설정 — 한 번에 모두 지정</span>
<span class="hl-f">cors</span>()-&gt;<span class="hl-f">allow</span>(
    [<span class="hl-s">'https://myapp.com'</span>],
    [<span class="hl-s">'GET'</span>, <span class="hl-s">'POST'</span>],
    [<span class="hl-s">'Authorization'</span>]
)-&gt;<span class="hl-f">handle</span>();</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>보안:</strong> Origin <code>'*'</code>와 <code>allow(true)</code>(Credentials)를 동시에 사용하면 자동으로 Credentials 헤더가 제외됩니다. 이는 브라우저 보안 정책 위반을 방지합니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/router" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">Router</a>
        <a data-spa="/tool/api" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Api</a>
    </div>
</div>
