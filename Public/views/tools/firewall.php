<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--danger);">local_fire_department</i>
        <div><h4 class="mb-0">Firewall</h4><span class="text-muted caption">Cat\Firewall — IP 차단/허용</span></div>
        <span class="badge badge--danger badge--sm ms-auto">firewall()</span>
    </div>

    <p class="mb-2">파일 기반 <strong>IP 블랙리스트/화이트리스트</strong> 방화벽입니다. 개별 IP와 <strong>CIDR 범위</strong>(IPv4 + IPv6)를 모두 지원하며, <code>inet_pton()</code> 기반으로 정확한 범위 매칭을 수행합니다.</p>
    <p class="mb-3"><code>flock(LOCK_SH/LOCK_EX)</code>로 동시 접근 시 데이터 무결성을 보장합니다. Rate 도구나 Guard 도구와 연동하여 자동 IP 차단이 가능합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">설정 — config/app.php</h6></div>
        <pre class="demo-code" style="border-radius:0 0 8px 8px;"><code><span class="hl-s">'firewall'</span> =&gt; [
    <span class="hl-s">'path'</span> =&gt; __DIR__ . <span class="hl-s">'/../storage/firewall'</span>,  <span class="hl-c">// 블랙리스트 파일 경로</span>
]</code></pre>
    </div>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:250px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>ban(string $ip)</code></td><td><code>self</code></td><td>IP 차단 (블랙리스트 추가)</td></tr>
                    <tr><td><code>deny(string $ip)</code></td><td><code>self</code></td><td><code>ban()</code> 별칭</td></tr>
                    <tr><td><code>unban(string $ip)</code></td><td><code>self</code></td><td>IP 차단 해제</td></tr>
                    <tr><td><code>isDenied(string $ip)</code></td><td><code>bool</code></td><td>차단 여부 확인</td></tr>
                    <tr><td><code>allow(string $ipOrCidr)</code></td><td><code>self</code></td><td>화이트리스트에 추가 (CIDR 지원)</td></tr>
                    <tr><td><code>isAllowed(string $ip)</code></td><td><code>bool</code></td><td>허용 여부 확인 (화이트리스트 우선)</td></tr>
                    <tr><td><code>bannedList()</code></td><td><code>array</code></td><td>차단 IP 목록 반환</td></tr>
                    <tr><td><code>middleware()</code></td><td><code>callable</code></td><td>자동 IP 검사 미들웨어 (차단 시 403)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 사용법</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// IP 차단</span>
<span class="hl-f">firewall</span>()-&gt;<span class="hl-f">ban</span>(<span class="hl-s">'203.0.113.50'</span>);

<span class="hl-c">// CIDR 범위 차단 (영구)</span>
<span class="hl-f">firewall</span>()-&gt;<span class="hl-f">ban</span>(<span class="hl-s">'10.0.0.0/8'</span>);

<span class="hl-c">// 차단 해제</span>
<span class="hl-f">firewall</span>()-&gt;<span class="hl-f">unban</span>(<span class="hl-s">'203.0.113.50'</span>);

<span class="hl-c">// 미들웨어로 자동 차단</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">firewall</span>()-&gt;<span class="hl-f">middleware</span>());</code></pre>

    <h6 class="mb-2">Rate 연동 — 자동 차단</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// Rate limit 초과 시 자동 IP 차단</span>
<span class="hl-c">// limit()은 카운터 증가 + 확인, check()는 조회만 (카운터 미증가)</span>
<span class="hl-v">$clientIp</span> = <span class="hl-f">ip</span>()-&gt;<span class="hl-f">address</span>();
<span class="hl-k">if</span> (!<span class="hl-f">rate</span>()-&gt;<span class="hl-f">limit</span>(<span class="hl-s">'api'</span>, <span class="hl-n">60</span>, <span class="hl-n">100</span>)) {
    <span class="hl-f">firewall</span>()-&gt;<span class="hl-f">ban</span>(<span class="hl-v">$clientIp</span>);
    <span class="hl-f">logger</span>()-&gt;<span class="hl-f">warn</span>(<span class="hl-s">'Rate limit 초과 — IP 차단'</span>, [<span class="hl-s">'ip'</span> =&gt; <span class="hl-v">$clientIp</span>]);
}

<span class="hl-c">// 자동 연동: Guard(auto_ban), User(attempt), Router(404)가</span>
<span class="hl-c">// 조건 충족 시 Firewall 자동 밴을 내장합니다.</span>

<span class="hl-c">// 차단 목록 조회 (관리자 대시보드)</span>
<span class="hl-v">$banned</span> = <span class="hl-f">firewall</span>()-&gt;<span class="hl-f">bannedList</span>();</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>CIDR 지원:</strong> <code>192.168.1.0/24</code> (IPv4), <code>2001:db8::/32</code> (IPv6) 형식 모두 <code>inet_pton()</code> 기반으로 정확한 범위 매칭을 수행합니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/rate" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Rate</a>
        <a data-spa="/tool/ip" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Ip</a>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
    </div>
</div>
