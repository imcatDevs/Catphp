<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--secondary);">translate</i>
        <div><h4 class="mb-0">Geo</h4><span class="text-muted caption">Cat\Geo — 다국어 · 로케일</span></div>
        <span class="badge badge--secondary badge--sm ms-auto">geo()</span>
    </div>

    <p class="mb-2"><strong>다국어(i18n)</strong> 및 <strong>로케일</strong> 관리 도구입니다. 브라우저 Accept-Language, 쿠키, URL 파라미터를 통해 사용자 언어를 자동 감지하고, <code>lang/</code> 파일의 번역 문자열을 로드합니다.</p>
    <p class="mb-3"><code>t()</code>으로 번역 키를 조회하고, <code>currency()</code>/<code>date()</code>로 로케일에 맞는 통화/날짜 형식을 출력합니다. <code>hreflang()</code>으로 SEO용 다국어 링크 태그를 생성합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:260px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>getLocale()</code></td><td><code>string</code></td><td>현재 로케일 반환</td></tr>
                    <tr><td><code>locale(string $loc)</code></td><td><code>self</code></td><td>로케일 설정</td></tr>
                    <tr><td><code>t(string $key, array $replace)</code></td><td><code>string</code></td><td>번역 문자열 조회</td></tr>
                    <tr><td><code>detect()</code></td><td><code>string</code></td><td>사용자 언어 자동 감지</td></tr>
                    <tr><td><code>url(string $path, ?string $locale)</code></td><td><code>string</code></td><td>다국어 URL 생성</td></tr>
                    <tr><td><code>switch(string $locale)</code></td><td><code>string</code></td><td>언어 전환 URL 반환</td></tr>
                    <tr><td><code>currency(float $amount)</code></td><td><code>string</code></td><td>통화 형식 출력</td></tr>
                    <tr><td><code>date(int $timestamp)</code></td><td><code>string</code></td><td>로케일별 날짜 형식</td></tr>
                    <tr><td><code>hreflang()</code></td><td><code>string</code></td><td>hreflang 링크 태그 생성</td></tr>
                    <tr><td><code>middleware()</code></td><td><code>callable</code></td><td>자동 언어 감지 미들웨어</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 자동 언어 감지 미들웨어</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">geo</span>()-&gt;<span class="hl-f">middleware</span>());

<span class="hl-c">// 번역 사용 (lang/ko.php → ['welcome' => '환영합니다'])</span>
<span class="hl-k">echo</span> <span class="hl-f">geo</span>()-&gt;<span class="hl-f">t</span>(<span class="hl-s">'welcome'</span>);  <span class="hl-c">// → "환영합니다"</span>

<span class="hl-c">// 통화 + 날짜 형식</span>
<span class="hl-k">echo</span> <span class="hl-f">geo</span>()-&gt;<span class="hl-f">currency</span>(<span class="hl-n">50000</span>);  <span class="hl-c">// → "₩50,000"</span>
<span class="hl-k">echo</span> <span class="hl-f">geo</span>()-&gt;<span class="hl-f">date</span>(<span class="hl-f">time</span>());  <span class="hl-c">// → "2024년 1월 15일"</span>

<span class="hl-c">// 언어 전환</span>
<span class="hl-v">$url</span> = <span class="hl-f">geo</span>()-&gt;<span class="hl-f">switch</span>(<span class="hl-s">'en'</span>);  <span class="hl-c">// 언어 전환 URL 반환</span></code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>감지 순서:</strong> URL 파라미터(<code>?lang=ko</code>) → 쿠키 → <code>Accept-Language</code> 헤더. <code>switch()</code>는 쿠키에 저장하여 다음 요청부터 자동 적용됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/cookie" class="badge badge--soft badge--success badge--sm" style="cursor:pointer;">Cookie</a>
        <a data-spa="/tool/ip" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Ip</a>
        <a data-spa="/tool/meta" class="badge badge--soft badge--secondary badge--sm" style="cursor:pointer;">Meta</a>
    </div>
</div>
