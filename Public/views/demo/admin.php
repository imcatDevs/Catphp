<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <h3 class="mb-1">관리 / 연동</h3>
    <p class="text-muted mb-4">Sitemap · Backup · DbView · Webhook · Swoole — 사이트맵, DB 백업, DB 탐색기, 웹훅, 비동기 서버</p>

    <!-- Sitemap -->
    <div class="card card--outlined mb-4" id="demo-sitemap">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">map</i> Sitemap</h5>
            <span class="badge badge--success badge--sm">Cat\Sitemap</span>
        </div>
        <div class="card__body">
            <p class="mb-3">XML 사이트맵 생성. 개별 URL, DB 쿼리 자동 변환, 인덱스 분할을 지원합니다.</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// DB 결과로 사이트맵 생성 + 파일 저장</span>
<span class="hl-v">$posts</span> = <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)
    -&gt;<span class="hl-f">where</span>(<span class="hl-s">'status'</span>, <span class="hl-s">'published'</span>)-&gt;<span class="hl-f">all</span>();

<span class="hl-f">sitemap</span>()-&gt;<span class="hl-f">url</span>(<span class="hl-s">'/'</span>, <span class="hl-k">null</span>, <span class="hl-s">'daily'</span>, <span class="hl-n">1.0</span>)
    -&gt;<span class="hl-f">fromQuery</span>(<span class="hl-v">$posts</span>, <span class="hl-s">'/post/{slug}'</span>, <span class="hl-s">'updated_at'</span>)
    -&gt;<span class="hl-f">save</span>(<span class="hl-s">'Public/sitemap.xml'</span>);

<span class="hl-c">// 인덱스 파일</span>
<span class="hl-f">sitemap</span>()-&gt;<span class="hl-f">index</span>([
    <span class="hl-s">'/sitemap-posts.xml'</span>,
    <span class="hl-s">'/sitemap-pages.xml'</span>,
])-&gt;<span class="hl-f">output</span>();  <span class="hl-c">// XML 직접 출력</span></code></pre>

            <h6 class="mb-2">검증 규칙</h6>
            <table class="table table--sm table--bordered mb-0">
                <thead><tr><th>항목</th><th>규칙</th></tr></thead>
                <tbody>
                    <tr><td>changefreq</td><td><code>always|hourly|daily|weekly|monthly|yearly|never</code> — 위반 시 <code>InvalidArgumentException</code></td></tr>
                    <tr><td>priority</td><td><code>0.0 ~ 1.0</code> 자동 클램핑</td></tr>
                    <tr><td>URL 수</td><td>파일당 최대 50,000개 (스펙). 초과 시 <code>RuntimeException</code></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Backup -->
    <div class="card card--outlined mb-4" id="demo-backup">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">backup</i> Backup</h5>
            <span class="badge badge--warning badge--sm">Cat\Backup</span>
        </div>
        <div class="card__body">
            <p class="mb-3">MySQL/PostgreSQL/SQLite DB 백업 및 복원. gzip 압축, 자동 정리를 지원합니다.</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 백업 + 정리</span>
<span class="hl-v">$path</span> = <span class="hl-f">backup</span>()-&gt;<span class="hl-f">database</span>();
<span class="hl-f">backup</span>()-&gt;<span class="hl-f">clean</span>(<span class="hl-n">30</span>);  <span class="hl-c">// 30일 이전 삭제</span>

<span class="hl-c">// 스케줄러 연동 — 매일 자동 백업</span>
<span class="hl-f">schedule</span>()-&gt;<span class="hl-f">daily</span>(<span class="hl-k">function</span>() {
    <span class="hl-f">backup</span>()-&gt;<span class="hl-f">database</span>();
    <span class="hl-f">backup</span>()-&gt;<span class="hl-f">clean</span>(<span class="hl-n">30</span>);
});

