<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--warning);">badge</i>
        <div><h4 class="mb-0">Session</h4><span class="text-muted caption">Cat\Session — 세션 관리</span></div>
        <span class="badge badge--warning badge--sm ms-auto">session()</span>
    </div>

    <p class="mb-2"><strong>세션 관리</strong> 도구입니다. get/set/flash/regenerate를 제공하며, 세션 고정 공격 방지를 위해 <code>regenerate()</code>를 내장합니다.</p>
    <p class="mb-3">Flash 데이터는 다음 요청까지만 유지되며, <code>reflash()</code>/<code>keep()</code>로 수명을 연장할 수 있습니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">주요 메서드</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>get(string $key, mixed $default)</code></td><td><code>mixed</code></td><td>세션 값 읽기</td></tr>
                    <tr><td><code>set(string $key, mixed $value)</code></td><td><code>self</code></td><td>세션 값 설정</td></tr>
                    <tr><td><code>has(string $key)</code></td><td><code>bool</code></td><td>존재 확인</td></tr>
                    <tr><td><code>forget(string $key)</code></td><td><code>self</code></td><td>키 삭제</td></tr>
                    <tr><td><code>pull(string $key)</code></td><td><code>mixed</code></td><td>가져온 뒤 삭제</td></tr>
                    <tr><td><code>flash(string $key, mixed $value)</code></td><td><code>self</code></td><td>flash 데이터 설정</td></tr>
                    <tr><td><code>getFlash / hasFlash</code></td><td>—</td><td>flash 데이터 읽기/확인</td></tr>
                    <tr><td><code>reflash() / keep(array $keys)</code></td><td><code>self</code></td><td>flash 수명 연장</td></tr>
                    <tr><td><code>regenerate(bool $delete)</code></td><td><code>self</code></td><td>세션 ID 재생성</td></tr>
                    <tr><td><code>destroy()</code></td><td><code>void</code></td><td>세션 파괴</td></tr>
                    <tr><td><code>remember(string $key, callable $cb)</code></td><td><code>mixed</code></td><td>없으면 설정 후 반환</td></tr>
                    <tr><td><code>increment / decrement</code></td><td><code>int</code></td><td>값 증감</td></tr>
                    <tr><td><code>token()</code></td><td><code>string</code></td><td>CSRF 토큰</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 기본 세션</span>
<span class="hl-f">session</span>()-&gt;<span class="hl-f">set</span>(<span class="hl-s">'cart'</span>, [<span class="hl-s">'item1'</span>, <span class="hl-s">'item2'</span>]);
<span class="hl-v">$cart</span> = <span class="hl-f">session</span>(<span class="hl-s">'cart'</span>, []);

<span class="hl-c">// Flash 메시지 (PRG 패턴)</span>
<span class="hl-f">session</span>()-&gt;<span class="hl-f">flash</span>(<span class="hl-s">'success'</span>, <span class="hl-s">'저장 완료'</span>);

<span class="hl-c">// 로그인 후 세션 고정 공격 방지</span>
<span class="hl-f">session</span>()-&gt;<span class="hl-f">regenerate</span>();

<span class="hl-c">// 조회수 카운터</span>
<span class="hl-f">session</span>()-&gt;<span class="hl-f">increment</span>(<span class="hl-s">'page_views'</span>);</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> 로그인 성공 후 반드시 <code>regenerate()</code>를 호출하여 세션 고정(Session Fixation) 공격을 방지하세요.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/auth" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Auth</a>
        <a data-spa="/tool/flash" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Flash</a>
        <a data-spa="/tool/csrf" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Csrf</a>
    </div>
</div>
