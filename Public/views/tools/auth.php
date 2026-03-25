<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--danger);">shield</i>
        <div><h4 class="mb-0">Auth</h4><span class="text-muted caption">Cat\Auth — 인증 · JWT · 비밀번호</span></div>
        <span class="badge badge--danger badge--sm ms-auto">auth()</span>
    </div>

    <p class="mb-2"><strong>Argon2id</strong> 비밀번호 해싱, <strong>HMAC-SHA256 JWT</strong> 토큰 발행/검증, 세션 기반 로그인 관리를 하나의 도구로 제공합니다. 비밀번호는 PHP 내장 <code>password_hash(PASSWORD_ARGON2ID)</code>를 사용하여 최고 수준의 보안을 보장합니다.</p>
    <p class="mb-3">JWT 토큰은 <code>header.payload.signature</code> 구조이며, 시크릿 키가 비어 있으면 <code>RuntimeException</code>을 던져 안전하지 않은 토큰 발행을 원천 차단합니다. <code>bearer()</code>는 <code>Authorization: Bearer xxx</code> 헤더에서 토큰을 자동 추출합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">설정 — config/app.php</h6></div>
        <pre class="demo-code" style="border-radius:0 0 8px 8px;"><code><span class="hl-s">'auth'</span> =&gt; [
    <span class="hl-s">'secret'</span> =&gt; <span class="hl-s">'your-jwt-secret-key'</span>,  <span class="hl-c">// JWT 서명 키 (필수)</span>
    <span class="hl-s">'ttl'</span>    =&gt; <span class="hl-n">3600</span>,                     <span class="hl-c">// 토큰 유효 시간 (초)</span>
]</code></pre>
    </div>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>hashPassword(string $pw)</code></td><td><code>string</code></td><td>Argon2id 해싱</td></tr>
                    <tr><td><code>verifyPassword(string $pw, string $hash)</code></td><td><code>bool</code></td><td>해시 검증</td></tr>
                    <tr><td><code>createToken(array $payload, ?int $ttl)</code></td><td><code>string</code></td><td>JWT 발행 (exp 자동 추가, ttl 선택)</td></tr>
                    <tr><td><code>verifyToken(string $token)</code></td><td><code>?array</code></td><td>JWT 검증. 만료/변조 시 null</td></tr>
                    <tr><td><code>bearer()</code></td><td><code>?string</code></td><td>Authorization 헤더에서 Bearer 토큰 추출</td></tr>
                    <tr><td><code>user()</code></td><td><code>?array</code></td><td>세션 기반 현재 로그인 사용자</td></tr>
                    <tr><td><code>check()</code></td><td><code>bool</code></td><td>로그인 상태 확인</td></tr>
                    <tr><td><code>login(array $user)</code></td><td><code>void</code></td><td>세션에 사용자 정보 저장 + ID 재생성</td></tr>
                    <tr><td><code>logout()</code></td><td><code>void</code></td><td>세션 삭제 (session_destroy)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">비밀번호 해싱 + 로그인</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 회원가입 — 비밀번호 해싱</span>
<span class="hl-v">$hash</span> = <span class="hl-f">auth</span>()-&gt;<span class="hl-f">hashPassword</span>(<span class="hl-s">'my-password'</span>);
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">insert</span>([<span class="hl-s">'email'</span> =&gt; <span class="hl-v">$email</span>, <span class="hl-s">'password'</span> =&gt; <span class="hl-v">$hash</span>]);

<span class="hl-c">// 로그인 — 비밀번호 검증</span>
<span class="hl-v">$user</span> = <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'email'</span>, <span class="hl-v">$email</span>)-&gt;<span class="hl-f">first</span>();
<span class="hl-k">if</span> (<span class="hl-v">$user</span> &amp;&amp; <span class="hl-f">auth</span>()-&gt;<span class="hl-f">verifyPassword</span>(<span class="hl-v">$pw</span>, <span class="hl-v">$user</span>[<span class="hl-s">'password'</span>])) {
    <span class="hl-f">auth</span>()-&gt;<span class="hl-f">login</span>(<span class="hl-v">$user</span>);
}</code></pre>

    <h6 class="mb-2">JWT API 인증</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 토큰 발행</span>
<span class="hl-v">$token</span> = <span class="hl-f">auth</span>()-&gt;<span class="hl-f">createToken</span>([<span class="hl-s">'user_id'</span> =&gt; <span class="hl-n">1</span>, <span class="hl-s">'role'</span> =&gt; <span class="hl-s">'admin'</span>]);

<span class="hl-c">// API 요청에서 토큰 검증</span>
<span class="hl-v">$token</span> = <span class="hl-f">auth</span>()-&gt;<span class="hl-f">bearer</span>();
<span class="hl-v">$payload</span> = <span class="hl-f">auth</span>()-&gt;<span class="hl-f">verifyToken</span>(<span class="hl-v">$token</span>);
<span class="hl-k">if</span> (!<span class="hl-v">$payload</span>) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'인증 실패'</span>, <span class="hl-n">401</span>);
}</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>보안:</strong> JWT 서명은 <code>hash_equals()</code>로 비교하여 타이밍 공격을 방지합니다. <code>#[\SensitiveParameter]</code>로 비밀번호가 스택 트레이스에 노출되지 않습니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/encrypt" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Encrypt</a>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
        <a data-spa="/tool/api" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Api</a>
    </div>
</div>
