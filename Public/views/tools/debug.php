<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--danger);">bug_report</i>
        <div><h4 class="mb-0">Debug</h4><span class="text-muted caption">Cat\Debug — 디버깅 도구</span></div>
        <span class="badge badge--danger badge--sm ms-auto">debug() / dd() / dump()</span>
    </div>

    <p class="mb-2"><strong>개발 디버깅</strong> 도구입니다. 변수 덤프, 타이머, 메모리 측정, 콜 스택 추적, 디버그 바를 제공합니다.</p>
    <p class="mb-3"><code>dd()</code>는 변수를 출력하고 즉시 종료합니다. <code>bar()</code>는 페이지 하단에 실행 정보를 표시합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>dump(mixed ...$vars)</code></td><td><code>self</code></td><td>변수 출력 (계속 실행)</td></tr>
                    <tr><td><code>dd(mixed ...$vars)</code></td><td><code>never</code></td><td>변수 출력 후 종료</td></tr>
                    <tr><td><code>timer(string $label)</code></td><td><code>self</code></td><td>타이머 시작</td></tr>
                    <tr><td><code>timerEnd(string $label)</code></td><td><code>float</code></td><td>타이머 종료 (ms)</td></tr>
                    <tr><td><code>measure(string $label, callable $cb)</code></td><td><code>mixed</code></td><td>콜백 실행 시간 측정</td></tr>
                    <tr><td><code>elapsed()</code></td><td><code>float</code></td><td>앱 시작부터 경과 (ms)</td></tr>
                    <tr><td><code>memory / peakMemory</code></td><td><code>string</code></td><td>메모리 사용량</td></tr>
                    <tr><td><code>trace(int $limit)</code></td><td><code>self</code></td><td>호출 스택 출력</td></tr>
                    <tr><td><code>log(string $type, string $msg)</code></td><td><code>self</code></td><td>디버그 로그 기록</td></tr>
                    <tr><td><code>bar()</code></td><td><code>string</code></td><td>HTML 디버그 바</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 빠른 디버깅</span>
<span class="hl-f">dd</span>(<span class="hl-v">$user</span>, <span class="hl-v">$request</span>);  <span class="hl-c">// 출력 후 종료</span>
<span class="hl-f">dump</span>(<span class="hl-v">$data</span>);           <span class="hl-c">// 출력, 계속 실행</span>

<span class="hl-c">// 실행 시간 측정</span>
<span class="hl-v">$result</span> = <span class="hl-f">debug</span>()-&gt;<span class="hl-f">measure</span>(<span class="hl-s">'heavy-query'</span>, <span class="hl-k">fn</span>() =&gt;
    <span class="hl-f">db</span>()-&gt;<span class="hl-f">query</span>(<span class="hl-s">'SELECT * FROM logs'</span>)
);

<span class="hl-c">// 디버그 바 (페이지 하단)</span>
<span class="hl-k">echo</span> <span class="hl-f">debug</span>()-&gt;<span class="hl-f">bar</span>();  <span class="hl-c">// 실행시간, 메모리, 쿼리 수 등</span></code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>프로덕션:</strong> <code>config/app.php</code>의 <code>app.debug</code>가 <code>false</code>이면 <code>bar()</code>는 빈 문자열을 반환합니다. 민감한 정보 노출을 방지합니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/log" class="badge badge--soft badge--success badge--sm" style="cursor:pointer;">Log</a>
    </div>
</div>
