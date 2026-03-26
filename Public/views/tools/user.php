<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--primary);">person</i>
        <div><h4 class="mb-0">User</h4><span class="text-muted caption">Cat\User — 유저 CRUD + 인증 통합</span></div>
        <span class="badge badge--primary badge--sm ms-auto">user()</span>
    </div>

    <p class="mb-2"><strong>유저 관리</strong> 도구입니다. DB 기반 CRUD, Auth 연동 로그인, XSS 자동 살균을 제공합니다.</p>
    <p class="mb-3">조회 메서드는 자동으로 XSS 살균을 적용합니다. 내부 로직(비밀번호 검증 등)에는 <code>raw()</code>/<code>rawBy()</code>로 원본 데이터에 접근합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:300px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>current()</code></td><td><code>?array</code></td><td>현재 로그인 유저 (XSS 살균)</td></tr>
                    <tr><td><code>find(int|string $id)</code></td><td><code>?array</code></td><td>ID로 조회</td></tr>
                    <tr><td><code>findBy(string $col, mixed $val)</code></td><td><code>?array</code></td><td>필드로 조회</td></tr>
                    <tr><td><code>get(string $field, mixed $default)</code></td><td><code>mixed</code></td><td>현재 유저의 특정 필드</td></tr>
                    <tr><td><code>list(int $limit, int $offset)</code></td><td><code>array</code></td><td>유저 목록</td></tr>
                    <tr><td><code>search(string $col, string $keyword)</code></td><td><code>array</code></td><td>유저 검색</td></tr>
                    <tr><td><code>count()</code></td><td><code>int</code></td><td>유저 수</td></tr>
                    <tr><td><code>exists(string $col, mixed $val)</code></td><td><code>bool</code></td><td>존재 확인</td></tr>
                    <tr><td><code>raw / rawBy</code></td><td><code>?array</code></td><td>원본 조회 (내부 로직용)</td></tr>
                    <tr><td><code>create(array $data)</code></td><td><code>string|false</code></td><td>유저 생성 (비밀번호 자동 해싱)</td></tr>
                    <tr><td><code>update(int|string $id, array $data)</code></td><td><code>int</code></td><td>유저 수정</td></tr>
                    <tr><td><code>delete(int|string $id)</code></td><td><code>int</code></td><td>유저 삭제</td></tr>
                    <tr><td><code>attempt(string $email, string $pw)</code></td><td><code>bool</code></td><td>이메일+비밀번호 로그인 (브루트포스 방어 내장)</td></tr>
                    <tr><td><code>refresh()</code></td><td><code>?array</code></td><td>현재 유저 세션 새로고침</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 유저 생성 (비밀번호 자동 Argon2id 해싱)</span>
<span class="hl-f">user</span>()-&gt;<span class="hl-f">create</span>([
    <span class="hl-s">'name'</span>     =&gt; <span class="hl-s">'김개발'</span>,
    <span class="hl-s">'email'</span>    =&gt; <span class="hl-s">'dev@catphp.dev'</span>,
    <span class="hl-s">'password'</span> =&gt; <span class="hl-s">'secret123'</span>,
]);

<span class="hl-c">// 로그인 (IP당 5분/10회 제한, 30분/50회 초과 시 Firewall 자동 밴)</span>
<span class="hl-k">if</span> (<span class="hl-f">user</span>()-&gt;<span class="hl-f">attempt</span>(<span class="hl-v">$email</span>, <span class="hl-v">$password</span>)) {
    <span class="hl-f">response</span>()-&gt;<span class="hl-f">redirect</span>(<span class="hl-s">'/dashboard'</span>);
}

<span class="hl-c">// 현재 유저 정보</span>
<span class="hl-v">$name</span> = <span class="hl-f">user</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'name'</span>, <span class="hl-s">'게스트'</span>);</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> <code>current()</code>/<code>find()</code> 등 조회 메서드는 자동 XSS 살균이 적용됩니다. 비밀번호 검증 등 내부 로직에서는 <code>raw()</code>/<code>rawBy()</code>를 사용하세요.</span>
    </div>
    <div class="alert alert--danger mb-3">
        <span class="alert__message"><strong>브루트포스 방어:</strong> <code>attempt()</code>는 IP 기준 레이트 리미트(5분/10회)를 내장합니다. 제한 초과 시 자동 차단되며, 30분간 50회 초과 시 Firewall 영구 밴이 적용됩니다. 로그인 성공 시 카운터가 초기화됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/auth" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Auth</a>
        <a data-spa="/tool/session" class="badge badge--soft badge--warning badge--sm" style="cursor:pointer;">Session</a>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
    </div>
</div>
