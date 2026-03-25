<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--info);">table_view</i>
        <div><h4 class="mb-0">DbView</h4><span class="text-muted caption">Cat\DbView — DB 구조 조회/탐색기</span></div>
        <span class="badge badge--info badge--sm ms-auto">dbview()</span>
    </div>

    <p class="mb-2"><strong>MySQL</strong>, <strong>PostgreSQL</strong>, <strong>SQLite</strong> 호환 DB 구조 조회 도구입니다. 테이블 목록, 컬럼 정보, 인덱스, 데이터 미리보기, 통계를 제공합니다.</p>
    <p class="mb-3">모든 테이블/컬럼명은 <code>validateIdentifier()</code>로 SQL 인젝션을 차단합니다. <code>describe()</code>는 내부적으로 중복 검증 없이 최적화되어 있습니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:320px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>tables()</code></td><td><code>array</code></td><td>테이블 이름 목록</td></tr>
                    <tr><td><code>columns(string $table)</code></td><td><code>array</code></td><td>컬럼 정보 (이름, 타입, nullable, 기본값, 키)</td></tr>
                    <tr><td><code>describe(string $table)</code></td><td><code>array</code></td><td>상세 (컬럼 + 인덱스 + 행 수 + 크기)</td></tr>
                    <tr><td><code>preview(string $table, int $limit = 10)</code></td><td><code>array</code></td><td>데이터 미리보기 (최대 100행)</td></tr>
                    <tr><td><code>indexes(string $table)</code></td><td><code>array</code></td><td>인덱스 정보 (이름, 컬럼, 유니크)</td></tr>
                    <tr><td><code>rowCount(string $table)</code></td><td><code>int</code></td><td>테이블 행 수</td></tr>
                    <tr><td><code>size(string $table)</code></td><td><code>string</code></td><td>테이블 크기 (예: 1.5 MB)</td></tr>
                    <tr><td><code>stats()</code></td><td><code>array</code></td><td>DB 전체 통계 (드라이버, DB명, 테이블 수, 행, 크기)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">PHP 코드 사용</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 테이블 목록</span>
<span class="hl-v">$tables</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">tables</span>();
<span class="hl-c">// ['users', 'posts', 'comments', ...]</span>

<span class="hl-c">// 테이블 상세 정보</span>
<span class="hl-v">$info</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">describe</span>(<span class="hl-s">'users'</span>);
<span class="hl-c">// ['table' => 'users', 'columns' => [...], 'indexes' => [...],</span>
<span class="hl-c">//  'row_count' => 150, 'size' => '24.5 KB']</span>

<span class="hl-c">// 데이터 미리보기 (5행)</span>
<span class="hl-v">$rows</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">preview</span>(<span class="hl-s">'users'</span>, <span class="hl-n">5</span>);

<span class="hl-c">// DB 전체 통계</span>
<span class="hl-v">$stats</span> = <span class="hl-f">dbview</span>()-&gt;<span class="hl-f">stats</span>();
<span class="hl-c">// ['driver' => 'mysql', 'database' => 'mydb',</span>
<span class="hl-c">//  'tables' => 12, 'total_rows' => 5420, 'total_size' => '3.2 MB']</span></code></pre>

    <h6 class="mb-2">CLI 명령어</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c"># 테이블 목록</span>
php cli.php db:tables

<span class="hl-c"># 컬럼 정보</span>
php cli.php db:columns --table=users

<span class="hl-c"># 상세 (컬럼 + 인덱스 + 행 수 + 크기)</span>
php cli.php db:describe --table=users

<span class="hl-c"># 데이터 미리보기</span>
php cli.php db:preview --table=users --limit=5

<span class="hl-c"># DB 전체 통계</span>
php cli.php db:stats</code></pre>

    <h6 class="mb-2">API 활용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 관리자 API: DB 탐색기</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/admin/api/tables'</span>, <span class="hl-k">function</span>() {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">dbview</span>()-&gt;<span class="hl-f">tables</span>());
});

<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/admin/api/table/{name}'</span>, <span class="hl-k">function</span>(<span class="hl-v">$name</span>) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">dbview</span>()-&gt;<span class="hl-f">describe</span>(<span class="hl-v">$name</span>));
});</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>팁:</strong> <code>stats()</code>는 모든 테이블에 대해 rowCount + size를 호출하므로 테이블이 많은 DB에서는 느릴 수 있습니다. 관리자 대시보드에서 캐싱과 함께 사용하세요.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/backup" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Backup</a>
        <a data-spa="/tool/migration" class="badge badge--soft badge--secondary badge--sm" style="cursor:pointer;">Migration</a>
    </div>
</div>
