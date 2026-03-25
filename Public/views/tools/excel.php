<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--success);">table_chart</i>
        <div><h4 class="mb-0">Excel</h4><span class="text-muted caption">Cat\Excel — CSV/XLSX 가져오기·내보내기</span></div>
        <span class="badge badge--success badge--sm ms-auto">excel()</span>
    </div>

    <p class="mb-2"><strong>CSV/XLSX</strong> 파일 가져오기·내보내기 도구입니다. 배열 데이터를 CSV 또는 XLSX로 저장하고, CSV 파일을 배열로 읽습니다.</p>
    <p class="mb-3">XLSX 내보내기는 <code>ext-zip</code> 확장이 필요합니다. <code>download()</code>로 브라우저 다운로드도 지원합니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:300px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>from(array $rows)</code></td><td><code>self</code></td><td>배열 데이터 설정</td></tr>
                    <tr><td><code>headers(array $headers)</code></td><td><code>self</code></td><td>헤더 설정</td></tr>
                    <tr><td><code>delimiter(string $d)</code></td><td><code>self</code></td><td>CSV 구분자 설정</td></tr>
                    <tr><td><code>toCsv(string $path)</code></td><td><code>bool</code></td><td>CSV 파일로 저장</td></tr>
                    <tr><td><code>toCsvString()</code></td><td><code>string</code></td><td>CSV 문자열 반환</td></tr>
                    <tr><td><code>toXlsx(string $path)</code></td><td><code>bool</code></td><td>XLSX 파일로 저장</td></tr>
                    <tr><td><code>fromCsv(string $path, bool $hasHeader)</code></td><td><code>array</code></td><td>CSV 파일 읽기</td></tr>
                    <tr><td><code>fromCsvString(string $content)</code></td><td><code>array</code></td><td>CSV 문자열 읽기</td></tr>
                    <tr><td><code>download(string $filename)</code></td><td><code>never</code></td><td>브라우저 다운로드 (exit)</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// DB → CSV 내보내기</span>
<span class="hl-v">$users</span> = <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">all</span>();
<span class="hl-f">excel</span>()-&gt;<span class="hl-f">headers</span>([<span class="hl-s">'ID'</span>, <span class="hl-s">'이름'</span>, <span class="hl-s">'이메일'</span>])
    -&gt;<span class="hl-f">from</span>(<span class="hl-v">$users</span>)
    -&gt;<span class="hl-f">toCsv</span>(<span class="hl-s">'storage/users.csv'</span>);

<span class="hl-c">// CSV 가져오기</span>
<span class="hl-v">$rows</span> = <span class="hl-f">excel</span>()-&gt;<span class="hl-f">fromCsv</span>(<span class="hl-s">'storage/import.csv'</span>);

<span class="hl-c">// 브라우저 다운로드</span>
<span class="hl-f">excel</span>()-&gt;<span class="hl-f">from</span>(<span class="hl-v">$users</span>)-&gt;<span class="hl-f">download</span>(<span class="hl-s">'export.xlsx'</span>);</code></pre>

    <div class="alert alert--info mb-3">
        <span class="alert__message"><strong>팁:</strong> XLSX 내보내기에는 <code>ext-zip</code>이 필요합니다. CSV만 사용한다면 추가 확장 없이 동작합니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/db" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">DB</a>
        <a data-spa="/tool/storage" class="badge badge--soft badge--primary badge--sm" style="cursor:pointer;">Storage</a>
    </div>
</div>
