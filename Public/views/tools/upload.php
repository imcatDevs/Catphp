<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--success);">cloud_upload</i>
        <div><h4 class="mb-0">Upload</h4><span class="text-muted caption">Cat\Upload — 파일 업로드</span></div>
        <span class="badge badge--success badge--sm ms-auto">upload()</span>
    </div>

    <p class="mb-2"><strong>안전한 파일 업로드</strong> 도구입니다. 파일 크기, 확장자, <strong>MIME 타입 교차 검증</strong>(<code>finfo_file()</code>)으로 위변조된 파일을 차단합니다.</p>
    <p class="mb-3">체이닝으로 제한 조건을 설정하고 <code>save()</code>로 저장합니다. 파일명은 자동으로 유니크하게 생성되며, Guard 도구와 연동하여 파일명 정화도 수행합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>file(string $field)</code></td><td><code>self</code></td><td>업로드 필드명 지정</td></tr>
                    <tr><td><code>maxSize(string $size)</code></td><td><code>self</code></td><td>최대 크기 (예: <code>'5M'</code>, <code>'500K'</code>)</td></tr>
                    <tr><td><code>allowTypes(array $types)</code></td><td><code>self</code></td><td>허용 확장자 + MIME 교차 검증</td></tr>
                    <tr><td><code>save(string $dir, ?string $filename)</code></td><td><code>?string</code></td><td>저장 후 파일명 반환. 실패 시 null</td></tr>
                    <tr><td><code>move(string $from, string $to)</code></td><td><code>bool</code></td><td>파일 이동 (rename)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">기본 파일 업로드</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 이미지 업로드 — 5MB, jpg/png/webp만 허용</span>
<span class="hl-v">$filename</span> = <span class="hl-f">upload</span>()
    -&gt;<span class="hl-f">file</span>(<span class="hl-s">'avatar'</span>)
    -&gt;<span class="hl-f">maxSize</span>(<span class="hl-s">'5M'</span>)
    -&gt;<span class="hl-f">allowTypes</span>([<span class="hl-s">'jpg'</span>, <span class="hl-s">'png'</span>, <span class="hl-s">'webp'</span>])
    -&gt;<span class="hl-f">save</span>(<span class="hl-s">'storage/uploads/avatars'</span>);

<span class="hl-k">if</span> (<span class="hl-v">$filename</span>) {
    <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'id'</span>, <span class="hl-v">$userId</span>)
        -&gt;<span class="hl-f">update</span>([<span class="hl-s">'avatar'</span> =&gt; <span class="hl-v">$filename</span>]);
} <span class="hl-k">else</span> {
    <span class="hl-f">json</span>()-&gt;<span class="hl-f">fail</span>(<span class="hl-s">'업로드 실패'</span>);
}</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>보안:</strong> <code>finfo_file()</code>로 실제 MIME 타입을 검증합니다. 확장자만 변경한 위장 파일(예: .php를 .jpg로 변경)은 차단됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/guard" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Guard</a>
        <a data-spa="/tool/image" class="badge badge--soft badge--secondary badge--sm" style="cursor:pointer;">Image</a>
    </div>
</div>
