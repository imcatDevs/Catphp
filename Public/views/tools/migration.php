<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--warning);">storage</i>
        <div><h4 class="mb-0">Migration</h4><span class="text-muted caption">Cat\Migration — DB 스키마 버전 관리</span></div>
        <span class="badge badge--warning badge--sm ms-auto">migration()</span>
    </div>

    <p class="mb-2"><strong>DB 스키마 버전 관리</strong> 도구입니다. 마이그레이션 파일로 테이블 생성/수정을 추적하고, 롤백으로 되돌릴 수 있습니다.</p>
    <p class="mb-3">각 마이그레이션에는 <code>up()</code>과 <code>down()</code> 함수가 있으며, 배치 단위로 관리됩니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:320px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>run()</code></td><td><code>array</code></td><td>미실행 마이그레이션 실행</td></tr>
                    <tr><td><code>rollback(int $steps = 1)</code></td><td><code>array</code></td><td>마지막 배치 롤백</td></tr>
                    <tr><td><code>fresh()</code></td><td><code>array</code></td><td>전체 롤백 후 재실행</td></tr>
                    <tr><td><code>status()</code></td><td><code>array</code></td><td>마이그레이션 상태 목록</td></tr>
                    <tr><td><code>create(string $name, string $table, string $type)</code></td><td><code>string</code></td><td>마이그레이션 파일 생성</td></tr>
                    <tr><td><code>getPath()</code></td><td><code>string</code></td><td>마이그레이션 디렉토리 경로</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">CLI 명령어</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c"># 마이그레이션 파일 생성</span>
php cli.php migrate:create create_users_table --table=users

<span class="hl-c"># 실행 / 롤백 / 상태</span>
php cli.php migrate
php cli.php migrate:rollback
php cli.php migrate:status

<span class="hl-c"># 전체 초기화 후 재실행</span>
php cli.php migrate:fresh</code></pre>

    <h6 class="mb-2">마이그레이션 파일 구조</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// migrations/2024_01_15_000001_create_users_table.php</span>
<span class="hl-k">return</span> [
    <span class="hl-s">'up'</span> =&gt; <span class="hl-k">function</span>() {
        <span class="hl-f">db</span>()-&gt;<span class="hl-f">exec</span>(<span class="hl-s">'CREATE TABLE users (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL
        )'</span>);
    },
    <span class="hl-s">'down'</span> =&gt; <span class="hl-k">function</span>() {
        <span class="hl-f">db</span>()-&gt;<span class="hl-f">exec</span>(<span class="hl-s">'DROP TABLE IF EXISTS users'</span>);
    },
];</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>주의:</strong> <code>fresh()</code>는 모든 테이블을 삭제 후 재생성합니다. 프로덕션에서 절대 사용하지 마세요.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/cli" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Cli</a>
    </div>
</div>
