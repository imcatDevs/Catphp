<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--secondary);">admin_panel_settings</i>
        <div><h4 class="mb-0">Perm</h4><span class="text-muted caption">Cat\Perm — 역할 기반 권한 관리</span></div>
        <span class="badge badge--secondary badge--sm ms-auto">perm()</span>
    </div>

    <p class="mb-2"><strong>역할(Role) 기반 권한 관리</strong> 도구입니다. 역할에 권한(Permission)을 매핑하고, 현재 로그인 사용자의 권한을 확인하여 접근 제어를 구현합니다.</p>
    <p class="mb-3">Auth 도구와 연동하여 <code>auth()->user()</code>의 역할 정보를 기반으로 권한을 확인합니다. <code>can()</code>/<code>cannot()</code>으로 권한 보유 여부, <code>middleware()</code>로 라우트별 역할 제한이 가능합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>role(string $role, array $perms)</code></td><td><code>self</code></td><td>역할에 권한 부여 (정의)</td></tr>
                    <tr><td><code>can(string $permission)</code></td><td><code>bool</code></td><td>현재 사용자 권한 확인</td></tr>
                    <tr><td><code>cannot(string $permission)</code></td><td><code>bool</code></td><td>권한 없음 확인 (<code>!can()</code>)</td></tr>
                    <tr><td><code>roles()</code></td><td><code>array</code></td><td>등록된 역할 목록</td></tr>
                    <tr><td><code>assign(string $role)</code></td><td><code>void</code></td><td>현재 사용자에게 역할 할당</td></tr>
                    <tr><td><code>middleware(string ...$roles)</code></td><td><code>callable</code></td><td>특정 역할만 접근 허용 미들웨어</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">역할 정의 + 권한 확인</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 역할에 권한 매핑 (부팅 시)</span>
<span class="hl-f">perm</span>()-&gt;<span class="hl-f">role</span>(<span class="hl-s">'admin'</span>, [<span class="hl-s">'posts.edit'</span>, <span class="hl-s">'posts.delete'</span>, <span class="hl-s">'users.manage'</span>]);
<span class="hl-f">perm</span>()-&gt;<span class="hl-f">role</span>(<span class="hl-s">'editor'</span>, [<span class="hl-s">'posts.edit'</span>]);

<span class="hl-c">// 권한 확인</span>
<span class="hl-k">if</span> (<span class="hl-f">perm</span>()-&gt;<span class="hl-f">can</span>(<span class="hl-s">'posts.edit'</span>)) {
    <span class="hl-c">// 게시글 수정 가능</span>
}

<span class="hl-k">if</span> (<span class="hl-f">perm</span>()-&gt;<span class="hl-f">cannot</span>(<span class="hl-s">'users.manage'</span>)) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'권한 없음'</span>, <span class="hl-k">null</span>, <span class="hl-n">403</span>);
}</code></pre>

    <h6 class="mb-2">라우트 보호 미들웨어</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// admin 역할만 접근 허용</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">group</span>(<span class="hl-s">'/admin'</span>, <span class="hl-k">function</span>() {
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">perm</span>()-&gt;<span class="hl-f">middleware</span>(<span class="hl-s">'admin'</span>));
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/users'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-c">/* 사용자 관리 */</span> <span class="hl-s">''</span>);
});

<span class="hl-c">// 여러 역할 허용 (admin 또는 editor)</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">perm</span>()-&gt;<span class="hl-f">middleware</span>(<span class="hl-s">'admin'</span>, <span class="hl-s">'editor'</span>));</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>연동:</strong> <code>auth()->user()</code>의 <code>role</code> 필드를 기반으로 권한을 확인합니다. 로그인되지 않은 사용자는 모든 권한이 거부됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/auth" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Auth</a>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
    </div>
</div>