<span class="hl-c">// CLI</span>
<span class="hl-c">// php cli.php db:backup</span>
<span class="hl-c">// php cli.php db:restore --path=storage/backup/20240101_120000_mysql.sql</span>
<span class="hl-c">// php cli.php db:backup:list</span>
<span class="hl-c">// php cli.php db:backup:clean --days=30</span></code></pre>

            <div class="alert alert--warning">
                <span class="alert__message"><strong>Windows:</strong> <code>mysqldump</code>/<code>pg_dump</code>가 시스템 PATH에 등록되어 있어야 합니다.</span>
            </div>
        </div>
    </div>

    <!-- DbView -->
    <div class="card card--outlined mb-4" id="demo-dbview">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">table_view</i> DbView</h5>
            <span class="badge badge--info badge--sm">Cat\DbView</span>
        </div>
        <div class="card__body">
            <p class="mb-3">DB 구조 조회 및 탐색. 테이블/컬럼/인덱스 정보, 데이터 미리보기, 전체 통계.</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 테이블 목록 + 상세 정보</span>
<span class="hl-v">$tables</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">tables</span>();
<span class="hl-v">$info</span>   = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">describe</span>(<span class="hl-s">'users'</span>);
<span class="hl-c">// → columns, indexes, row_count, size</span>

<span class="hl-c">// 데이터 미리보기 (최대 100행)</span>
<span class="hl-v">$rows</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">preview</span>(<span class="hl-s">'users'</span>, <span class="hl-n">5</span>);

<span class="hl-c">// DB 전체 통계</span>
<span class="hl-v">$stats</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">stats</span>();
<span class="hl-c">// → driver, database, tables, total_rows, total_size</span>

<span class="hl-c">// 관리자 API</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/admin/api/table/{name}'</span>, <span class="hl-k">function</span>(<span class="hl-v">$name</span>) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">dbview</span>()-&gt;<span class="hl-f">describe</span>(<span class="hl-v">$name</span>));
});</code></pre>

            <h6 class="mb-2">CLI 명령어</h6>
            <table class="table table--sm table--bordered mb-0">
                <thead><tr><th>명령어</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>php cli.php db:tables</code></td><td>테이블 목록</td></tr>
                    <tr><td><code>php cli.php db:describe --table=users</code></td><td>상세 (컬럼+인덱스+크기)</td></tr>
                    <tr><td><code>php cli.php db:preview --table=users --limit=5</code></td><td>데이터 미리보기</td></tr>
                    <tr><td><code>php cli.php db:stats</code></td><td>DB 전체 통계</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Webhook -->
    <div class="card card--outlined mb-4" id="demo-webhook">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">webhook</i> Webhook</h5>
            <span class="badge badge--danger badge--sm">Cat\Webhook</span>
        </div>
        <div class="card__body">
            <p class="mb-3">Webhook 발송/수신 + HMAC-SHA256 서명 검증. 재시도, 로깅, SSRF 방어 내장.</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 발송 — HMAC 서명 자동 첨부</span>
<span class="hl-v">$result</span> = <span class="hl-f">webhook</span>()-&gt;<span class="hl-f">to</span>(<span class="hl-s">'https://example.com/hook'</span>)
    -&gt;<span class="hl-f">secret</span>(<span class="hl-s">'my-secret'</span>)
    -&gt;<span class="hl-f">payload</span>([<span class="hl-s">'event'</span> =&gt; <span class="hl-s">'order.paid'</span>, <span class="hl-s">'id'</span> =&gt; <span class="hl-n">123</span>])
    -&gt;<span class="hl-f">send</span>();

<span class="hl-k">if</span> (<span class="hl-v">$result</span>-&gt;<span class="hl-f">ok</span>()) {
    <span class="hl-c">// status: 200, body: '...', attempts: 1</span>
}

