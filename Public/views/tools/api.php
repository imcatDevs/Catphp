<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--info);">api</i>
        <div><h4 class="mb-0">Api</h4><span class="text-muted caption">Cat\Api — API 미들웨어 통합</span></div>
        <span class="badge badge--info badge--sm ms-auto">api()</span>
    </div>

    <p class="mb-2">API 라우트에 필요한 <strong>보안 미들웨어를 한 번에 적용</strong>합니다. CORS, Rate Limiting, JWT 인증, Guard 입력 보호를 통합하여 API 개발 시 반복적인 미들웨어 설정을 줄입니다.</p>
    <p class="mb-3"><code>auth()->bearer()</code>로 Authorization 헤더에서 JWT 토큰을 추출하고, <code>auth()->verifyToken()</code>으로 유효성을 검증합니다. 인증 실패 시 자동으로 <code>401 JSON</code> 응답을 반환합니다.</p>

    <h6 class="mb-2">API 라우트 패턴</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// API 그룹 — CORS + Rate + Auth + Guard 통합</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">group</span>(<span class="hl-s">'/api'</span>, <span class="hl-k">function</span>() {
    <span class="hl-c">// 공개 엔드포인트</span>
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">post</span>(<span class="hl-s">'/login'</span>, <span class="hl-k">function</span>() {
        <span class="hl-f">cors</span>()-&gt;<span class="hl-f">origins</span>([<span class="hl-s">'*'</span>])-&gt;<span class="hl-f">handle</span>();
        <span class="hl-f">rate</span>()-&gt;<span class="hl-f">limit</span>(<span class="hl-s">'login'</span>, <span class="hl-n">60</span>, <span class="hl-n">10</span>) || <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'Too many'</span>, <span class="hl-n">429</span>);
        <span class="hl-c">// 로그인 처리...</span>
    });

    <span class="hl-c">// 인증 필요 엔드포인트</span>
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/profile'</span>, <span class="hl-k">function</span>() {
        <span class="hl-f">cors</span>()-&gt;<span class="hl-f">origins</span>([<span class="hl-s">'https://myapp.com'</span>])-&gt;<span class="hl-f">handle</span>();
        <span class="hl-v">$token</span> = <span class="hl-f">auth</span>()-&gt;<span class="hl-f">bearer</span>();
        <span class="hl-v">$payload</span> = <span class="hl-v">$token</span> ? <span class="hl-f">auth</span>()-&gt;<span class="hl-f">verifyToken</span>(<span class="hl-v">$token</span>) : <span class="hl-k">null</span>;
        <span class="hl-k">if</span> (!<span class="hl-v">$payload</span>) <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'Unauthorized'</span>, <span class="hl-n">401</span>);
        <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'id'</span>, <span class="hl-v">$payload</span>[<span class="hl-s">'user_id'</span>])-&gt;<span class="hl-f">first</span>());
    });
});</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>연동 도구:</strong> Cors(CORS 헤더) + Rate(속도 제한) + Auth(JWT 인증) + Guard(입력 보호) + Json(응답 형식)</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/cors" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Cors</a>
        <a data-spa="/tool/rate" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Rate</a>
        <a data-spa="/tool/auth" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Auth</a>
        <a data-spa="/tool/json" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Json</a>
    </div>
</div>
