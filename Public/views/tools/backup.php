<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--warning);">backup</i>
        <div><h4 class="mb-0">Backup</h4><span class="text-muted caption">Cat\Backup — DB 백업/복원</span></div>
        <span class="badge badge--warning badge--sm ms-auto">backup()</span>
    </div>

    <p class="mb-2"><strong>MySQL</strong>, <strong>PostgreSQL</strong>, <strong>SQLite</strong> 데이터베이스를 백업하고 복원합니다. <code>mysqldump</code>/<code>pg_dump</code> 바이너리 기반이며, SQLite는 파일 복사 방식입니다.</p>
    <p class="mb-3"><code>compress</code> 옵션으로 gzip 압축을 지원하고, <code>clean()</code>으로 오래된 백업을 자동 정리합니다. 복원 시 경로 트래버설 방어가 내장되어 있습니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:300px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>database(?string $path = null)</code></td><td><code>string</code></td><td>DB 백업 실행, 파일 경로 반환</td></tr>
                    <tr><td><code>restore(string $path)</code></td><td><code>bool</code></td><td>백업 파일에서 복원</td></tr>
                    <tr><td><code>list()</code></td><td><code>array</code></td><td>백업 파일 목록 (최신순)</td></tr>
                    <tr><td><code>latest()</code></td><td><code>?string</code></td><td>최신 백업 파일 경로</td></tr>
                    <tr><td><code>clean(int $days = 0)</code></td><td><code>int</code></td><td>N일 이전 백업 삭제, 삭제 수 반환</td></tr>
                    <tr><td><code>getPath()</code></td><td><code>string</code></td><td>백업 디렉토리 경로</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">PHP 코드 사용</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 백업 실행 (자동 경로: storage/backup/20240101_120000_mysql.sql)</span>
<span class="hl-v">$path</span> = <span class="hl-f">backup</span>()-&gt;<span class="hl-f">database</span>();

<span class="hl-c">// 최신 백업으로 복원</span>
<span class="hl-v">$latest</span> = <span class="hl-f">backup</span>()-&gt;<span class="hl-f">latest</span>();
<span class="hl-k">if</span> (<span class="hl-v">$latest</span>) {
    <span class="hl-f">backup</span>()-&gt;<span class="hl-f">restore</span>(<span class="hl-v">$latest</span>);
}

<span class="hl-c">// 30일 이전 백업 자동 정리</span>
<span class="hl-v">$deleted</span> = <span class="hl-f">backup</span>()-&gt;<span class="hl-f">clean</span>(<span class="hl-n">30</span>);</code></pre>

    <h6 class="mb-2">CLI 명령어</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c"># 백업 실행</span>
php cli.php db:backup
php cli.php db:backup --path=custom/backup.sql

<span class="hl-c"># 복원</span>
php cli.php db:restore --path=storage/backup/20240101_120000_mysql.sql

<span class="hl-c"># 백업 목록 / 정리</span>
php cli.php db:backup:list
php cli.php db:backup:clean --days=30</code></pre>

    <h6 class="mb-2">설정</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// config/app.php</span>
<span class="hl-s">'backup'</span> =&gt; [
    <span class="hl-s">'path'</span>      =&gt; <span class="hl-s">'storage/backup'</span>,   <span class="hl-c">// 저장 디렉토리</span>
    <span class="hl-s">'keep_days'</span> =&gt; <span class="hl-n">30</span>,                  <span class="hl-c">// 자동 정리 보관 일수</span>
    <span class="hl-s">'compress'</span>  =&gt; <span class="hl-k">false</span>,               <span class="hl-c">// gzip 압축</span>
],</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>주의:</strong> MySQL/PostgreSQL 백업은 <code>mysqldump</code>/<code>pg_dump</code> 바이너리가 시스템 PATH에 등록되어 있어야 합니다. Windows: XAMPP의 경우 <code>C:\xampp\mysql\bin</code>을 PATH에 추가하세요.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/dbview" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">DbView</a>
        <a data-spa="/tool/schedule" class="badge badge--soft badge--secondary badge--sm" style="cursor:pointer;">Schedule</a>
    </div>
</div>
