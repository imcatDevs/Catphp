<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="material-icons-outlined" style="font-size:28px;color:var(--success);">settings</i>
        <div><h4 class="mb-0">Env</h4><span class="text-muted caption">Cat\Env — .env 파일 파서</span></div>
        <span class="badge badge--success badge--sm ms-auto">env()</span>
    </div>

    <p class="mb-2"><strong>.env 파일 파서</strong> 및 환경변수 관리 도구입니다. 키=값 형식의 <code>.env</code> 파일을 로드하여 <code>$_ENV</code>와 <code>putenv()</code>에 동기화합니다.</p>
    <p class="mb-3"><code>required()</code>로 필수 환경변수를 검증하고, <code>write()</code>로 .env 파일을 프로그래밍 방식으로 수정할 수 있습니다.</p>

    <div class="card card--outlined mb-3">
        <div class="card__header"><h6 class="card__title mb-0">전체 메서드 레퍼런스</h6></div>
        <div class="card__body p-0">
            <table class="table table--sm mb-0">
                <thead><tr><th style="min-width:280px;">메서드</th><th>반환</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>load(string $path)</code></td><td><code>self</code></td><td>.env 파일 로드</td></tr>
                    <tr><td><code>get(string $key, mixed $default)</code></td><td><code>mixed</code></td><td>환경변수 가져오기</td></tr>
                    <tr><td><code>set(string $key, string $value)</code></td><td><code>self</code></td><td>환경변수 설정</td></tr>
                    <tr><td><code>has(string $key)</code></td><td><code>bool</code></td><td>존재 확인</td></tr>
                    <tr><td><code>required(array $keys)</code></td><td><code>self</code></td><td>필수 키 검증 (누락 시 예외)</td></tr>
                    <tr><td><code>all()</code></td><td><code>array</code></td><td>로드된 모든 변수</td></tr>
                    <tr><td><code>write(string $path, string $key, string $val)</code></td><td><code>bool</code></td><td>.env 파일 키 수정/추가</td></tr>
                    <tr><td><code>isLoaded()</code></td><td><code>bool</code></td><td>로드 여부</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <h6 class="mb-2">사용 예제</h6>
    <pre class="demo-code mb-3"><code><span class="hl-c">// .env 로드 + 필수 키 검증</span>
<span class="hl-f">env</span>()-&gt;<span class="hl-f">load</span>(<span class="hl-s">'.env'</span>)
    -&gt;<span class="hl-f">required</span>([<span class="hl-s">'DB_HOST'</span>, <span class="hl-s">'DB_NAME'</span>, <span class="hl-s">'APP_KEY'</span>]);

<span class="hl-c">// 값 읽기</span>
<span class="hl-v">$debug</span> = <span class="hl-f">env</span>(<span class="hl-s">'APP_DEBUG'</span>, <span class="hl-s">'false'</span>);
<span class="hl-v">$dbHost</span> = <span class="hl-f">env</span>(<span class="hl-s">'DB_HOST'</span>, <span class="hl-s">'127.0.0.1'</span>);

<span class="hl-c">// .env 파일 수정 (배포 스크립트 등)</span>
<span class="hl-f">env</span>()-&gt;<span class="hl-f">write</span>(<span class="hl-s">'.env'</span>, <span class="hl-s">'APP_VERSION'</span>, <span class="hl-s">'2.1.0'</span>);</code></pre>

    <div class="alert alert--warning mb-3">
        <span class="alert__message"><strong>보안:</strong> <code>.env</code> 파일은 반드시 <code>.gitignore</code>와 <code>.htaccess</code>에서 차단하세요. 비밀번호, API 키 등 민감한 정보가 포함됩니다.</span>
    </div>

    <div class="d-flex gap-1 flex-wrap">
        <span class="badge badge--soft badge--secondary badge--sm">관련:</span>
        <a data-spa="/tool/encrypt" class="badge badge--soft badge--danger badge--sm" style="cursor:pointer;">Encrypt</a>
    </div>
</div>
