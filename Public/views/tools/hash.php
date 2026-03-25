<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--danger);">fingerprint</i>
        <div><h4 class="mb-0">Hash</h4><span class="text-muted caption">Cat\Hash — 해싱 · 무결성 검증</span></div>
        <span class="badge badge--danger badge--sm ms-auto">hasher()</span>
    </div>

    <p class="mb-2"><strong>파일/문자열 해싱</strong> 및 <strong>무결성 검증</strong> 도구입니다. SHA-256, HMAC 서명, 비밀번호 해싱(Argon2id/Bcrypt), 디렉토리 매니페스트를 제공합니다.</p>
    <p class="mb-3">모든 비교는 <code>hash_equals()</code>로 타이밍 공격을 방지합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">주요 메서드</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:320px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>file(string $path, ?string $algo)</code></td><td><code>string</code></td><td>파일 해시</td></tr>
                    <tr><td><code>verify(string $path, string $hash)</code></td><td><code>bool</code></td><td>파일 무결성 검증</td></tr>
                    <tr><td><code>checksum(string $path)</code></td><td><code>string</code></td><td>CRC32 체크섬</td></tr>
                    <tr><td><code>string(string $data, ?string $algo)</code></td><td><code>string</code></td><td>문자열 해시</td></tr>
                    <tr><td><code>hmac(string $data, string $key)</code></td><td><code>string</code></td><td>HMAC 서명</td></tr>
                    <tr><td><code>verifyHmac(string $data, string $mac, string $key)</code></td><td><code>bool</code></td><td>HMAC 검증</td></tr>
                    <tr><td><code>password(string $pw)</code></td><td><code>string</code></td><td>비밀번호 해시 (Argon2id)</td></tr>
                    <tr><td><code>passwordVerify(string $pw, string $hash)</code></td><td><code>bool</code></td><td>비밀번호 검증</td></tr>
                    <tr><td><code>directory(string $path)</code></td><td><code>array</code></td><td>디렉토리 매니페스트</td></tr>
                    <tr><td><code>verifyDirectory(string $path, array $manifest)</code></td><td><code>array</code></td><td>디렉토리 무결성 검증</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 파일 해시 + 검증</span>
<span class="hl-v">$hash</span> = <span class="hl-f">hasher</span>()-&gt;<span class="hl-f">file</span>(<span class="hl-s">'storage/backup.zip'</span>);
<span class="hl-f">hasher</span>()-&gt;<span class="hl-f">verify</span>(<span class="hl-s">'storage/backup.zip'</span>, <span class="hl-v">$hash</span>);  <span class="hl-c">// true</span>

<span class="hl-c">// HMAC 서명 (API 웹훅)</span>
<span class="hl-v">$sig</span> = <span class="hl-f">hasher</span>()-&gt;<span class="hl-f">hmac</span>(<span class="hl-v">$payload</span>, <span class="hl-v">$secret</span>);
<span class="hl-k">if</span> (!<span class="hl-f">hasher</span>()-&gt;<span class="hl-f">verifyHmac</span>(<span class="hl-v">$payload</span>, <span class="hl-v">$received</span>, <span class="hl-v">$secret</span>)) {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'서명 불일치'</span>, <span class="hl-k">null</span>, <span class="hl-n">403</span>);
}

<span class="hl-c">// 디렉토리 무결성 체크</span>
<span class="hl-v">$manifest</span> = <span class="hl-f">hasher</span>()-&gt;<span class="hl-f">directory</span>(<span class="hl-s">'catphp/'</span>);
<span class="hl-v">$diff</span> = <span class="hl-f">hasher</span>()-&gt;<span class="hl-f">verifyDirectory</span>(<span class="hl-s">'catphp/'</span>, <span class="hl-v">$manifest</span>);</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> 비밀번호 해싱에는 <code>password()</code>를 사용하세요. <code>string()</code>은 단순 해시이므로 비밀번호에 사용하면 안 됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/encrypt" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Encrypt</a>
        <a data-spa="/tool/auth" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Auth</a>
    </div>
</div>