<span class="hl-c">// 수신 검증</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">post</span>(<span class="hl-s">'/api/webhook'</span>, <span class="hl-k">function</span>() {
    <span class="hl-v">$wh</span> = <span class="hl-f">webhook</span>()-&gt;<span class="hl-f">receive</span>();
    <span class="hl-k">if</span> (!<span class="hl-v">$wh</span>-&gt;<span class="hl-f">isValid</span>()) {
        <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'서명 검증 실패'</span>, [], <span class="hl-n">401</span>);
    }
    <span class="hl-v">$data</span> = <span class="hl-v">$wh</span>-&gt;<span class="hl-f">getPayload</span>();
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>([<span class="hl-s">'received'</span> =&gt; <span class="hl-k">true</span>]);
});</code></pre>

            <h6 class="mb-2">보안 기능</h6>
            <table class="table table--sm table--bordered mb-0">
                <thead><tr><th>기능</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td>URL 스키마 검증</td><td><code>http</code>/<code>https</code>만 허용 (SSRF 차단)</td></tr>
                    <tr><td>HMAC-SHA256</td><td><code>hash_equals()</code>로 타이밍 공격 방어</td></tr>
                    <tr><td>CRLF 방어</td><td>커스텀 헤더에서 <code>\r\n\0</code> 제거</td></tr>
                    <tr><td>리다이렉트 차단</td><td><code>CURLOPT_FOLLOWLOCATION = false</code></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Swoole -->
    <div class="card card--outlined mb-4" id="demo-swoole">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">bolt</i> Swoole</h5>
            <span class="badge badge--primary badge--sm">Cat\Swoole</span>
        </div>
        <div class="card__body">
            <p class="mb-3">고성능 비동기 HTTP/WebSocket 서버. CatPHP Router 자동 통합, 코루틴, 연결 풀, 태스크 워커를 지원합니다.</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// HTTP 서버 — 한 줄 시작</span>
<span class="hl-f">swoole</span>()-&gt;<span class="hl-f">http</span>()-&gt;<span class="hl-f">onBoot</span>(<span class="hl-k">function</span>() {
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-s">'Hello Swoole!'</span>);
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/api/users'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">get</span>()));
})-&gt;<span class="hl-f">start</span>();

<span class="hl-c">// WebSocket 채팅 — 룸 기반</span>
<span class="hl-f">swoole</span>()-&gt;<span class="hl-f">websocket</span>()
    -&gt;<span class="hl-f">onWsOpen</span>(<span class="hl-k">fn</span>(<span class="hl-v">$fd</span>) =&gt; <span class="hl-f">swoole</span>()-&gt;<span class="hl-f">join</span>(<span class="hl-v">$fd</span>, <span class="hl-s">'lobby'</span>))
    -&gt;<span class="hl-f">onWsMessage</span>(<span class="hl-k">fn</span>(<span class="hl-v">$fd</span>, <span class="hl-v">$data</span>) =&gt;
        <span class="hl-f">swoole</span>()-&gt;<span class="hl-f">toRoom</span>(<span class="hl-s">'lobby'</span>, <span class="hl-v">$data</span>, <span class="hl-v">$fd</span>))
    -&gt;<span class="hl-f">onWsClose</span>(<span class="hl-k">fn</span>(<span class="hl-v">$fd</span>) =&gt; <span class="hl-f">swoole</span>()-&gt;<span class="hl-f">leaveAll</span>(<span class="hl-v">$fd</span>))
    -&gt;<span class="hl-f">start</span>();

<span class="hl-c">// 코루틴 병렬 실행</span>
<span class="hl-v">$results</span> = <span class="hl-f">swoole</span>()-&gt;<span class="hl-f">parallel</span>([
    <span class="hl-s">'users'</span>  =&gt; <span class="hl-k">fn</span>() =&gt; <span class="hl-f">http</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'https://api.a.com/users'</span>),
    <span class="hl-s">'orders'</span> =&gt; <span class="hl-k">fn</span>() =&gt; <span class="hl-f">http</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'https://api.b.com/orders'</span>),
]);</code></pre>

            <h6 class="mb-2">4대 원칙</h6>
            <table class="table table--sm table--bordered mb-0">
                <thead><tr><th>원칙</th><th>구현</th></tr></thead>
                <tbody>
                    <tr><td>⚡ 빠른 속도</td><td>상주 프로세스 (require 1회), 연결 풀 재사용, 코루틴 비블로킹 I/O</td></tr>
                    <tr><td>🔧 사용 편리</td><td><code>swoole()->http()->start()</code> 한 줄, 체이닝 API</td></tr>
                    <tr><td>📖 쉬운 학습</td><td>http / websocket / task / co — 4가지 핵심 개념</td></tr>
                    <tr><td>🔒 보안</td><td>요청 격리 (슈퍼글로벌+input 캐시 초기화), Graceful Shutdown</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 통합 예제 -->
    <div class="card card--outlined mb-4" id="demo-admin-combo">
        <div class="card__header">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">integration_instructions</i> 통합 예제</h5>
        </div>
        <div class="card__body">
            <pre class="demo-code mb-3"><code><span class="hl-c">// Swoole 서버 + 태스크 워커로 백업·사이트맵·Webhook 자동화</span>
