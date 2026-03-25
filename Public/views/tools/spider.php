<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--secondary);">bug_report</i>
        <div><h4 class="mb-0">Spider</h4><span class="text-muted caption">Cat\Spider — 웹 크롤러</span></div>
        <span class="badge badge--secondary badge--sm ms-auto">spider()</span>
    </div>

    <p class="mb-2"><strong>웹 크롤링/스크래핑</strong> 도구입니다. URL 패턴 매칭, CSS 셀렉터 파싱, 페이지네이션 자동 추적, 요청 딜레이 등을 체이닝 API로 설정합니다.</p>
    <p class="mb-3">정규식 또는 glob 패턴으로 크롤링 대상 URL을 필터링하고, <code>each()</code> 콜백으로 각 페이지의 데이터를 처리합니다. <code>sanitize()</code>로 추출 데이터 정화, <code>encoding()</code>으로 문자셋 변환이 가능합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">주요 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>startAt(string $url)</code></td><td><code>self</code></td><td>시작 URL 설정</td></tr>
                    <tr><td><code>pattern(string $glob)</code></td><td><code>self</code></td><td>URL glob 패턴 필터</td></tr>
                    <tr><td><code>regex(string $re)</code></td><td><code>self</code></td><td>URL 정규식 필터</td></tr>
                    <tr><td><code>skipAfter(int $n)</code></td><td><code>self</code></td><td>최대 크롤링 페이지 수</td></tr>
                    <tr><td><code>delay(int $ms)</code></td><td><code>self</code></td><td>요청 간 딜레이 (ms)</td></tr>
                    <tr><td><code>timeout(int $sec)</code></td><td><code>self</code></td><td>요청 타임아웃</td></tr>
                    <tr><td><code>userAgent(string $ua)</code></td><td><code>self</code></td><td>User-Agent 설정</td></tr>
                    <tr><td><code>each(callable $fn)</code></td><td><code>self</code></td><td>각 페이지 콜백</td></tr>
                    <tr><td><code>fetch()</code></td><td><code>self</code></td><td>크롤링 실행</td></tr>
                    <tr><td><code>find(string $selector)</code></td><td><code>array</code></td><td>CSS 셀렉터로 요소 추출</td></tr>
                    <tr><td><code>parse(string $selector)</code></td><td><code>array</code></td><td>테이블/리스트 구조화 파싱</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// 뉴스 사이트 크롤링</span>
<span class="hl-f">spider</span>()
    -&gt;<span class="hl-f">startAt</span>(<span class="hl-s">'https://news.example.com'</span>)
    -&gt;<span class="hl-f">pattern</span>(<span class="hl-s">'*/article/*'</span>)
    -&gt;<span class="hl-f">skipAfter</span>(<span class="hl-n">50</span>)
    -&gt;<span class="hl-f">delay</span>(<span class="hl-n">500</span>)
    -&gt;<span class="hl-f">each</span>(<span class="hl-k">function</span>(<span class="hl-v">$page</span>) {
        <span class="hl-v">$title</span> = <span class="hl-v">$page</span>-&gt;<span class="hl-f">find</span>(<span class="hl-s">'h1.title'</span>)[<span class="hl-n">0</span>] ?? <span class="hl-s">''</span>;
        <span class="hl-v">$body</span>  = <span class="hl-v">$page</span>-&gt;<span class="hl-f">find</span>(<span class="hl-s">'.article-body'</span>)[<span class="hl-n">0</span>] ?? <span class="hl-s">''</span>;
        <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'articles'</span>)-&gt;<span class="hl-f">insert</span>([
            <span class="hl-s">'title'</span> =&gt; <span class="hl-v">$title</span>,
            <span class="hl-s">'body'</span>  =&gt; <span class="hl-v">$body</span>,
        ]);
    })
    -&gt;<span class="hl-f">fetch</span>();</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>주의:</strong> 크롤링 대상 사이트의 <code>robots.txt</code>와 이용약관을 반드시 확인하세요. <code>delay()</code>로 적절한 요청 간격을 설정하여 서버에 부담을 주지 마세요.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/http" class="badge badge--soft badge--info badge--sm" style="cursor:pointer;">Http</a>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
    </div>
</div>
