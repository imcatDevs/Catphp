<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--primary);">output</i>
        <div><h4 class="mb-0">Response</h4><span class="text-muted caption">Cat\Response — HTTP 응답 빌더</span></div>
        <span class="badge badge--primary badge--sm ms-auto">response()</span>
    </div>

    <p class="mb-2"><strong>HTTP 응답 빌더</strong>입니다. HTML/텍스트/XML 응답, 리다이렉트, 파일 다운로드, 스트리밍을 체이닝 API로 제공합니다.</p>
    <p class="mb-3">CRLF 인젝션 방어와 오픈 리다이렉트 방어가 기본 적용됩니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">주요 메서드</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:320px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>status(int $code)</code></td><td><code>self</code></td><td>상태 코드 설정</td></tr>
                    <tr><td><code>header(string $name, string $value)</code></td><td><code>self</code></td><td>응답 헤더</td></tr>
                    <tr><td><code>contentType(string $type)</code></td><td><code>self</code></td><td>Content-Type</td></tr>
                    <tr><td><code>noCache() / cache(int $seconds)</code></td><td><code>self</code></td><td>캐시 제어</td></tr>
                    <tr><td><code>html(string $content)</code></td><td><code>never</code></td><td>HTML 응답</td></tr>
                    <tr><td><code>text(string $content)</code></td><td><code>never</code></td><td>텍스트 응답</td></tr>
                    <tr><td><code>xml(string $content)</code></td><td><code>never</code></td><td>XML 응답</td></tr>
                    <tr><td><code>noContent()</code></td><td><code>never</code></td><td>204 빈 응답</td></tr>
                    <tr><td><code>redirect(string $url, int $status)</code></td><td><code>never</code></td><td>리다이렉트</td></tr>
                    <tr><td><code>back(string $fallback)</code></td><td><code>never</code></td><td>이전 페이지</td></tr>
                    <tr><td><code>download(string $path)</code></td><td><code>never</code></td><td>파일 다운로드</td></tr>
                    <tr><td><code>stream(mixed $source)</code></td><td><code>never</code></td><td>스트리밍 응답</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 리다이렉트</span>
<span class="hl-f">response</span>()-&gt;<span class="hl-f">redirect</span>(<span class="hl-s">'/dashboard'</span>);

<span class="hl-c">// 파일 다운로드</span>
<span class="hl-f">response</span>()-&gt;<span class="hl-f">download</span>(<span class="hl-s">'storage/report.pdf'</span>, <span class="hl-s">'월간리포트.pdf'</span>);

<span class="hl-c">// 캐시 + HTML 응답</span>
<span class="hl-f">response</span>()-&gt;<span class="hl-f">cache</span>(<span class="hl-n">3600</span>)-&gt;<span class="hl-f">html</span>(<span class="hl-v">$renderedPage</span>);

<span class="hl-c">// API: 빈 응답 (DELETE 성공 등)</span>
<span class="hl-f">response</span>()-&gt;<span class="hl-f">noContent</span>();</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> <code>redirect()</code>는 외부 URL을 기본 차단합니다 (오픈 리다이렉트 방어). 헤더 값에서 CRLF 문자도 자동 제거됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/request" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">Request</a>
        <a data-spa="/tool/json" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Json</a>
    </div>
</div>
