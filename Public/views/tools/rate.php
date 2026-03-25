<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--info);">speed</i>
        <div><h4 class="mb-0">Rate</h4><span class="text-muted caption">Cat\Rate — 속도 제한</span></div>
        <span class="badge badge--info badge--sm ms-auto">rate()</span>
    </div>

    <p class="mb-2">파일 기반 <strong>Rate Limiting</strong> 도구입니다. IP, 사용자 ID, API 키 등을 기준으로 일정 시간 내 최대 요청 횟수를 제한합니다.</p>
    <p class="mb-3"><code>check()</code>는 요청이 허용되면 <code>true</code>, 초과하면 <code>false</code>를 반환합니다. <code>remaining()</code>으로 남은 횟수를 확인하고, <code>reset()</code>으로 카운터를 초기화할 수 있습니다. Firewall과 연동하면 반복 위반 시 자동 IP 차단이 가능합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">설정 — config/app.php</h6></div>
        <pre class="demo-code" style="border-radius:0 0 8px 8px;"><code><span class="hl-s">'rate'</span> =&gt; [
    <span class="hl-s">'path'</span> =&gt; __DIR__ . <span class="hl-s">'/../storage/rate'</span>,  <span class="hl-c">// 카운터 파일 경로</span>
]</code></pre>
    </div>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>limit(string $key, int $window, int $max)</code></td><td><code>bool</code></td><td>제한 확인 + 카운터 증가 (허용 시 true)</td></tr>
                    <tr><td><code>check(string $key, int $window, int $max)</code></td><td><code>bool</code></td><td>제한 확인만 (카운터 미증가)</td></tr>
                    <tr><td><code>remaining(string $key, int $window, int $max)</code></td><td><code>int</code></td><td>남은 요청 횟수</td></tr>
                    <tr><td><code>reset(string $key)</code></td><td><code>bool</code></td><td>카운터 초기화</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 사용법</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// IP 기반 — 분당 60회 제한 (limit: 카운터 증가 + 확인)</span>
<span class="hl-k">if</span> (!<span class="hl-f">rate</span>()-&gt;<span class="hl-f">limit</span>(<span class="hl-s">'api'</span>, <span class="hl-n">60</span>, <span class="hl-n">60</span>)) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'요청이 너무 많습니다'</span>, <span class="hl-k">null</span>, <span class="hl-n">429</span>);
}

<span class="hl-c">// API 키 기반 — 시간당 1000회 (check: 조회만, 카운터 미증가)</span>
<span class="hl-v">$apiKey</span> = <span class="hl-v">$_SERVER</span>[<span class="hl-s">'HTTP_X_API_KEY'</span>] ?? <span class="hl-s">''</span>;
<span class="hl-k">if</span> (!<span class="hl-f">rate</span>()-&gt;<span class="hl-f">check</span>(<span class="hl-s">'api:'</span> . <span class="hl-v">$apiKey</span>, <span class="hl-n">3600</span>, <span class="hl-n">1000</span>)) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'Rate limit 초과'</span>, <span class="hl-k">null</span>, <span class="hl-n">429</span>);
}

<span class="hl-c">// 남은 횟수를 헤더로 전송</span>
<span class="hl-f">header</span>(<span class="hl-s">'X-RateLimit-Remaining: '</span> . <span class="hl-f">rate</span>()-&gt;<span class="hl-f">remaining</span>(<span class="hl-s">'api'</span>, <span class="hl-n">60</span>, <span class="hl-n">60</span>));</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>팁:</strong> <code>limit()</code>는 호출할 때마다 카운터가 증가합니다. 같은 요청에서 여러 번 호출하면 횟수가 여러 번 소모되므로 주의하세요. <code>check()</code>는 조회만 하고 카운터를 증가시키지 않습니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/firewall" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Firewall</a>
        <a data-spa="/tool/ip" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Ip</a>
        <a data-spa="/tool/api" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Api</a>
    </div>
</div>