<span class="hl-f">swoole</span>()-&gt;<span class="hl-f">http</span>()
    -&gt;<span class="hl-f">handle</span>(<span class="hl-s">'daily-maintenance'</span>, <span class="hl-k">function</span>(<span class="hl-t">array</span> <span class="hl-v">$p</span>) {
        <span class="hl-c">// 태스크 워커에서 비동기 실행</span>
        <span class="hl-v">$path</span> = <span class="hl-f">backup</span>()-&gt;<span class="hl-f">database</span>();
        <span class="hl-f">backup</span>()-&gt;<span class="hl-f">clean</span>(<span class="hl-n">30</span>);

        <span class="hl-v">$posts</span> = <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'posts'</span>)
            -&gt;<span class="hl-f">where</span>(<span class="hl-s">'status'</span>, <span class="hl-s">'published'</span>)-&gt;<span class="hl-f">all</span>();
        <span class="hl-f">sitemap</span>()-&gt;<span class="hl-f">url</span>(<span class="hl-s">'/'</span>, <span class="hl-k">null</span>, <span class="hl-s">'daily'</span>, <span class="hl-n">1.0</span>)
            -&gt;<span class="hl-f">fromQuery</span>(<span class="hl-v">$posts</span>, <span class="hl-s">'/post/{slug}'</span>)
            -&gt;<span class="hl-f">save</span>(<span class="hl-s">'Public/sitemap.xml'</span>);

        <span class="hl-v">$stats</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">stats</span>();
        <span class="hl-f">webhook</span>()-&gt;<span class="hl-f">to</span>(<span class="hl-f">env</span>(<span class="hl-s">'SLACK_WEBHOOK_URL'</span>))
            -&gt;<span class="hl-f">payload</span>([<span class="hl-s">'text'</span> =&gt; <span class="hl-s">"백업 완료 ({$stats['total_size']})"</span>])
            -&gt;<span class="hl-f">send</span>();
    })
    -&gt;<span class="hl-f">onBoot</span>(<span class="hl-k">function</span>() {
        <span class="hl-c">// 매일 03:00에 태스크 자동 실행 (86400초 타이머)</span>
        <span class="hl-f">swoole</span>()-&gt;<span class="hl-f">tick</span>(<span class="hl-n">86400000</span>, <span class="hl-k">fn</span>() =&gt;
            <span class="hl-f">swoole</span>()-&gt;<span class="hl-f">task</span>(<span class="hl-s">'daily-maintenance'</span>));

        <span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-s">'Running on Swoole!'</span>);
    })
    -&gt;<span class="hl-f">start</span>();</code></pre>

            <pre class="demo-code mb-0"><code><span class="hl-c"># CLI 관리</span>
php cli.php swoole:start              <span class="hl-c"># 서버 시작</span>
php cli.php swoole:start --type=websocket  <span class="hl-c"># WebSocket 모드</span>
php cli.php swoole:status              <span class="hl-c"># 실행 상태</span>
php cli.php swoole:reload              <span class="hl-c"># 워커 리로드 (코드 반영)</span>
php cli.php swoole:stop                <span class="hl-c"># Graceful Shutdown</span></code></pre>
        </div>
    </div>
</div>
