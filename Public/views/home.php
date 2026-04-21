<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section text-center" style="max-width:800px;margin:0 auto;">
    <div style="margin-bottom:1rem;display:flex;align-items:center;justify-content:center;gap:.5rem;">
        <img src="/logo.svg" alt="Cat" style="height:56px;width:auto;">
        <span style="font-size:1.5rem;color:var(--text-muted,#94a3b8);">+</span>
        <img src="/new-php-logo.svg" alt="PHP" style="height:48px;width:auto;">
    </div>
    <h1 class="display-4 fw-bold mb-2">CatPHP</h1>
    <p class="lead" style="color:#94a3b8;">PHP 8.1+ 호환 경량 프레임워크</p>
    <p style="color:#64748b;" class="mb-3">코어 1파일 · require 1회 부팅 · 51개 도구 · 보안 기본 내장</p>

    <div class="d-flex justify-content-center gap-2 mb-4">
        <button class="btn btn--primary" onclick="IMCAT.alert('CatPHP 프레임워크에 오신 것을 환영합니다!')">데모 실행</button>
        <a href="https://github.com" class="btn btn--outline" target="_blank">GitHub</a>
    </div>

    <!-- 4대 원칙 -->
    <div class="card-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem;">
        <div class="card card--elevated"><div class="card__body text-center p-3"><div style="font-size:2rem;">⚡</div><h6 class="card__title mb-1">빠른 속도</h6><p class="text-muted caption mb-0">코어 1파일, OPcache + JIT</p></div></div>
        <div class="card card--elevated"><div class="card__body text-center p-3"><div style="font-size:2rem;">🔧</div><h6 class="card__title mb-1">사용 편리</h6><p class="text-muted caption mb-0">Shortcut 함수 + 체이닝</p></div></div>
        <div class="card card--elevated"><div class="card__body text-center p-3"><div style="font-size:2rem;">📖</div><h6 class="card__title mb-1">쉬운 학습</h6><p class="text-muted caption mb-0">함수명 = 기능, IDE 지원</p></div></div>
        <div class="card card--elevated"><div class="card__body text-center p-3"><div style="font-size:2rem;">🔒</div><h6 class="card__title mb-1">보안</h6><p class="text-muted caption mb-0">PDO · Argon2id · Sodium</p></div></div>
    </div>

    <!-- 빠른 시작 -->
    <div class="card card--outlined mb-4" style="text-align:left;">
        <div class="card__header"><h5 class="card__title mb-0">빠른 시작</h5></div>
        <pre class="demo-code"><code><span class="hl-c">// Public/index.php</span>
<span class="hl-k">require</span> <span class="hl-s">'../catphp/catphp.php'</span>;
<span class="hl-f">config</span>(<span class="hl-k">require</span> <span class="hl-s">'../config/app.php'</span>);

<span class="hl-c">// 라우트 등록</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-f">render</span>(<span class="hl-s">'index'</span>));
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/api/users'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">all</span>()));
<span class="hl-f">router</span>()-&gt;<span class="hl-f">dispatch</span>();</code></pre>
    </div>

    <!-- 도구 목록 -->
    <div class="card card--outlined" style="text-align:left;">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0">51개 도구</h5>
            <span class="badge badge--soft badge--info badge--sm">Cat\X → catphp/X.php</span>
        </div>
        <div class="card__body">
<?php
$groups = [
    ['기본',     'primary',   ['DB', 'Router', 'Cache', 'Log']],
    ['보안',     'danger',    ['Auth', 'Csrf', 'Encrypt', 'Firewall', 'Ip', 'Guard']],
    ['네트워크', 'info',      ['Http', 'Rate', 'Cors']],
    ['API',      'info',      ['Json', 'Api']],
    ['데이터',   'success',   ['Valid', 'Upload', 'Paginate', 'Cookie']],
    ['유틸',     'warning',   ['Event', 'Slug', 'Cli', 'Spider']],
    ['웹/CMS',   'secondary', ['Telegram', 'Image', 'Flash', 'Perm', 'Search', 'Meta', 'Geo']],
    ['블로그',   'dark',      ['Tag', 'Feed', 'Text']],
    ['인프라',   'danger',    ['Redis', 'Mail', 'Queue', 'Storage', 'Schedule', 'Notify', 'Hash', 'Excel']],
    ['HTTP',     'primary',   ['Env', 'Request', 'Response', 'Session']],
    ['데이터/테스트', 'info', ['Collection', 'Migration', 'Debug', 'Captcha', 'Faker']],
    ['유저',     'success',   ['User']],
];
foreach ($groups as [$label, $color, $tools]): ?>
            <div class="mb-2">
                <span class="badge badge--<?= $color ?> badge--sm me-1"><?= $label ?></span>
<?php foreach ($tools as $t): ?>
                <span class="badge badge--soft badge--<?= $color ?> badge--sm"><?= $t ?></span>
<?php endforeach; ?>
            </div>
<?php endforeach; ?>
        </div>
    </div>

    <div class="mt-4">
        <p class="text-muted caption mb-0">&copy; <?= date('Y') ?> CatPHP — PHP 8.1+ · MIT</p>
    </div>
</div>
