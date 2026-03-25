<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--danger);">security</i>
        <div><h4 class="mb-0">Guard</h4><span class="text-muted caption">Cat\Guard — 입력 보호</span></div>
        <span class="badge badge--danger badge--sm ms-auto">guard()</span>
    </div>

    <p class="mb-2">입력 데이터에 대한 <strong>종합 보안 도구</strong>입니다. XSS, SQL Injection, 경로 순회(Path Traversal) 등 일반적인 웹 공격을 탐지하고 차단합니다.</p>
    <p class="mb-3"><code>middleware()</code>로 모든 요청에 자동 적용할 수 있으며, 공격 탐지 시 커스텀 핸들러(<code>onAttack</code>)를 호출합니다. XSS 이벤트 핸들러 정규식으로 <code>onclick</code>, <code>onerror</code> 등 인라인 이벤트도 차단합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:260px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>path(string $p)</code></td><td><code>string</code></td><td>경로 순회 방지 (<code>..</code>, null byte 제거)</td></tr>
                    <tr><td><code>xss(string $s)</code></td><td><code>string</code></td><td>XSS 공격 패턴 제거 (스크립트, 이벤트 핸들러)</td></tr>
                    <tr><td><code>sql(string $s)</code></td><td><code>string</code></td><td>SQL 키워드 필터링 (보조 방어)</td></tr>
                    <tr><td><code>clean(string $s)</code></td><td><code>string</code></td><td>XSS + SQL 통합 정화</td></tr>
                    <tr><td><code>cleanArray(array $a)</code></td><td><code>array</code></td><td>배열 전체 정화 (재귀)</td></tr>
                    <tr><td><code>header(string $h)</code></td><td><code>string</code></td><td>HTTP 헤더 인젝션 방지</td></tr>
                    <tr><td><code>filename(string $f)</code></td><td><code>string</code></td><td>파일명 정화 (위험 문자 제거)</td></tr>
                    <tr><td><code>contentType(array $allowed)</code></td><td><code>bool</code></td><td>Content-Type 검증 (기본: json, form, multipart)</td></tr>
                    <tr><td><code>maxBodySize(?string $size)</code></td><td><code>bool</code></td><td>요청 본문 크기 제한 확인 (예: <code>'2M'</code>)</td></tr>
                    <tr><td><code>all()</code></td><td><code>array</code></td><td>전체 입력 살균 후 배열 반환</td></tr>
                    <tr><td><code>onAttack(callable $fn)</code></td><td><code>self</code></td><td>공격 탐지 시 콜백 등록</td></tr>
                    <tr><td><code>middleware()</code></td><td><code>callable</code></td><td>자동 보호 미들웨어</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 사용법</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 개별 정화</span>
<span class="hl-v">$name</span> = <span class="hl-f">guard</span>()-&gt;<span class="hl-f">xss</span>(<span class="hl-v">$_POST</span>[<span class="hl-s">'name'</span>]);
<span class="hl-v">$path</span> = <span class="hl-f">guard</span>()-&gt;<span class="hl-f">path</span>(<span class="hl-v">$_GET</span>[<span class="hl-s">'file'</span>]);
<span class="hl-v">$file</span> = <span class="hl-f">guard</span>()-&gt;<span class="hl-f">filename</span>(<span class="hl-v">$uploadName</span>);

<span class="hl-c">// 전체 입력 정화</span>
<span class="hl-f">guard</span>()-&gt;<span class="hl-f">all</span>();</code></pre>

    <h6 class="mb-2">미들웨어 + 공격 로깅</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 공격 탐지 시 로깅 + IP 차단</span>
<span class="hl-f">guard</span>()-&gt;<span class="hl-f">onAttack</span>(<span class="hl-k">function</span>(<span class="hl-k">string</span> <span class="hl-v">$type</span>, <span class="hl-k">string</span> <span class="hl-v">$ip</span>) {
    <span class="hl-f">logger</span>()-&gt;<span class="hl-f">warn</span>(<span class="hl-s">'공격 탐지'</span>, [<span class="hl-s">'type'</span> =&gt; <span class="hl-v">$type</span>, <span class="hl-s">'ip'</span> =&gt; <span class="hl-v">$ip</span>]);
    <span class="hl-f">firewall</span>()-&gt;<span class="hl-f">ban</span>(<span class="hl-v">$ip</span>);
});

<span class="hl-c">// 글로벌 미들웨어 등록</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">guard</span>()-&gt;<span class="hl-f">middleware</span>());</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>방어 범위:</strong> XSS (<code>&lt;script&gt;</code>, 인라인 이벤트), SQL Injection (보조), Path Traversal (<code>../</code>, null byte), HTTP Header Injection (<code>\r\n</code>), 파일명 공격, 본문 크기 초과</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/firewall" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Firewall</a>
        <a data-spa="/tool/csrf" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Csrf</a>
        <a data-spa="/tool/upload" class="badge badge--soft badge--success badge--sm" style="cursor:pointer;">Upload</a>
    </div>
</div>
