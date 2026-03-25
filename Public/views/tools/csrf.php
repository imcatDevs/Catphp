<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--danger);">verified_user</i>
        <div><h4 class="mb-0">Csrf</h4><span class="text-muted caption">Cat\Csrf — CSRF 방어</span></div>
        <span class="badge badge--danger badge--sm ms-auto">csrf()</span>
    </div>

    <p class="mb-2"><strong>Cross-Site Request Forgery</strong> 공격을 방어합니다. 세션 기반 일회용 토큰을 생성하고, 폼 제출 시 토큰 일치 여부를 검증합니다.</p>
    <p class="mb-3"><code>field()</code>로 hidden input을 자동 생성하고, <code>middleware()</code>로 POST/PUT/PATCH/DELETE 요청에 자동 검증을 적용할 수 있습니다. 토큰은 세션에 저장되며 검증 후 재생성됩니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:200px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>token()</code></td><td><code>string</code></td><td>현재 CSRF 토큰 반환 (없으면 생성)</td></tr>
                    <tr><td><code>field()</code></td><td><code>string</code></td><td><code>&lt;input type="hidden"&gt;</code> HTML 생성</td></tr>
                    <tr><td><code>verify()</code></td><td><code>bool</code></td><td>요청의 토큰 검증 (POST body 또는 X-CSRF-Token 헤더)</td></tr>
                    <tr><td><code>middleware()</code></td><td><code>callable</code></td><td>자동 검증 미들웨어 (실패 시 403)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">폼에서 사용</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 뷰 템플릿 — hidden 필드 자동 삽입</span>
&lt;form method="POST" action="/contact"&gt;
    <span class="hl-k">&lt;?=</span> <span class="hl-f">csrf</span>()-&gt;<span class="hl-f">field</span>() <span class="hl-k">?&gt;</span>
    &lt;input name="message" /&gt;
    &lt;button type="submit"&gt;전송&lt;/button&gt;
&lt;/form&gt;

<span class="hl-c">// 핸들러에서 수동 검증</span>
<span class="hl-k">if</span> (!<span class="hl-f">csrf</span>()-&gt;<span class="hl-f">verify</span>()) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'CSRF 토큰 불일치'</span>, <span class="hl-n">403</span>);
}</code></pre>

    <h6 class="mb-2">미들웨어로 자동 적용</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 글로벌 미들웨어 등록 — 모든 변경 요청에 자동 검증</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">csrf</span>()-&gt;<span class="hl-f">middleware</span>());

<span class="hl-c">// AJAX에서는 헤더로 전송</span>
<span class="hl-c">// fetch('/api/data', { headers: { 'X-CSRF-Token': token } })</span></code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>주의:</strong> CSRF 미들웨어는 GET/HEAD/OPTIONS 요청은 건너뛰고, POST/PUT/PATCH/DELETE만 검증합니다. API 전용 라우트에서는 JWT 인증으로 대체하는 것이 일반적입니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
        <a data-spa="/tool/auth" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Auth</a>
    </div>
</div>
