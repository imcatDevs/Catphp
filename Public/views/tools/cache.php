<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--primary);">cached</i>
        <div><h4 class="mb-0">Cache</h4><span class="text-muted caption">Cat\Cache — 파일 기반 캐시</span></div>
        <span class="badge badge--primary badge--sm ms-auto">cache()</span>
    </div>

    <p class="mb-2">파일 기반 키-값 캐시입니다. <strong>TTL(유효 기간)</strong> 지원, <code>LOCK_EX</code>로 동시 쓰기 충돌을 방지하며, <code>serialize/unserialize</code>로 배열·객체도 저장할 수 있습니다.</p>
    <p class="mb-3"><code>remember()</code> 패턴을 사용하면 캐시가 있으면 즉시 반환, 없으면 콜백을 실행하여 결과를 저장하는 단일 호출로 캐시 로직을 간결하게 작성할 수 있습니다. <code>unserialize</code>는 <code>allowed_classes: false</code>로 객체 인젝션을 차단합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">설정 — config/app.php</h6></div>
        <pre class="demo-code" style="border-radius:0 0 8px 8px;"><code><span class="hl-s">'cache'</span> =&gt; [
    <span class="hl-s">'path'</span> =&gt; __DIR__ . <span class="hl-s">'/../storage/cache'</span>,  <span class="hl-c">// 캐시 파일 저장 경로</span>
]</code></pre>
    </div>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:250px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>set(string $key, mixed $val, ?int $ttl = null)</code></td><td><code>bool</code></td><td>값 저장. TTL 초 단위 (null=기본)</td></tr>
                    <tr><td><code>get(string $key)</code></td><td><code>mixed</code></td><td>값 조회. 만료/없으면 <code>null</code></td></tr>
                    <tr><td><code>has(string $key)</code></td><td><code>bool</code></td><td>캐시 존재 + 유효 여부</td></tr>
                    <tr><td><code>del(string $key)</code></td><td><code>bool</code></td><td>단일 키 삭제</td></tr>
                    <tr><td><code>clear()</code></td><td><code>bool</code></td><td>전체 캐시 삭제</td></tr>
                    <tr><td><code>remember(string $key, callable $fn, ?int $ttl)</code></td><td><code>mixed</code></td><td>캐시 히트 → 반환, 미스 → 콜백 실행 후 저장</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 사용법</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 저장 + 조회</span>
<span class="hl-f">cache</span>()-&gt;<span class="hl-f">set</span>(<span class="hl-s">'user:1'</span>, [<span class="hl-s">'name'</span> =&gt; <span class="hl-s">'Cat'</span>], <span class="hl-n">3600</span>);
<span class="hl-v">$user</span> = <span class="hl-f">cache</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'user:1'</span>);

<span class="hl-c">// 존재 확인 + 삭제</span>
<span class="hl-k">if</span> (<span class="hl-f">cache</span>()-&gt;<span class="hl-f">has</span>(<span class="hl-s">'user:1'</span>)) {
    <span class="hl-f">cache</span>()-&gt;<span class="hl-f">del</span>(<span class="hl-s">'user:1'</span>);
}</code></pre>

    <h6 class="mb-2">remember 패턴 — 캐시 우선 로드</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// DB 쿼리 결과를 1시간 캐싱</span>
<span class="hl-v">$stats</span> = <span class="hl-f">cache</span>()-&gt;<span class="hl-f">remember</span>(<span class="hl-s">'site:stats'</span>, <span class="hl-k">fn</span>() =&gt; [
    <span class="hl-s">'posts'</span> =&gt; <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)-&gt;<span class="hl-f">count</span>(),
    <span class="hl-s">'users'</span> =&gt; <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">count</span>(),
], <span class="hl-n">3600</span>);

<span class="hl-c">// 데이터 변경 시 캐시 무효화</span>
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)-&gt;<span class="hl-f">insert</span>([...]);
<span class="hl-f">cache</span>()-&gt;<span class="hl-f">del</span>(<span class="hl-s">'site:stats'</span>);</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>보안:</strong> <code>unserialize()</code>는 <code>allowed_classes: false</code>로 실행되어, 캐시 파일에 악성 객체가 주입되더라도 PHP 객체 인젝션이 차단됩니다.</span>
    </div>
    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>팁:</strong> <code>storage/cache</code> 디렉토리의 쓰기 권한을 확인하세요. 디렉토리가 없으면 자동 생성을 시도하지만, 상위 디렉토리 권한이 필요합니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/search" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">Search</a>
    </div>
</div>
