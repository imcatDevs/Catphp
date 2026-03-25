<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--primary);">receipt_long</i>
        <div><h4 class="mb-0">Log</h4><span class="text-muted caption">Cat\Log — 레벨별 로그 기록</span></div>
        <span class="badge badge--primary badge--sm ms-auto">logger()</span>
    </div>

    <p class="mb-2"><strong>일별 파일 기반</strong> 로깅 도구입니다. 4단계 로그 레벨(<code>DEBUG → INFO → WARN → ERROR</code>)을 지원하며, 각 로그 항목에 <strong>타임스탬프 + 레벨 + 메시지 + 컨텍스트 배열</strong>이 기록됩니다.</p>
    <p class="mb-3">로그 파일은 <code>storage/logs/YYYY-MM-DD.log</code> 형식으로 자동 생성됩니다. <code>tail()</code>은 <strong>fseek 역방향 읽기</strong>로 대용량 로그에서도 빠르게 최근 항목을 조회합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">설정 — config/app.php</h6></div>
        <pre class="demo-code" style="border-radius:0 0 8px 8px;"><code><span class="hl-s">'log'</span> =&gt; [
    <span class="hl-s">'path'</span>  =&gt; __DIR__ . <span class="hl-s">'/../storage/logs'</span>,  <span class="hl-c">// 로그 저장 경로</span>
    <span class="hl-s">'level'</span> =&gt; <span class="hl-s">'debug'</span>,                    <span class="hl-c">// 최소 로그 레벨</span>
]</code></pre>
    </div>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:250px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>debug(string $msg, array $ctx = [])</code></td><td><code>void</code></td><td>디버그 레벨 로그</td></tr>
                    <tr><td><code>info(string $msg, array $ctx = [])</code></td><td><code>void</code></td><td>정보 레벨 로그</td></tr>
                    <tr><td><code>warn(string $msg, array $ctx = [])</code></td><td><code>void</code></td><td>경고 레벨 로그</td></tr>
                    <tr><td><code>error(string $msg, array $ctx = [])</code></td><td><code>void</code></td><td>에러 레벨 로그</td></tr>
                    <tr><td><code>tail(int $lines = 20)</code></td><td><code>string</code></td><td>최근 N줄 역방향 읽기 (fseek)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 사용법</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 레벨별 로그 기록</span>
<span class="hl-f">logger</span>()-&gt;<span class="hl-f">debug</span>(<span class="hl-s">'쿼리 실행'</span>, [<span class="hl-s">'sql'</span> =&gt; <span class="hl-s">'SELECT * FROM users'</span>]);
<span class="hl-f">logger</span>()-&gt;<span class="hl-f">info</span>(<span class="hl-s">'사용자 로그인'</span>, [<span class="hl-s">'user_id'</span> =&gt; <span class="hl-n">1</span>, <span class="hl-s">'ip'</span> =&gt; <span class="hl-s">'127.0.0.1'</span>]);
<span class="hl-f">logger</span>()-&gt;<span class="hl-f">warn</span>(<span class="hl-s">'Rate limit 초과'</span>, [<span class="hl-s">'ip'</span> =&gt; <span class="hl-v">$ip</span>]);
<span class="hl-f">logger</span>()-&gt;<span class="hl-f">error</span>(<span class="hl-s">'결제 실패'</span>, [<span class="hl-s">'code'</span> =&gt; <span class="hl-s">'PAY_001'</span>, <span class="hl-s">'amount'</span> =&gt; <span class="hl-n">50000</span>]);</code></pre>

    <h6 class="mb-2">로그 조회 + 실전 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 최근 10줄 조회 (대시보드/디버깅용)</span>
<span class="hl-v">$recent</span> = <span class="hl-f">logger</span>()-&gt;<span class="hl-f">tail</span>(<span class="hl-n">10</span>);

<span class="hl-c">// 에러 핸들러에서 자동 로깅</span>
<span class="hl-k">set_exception_handler</span>(<span class="hl-k">function</span>(<span class="hl-k">Throwable</span> <span class="hl-v">$e</span>) {
    <span class="hl-f">logger</span>()-&gt;<span class="hl-f">error</span>(<span class="hl-v">$e</span>-&gt;<span class="hl-f">getMessage</span>(), [
        <span class="hl-s">'file'</span>  =&gt; <span class="hl-v">$e</span>-&gt;<span class="hl-f">getFile</span>(),
        <span class="hl-s">'line'</span>  =&gt; <span class="hl-v">$e</span>-&gt;<span class="hl-f">getLine</span>(),
        <span class="hl-s">'trace'</span> =&gt; <span class="hl-v">$e</span>-&gt;<span class="hl-f">getTraceAsString</span>(),
    ]);
});</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>로그 형식:</strong> <code>[2024-01-15 14:30:00] INFO: 사용자 로그인 {"user_id":1,"ip":"127.0.0.1"}</code> — 컨텍스트는 JSON으로 기록됩니다.</span>
    </div>
    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>팁:</strong> 운영 환경에서는 <code>level</code>을 <code>'info'</code> 이상으로 설정하여 debug 로그를 비활성화하세요. <code>storage/logs</code> 디렉토리 쓰기 권한이 필요합니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/firewall" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Firewall</a>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
    </div>
</div>
