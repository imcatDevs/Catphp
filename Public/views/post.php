<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section" style="max-width:700px;margin:0 auto;">
<?php if (!isset($slug) || $slug === ''): ?>
    <div class="alert alert--warning">
        <span class="alert__message">게시글 슬러그가 지정되지 않았습니다.</span>
    </div>
<?php else: ?>
    <nav class="mb-3">
        <span class="text-muted caption">블로그</span>
        <span class="text-muted caption mx-1">/</span>
        <span class="caption"><?= e($slug) ?></span>
    </nav>
    <article>
        <h2 class="mb-2"><?= e($slug) ?></h2>
        <p class="text-muted mb-3">이 페이지는 게시글 상세 템플릿입니다.</p>
        <div class="card card--outlined">
            <div class="card__body">
                <p>슬러그 <code><?= e($slug) ?></code>에 해당하는 게시글 내용이 여기에 표시됩니다.</p>
                <p class="text-muted caption mb-0">DB 연결 후 실제 데이터를 로드하세요.</p>
            </div>
        </div>
    </article>
<?php endif; ?>
</div>
