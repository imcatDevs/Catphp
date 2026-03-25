<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <h3 class="mb-1">기본 도구</h3>
    <p class="text-muted mb-4">DB · Router · Cache · Log — CatPHP의 핵심 4가지 도구</p>

    <!-- DB -->
    <div class="card card--outlined mb-4" id="demo-db">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">storage</i> DB</h5>
            <span class="badge badge--primary badge--sm">Cat\DB</span>
        </div>
        <div class="card__body">
            <p class="mb-3">PDO 기반 쿼리 빌더. 체이닝 API, 지연 연결, prepared statement 전용.</p>

            <h6 class="mb-2">CRUD 체이닝</h6>
            <pre class="demo-code mb-3"><code><span class="hl-c">// SELECT</span>
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'status'</span>, <span class="hl-s">'active'</span>)-&gt;<span class="hl-f">all</span>();
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">select</span>(<span class="hl-s">'id'</span>, <span class="hl-s">'name'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'id'</span>, <span class="hl-n">1</span>)-&gt;<span class="hl-f">first</span>();
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">whereIn</span>(<span class="hl-s">'role'</span>, [<span class="hl-s">'admin'</span>, <span class="hl-s">'editor'</span>])-&gt;<span class="hl-f">all</span>();

<span class="hl-c">// INSERT</span>
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">insert</span>([<span class="hl-s">'name'</span> =&gt; <span class="hl-s">'Cat'</span>, <span class="hl-s">'email'</span> =&gt; <span class="hl-s">'cat@catphp.dev'</span>]);

<span class="hl-c">// UPDATE / DELETE</span>
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'id'</span>, <span class="hl-n">1</span>)-&gt;<span class="hl-f">update</span>([<span class="hl-s">'name'</span> =&gt; <span class="hl-s">'Updated'</span>]);
<span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'id'</span>, <span class="hl-n">1</span>)-&gt;<span class="hl-f">delete</span>();

<span class="hl-c">// 트랜잭션</span>
<span class="hl-f">db</span>()-&gt;<span class="hl-f">transaction</span>(<span class="hl-k">function</span>() {
    <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'accounts'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'id'</span>, <span class="hl-n">1</span>)-&gt;<span class="hl-f">update</span>([<span class="hl-s">'balance'</span> =&gt; <span class="hl-n">900</span>]);
    <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'accounts'</span>)-&gt;<span class="hl-f">where</span>(<span class="hl-s">'id'</span>, <span class="hl-n">2</span>)-&gt;<span class="hl-f">update</span>([<span class="hl-s">'balance'</span> =&gt; <span class="hl-n">1100</span>]);
});</code></pre>

            <h6 class="mb-2">시뮬레이션 데이터</h6>
            <div class="alert alert--info mb-3"><span class="alert__message">DB 연결 없이 CatUI DataTable로 시뮬레이션합니다.</span></div>
            <div class="table-responsive">
                <table class="table table--striped table--hover table--bordered">
                    <thead><tr><th>#</th><th>이름</th><th>이메일</th><th>역할</th><th>상태</th></tr></thead>
                    <tbody>
                        <tr><td>1</td><td>김개발</td><td>kim@catphp.dev</td><td><span class="badge badge--primary badge--sm">admin</span></td><td><span class="badge badge--success badge--sm">active</span></td></tr>
                        <tr><td>2</td><td>이디자인</td><td>lee@catphp.dev</td><td><span class="badge badge--secondary badge--sm">user</span></td><td><span class="badge badge--success badge--sm">active</span></td></tr>
                        <tr><td>3</td><td>박보안</td><td>park@catphp.dev</td><td><span class="badge badge--danger badge--sm">moderator</span></td><td><span class="badge badge--warning badge--sm">inactive</span></td></tr>
                        <tr><td>4</td><td>최백엔드</td><td>choi@catphp.dev</td><td><span class="badge badge--secondary badge--sm">user</span></td><td><span class="badge badge--success badge--sm">active</span></td></tr>
                        <tr><td>5</td><td>정프론트</td><td>jung@catphp.dev</td><td><span class="badge badge--info badge--sm">editor</span></td><td><span class="badge badge--success badge--sm">active</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Router -->
    <div class="card card--outlined mb-4" id="demo-router">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">alt_route</i> Router</h5>
            <span class="badge badge--primary badge--sm">Cat\Router</span>
        </div>
        <div class="card__body">
            <p class="mb-3">HTTP 메서드별 라우트 등록, 동적 파라미터, 그룹, 미들웨어, 리다이렉트 지원</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 기본 라우트</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-f">render</span>(<span class="hl-s">'index'</span>));

<span class="hl-c">// 동적 파라미터</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/posts/{slug}'</span>, <span class="hl-k">fn</span>(<span class="hl-k">string</span> <span class="hl-v">$slug</span>) =&gt; ...);
<span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/users/{id}'</span>, <span class="hl-k">fn</span>(<span class="hl-k">int</span> <span class="hl-v">$id</span>) =&gt; ...);

<span class="hl-c">// API 그룹</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">group</span>(<span class="hl-s">'/api'</span>, <span class="hl-k">function</span>() {
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'/users'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(...));
    <span class="hl-f">router</span>()-&gt;<span class="hl-f">post</span>(<span class="hl-s">'/users'</span>, <span class="hl-k">fn</span>() =&gt; <span class="hl-f">json</span>()-&gt;<span class="hl-f">ok</span>(...));
});

<span class="hl-c">// 미들웨어 + 리다이렉트</span>
<span class="hl-f">router</span>()-&gt;<span class="hl-f">use</span>(<span class="hl-f">guard</span>()-&gt;<span class="hl-f">middleware</span>());
<span class="hl-f">router</span>()-&gt;<span class="hl-f">redirect</span>(<span class="hl-s">'/old'</span>, <span class="hl-s">'/new'</span>, <span class="hl-n">301</span>);</code></pre>

            <h6 class="mb-2">현재 등록된 라우트</h6>
            <div class="list list--divided">
                <div class="list__item"><span class="badge badge--success badge--sm" style="min-width:50px;text-align:center;">GET</span><div class="list__content"><span class="list__title">/</span><span class="list__subtitle">SPA 메인 페이지</span></div></div>
                <div class="list__item"><span class="badge badge--success badge--sm" style="min-width:50px;text-align:center;">GET</span><div class="list__content"><span class="list__title">/home</span><span class="list__subtitle">홈 프래그먼트</span></div></div>
                <div class="list__item"><span class="badge badge--success badge--sm" style="min-width:50px;text-align:center;">GET</span><div class="list__content"><span class="list__title">/demo/{page}</span><span class="list__subtitle">데모 프래그먼트 (8개)</span></div></div>
                <div class="list__item"><span class="badge badge--success badge--sm" style="min-width:50px;text-align:center;">GET</span><div class="list__content"><span class="list__title">/tool/{tool}</span><span class="list__subtitle">도구 소개 (51개)</span></div></div>
                <div class="list__item"><span class="badge badge--success badge--sm" style="min-width:50px;text-align:center;">GET</span><div class="list__content"><span class="list__title">/api/posts</span><span class="list__subtitle">게시글 목록 API</span></div></div>
                <div class="list__item"><span class="badge badge--info badge--sm" style="min-width:50px;text-align:center;">POST</span><div class="list__content"><span class="list__title">/api/posts</span><span class="list__subtitle">게시글 생성 API</span></div></div>
            </div>
        </div>
    </div>

    <!-- Cache -->
    <div class="card card--outlined mb-4" id="demo-cache">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">cached</i> Cache</h5>
            <span class="badge badge--primary badge--sm">Cat\Cache</span>
        </div>
        <div class="card__body">
            <p class="mb-3">파일 기반 캐시. TTL, 동시성 보호(LOCK_EX), remember 패턴, 태그 삭제 지원</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 기본 CRUD</span>
<span class="hl-f">cache</span>()-&gt;<span class="hl-f">set</span>(<span class="hl-s">'key'</span>, <span class="hl-s">'value'</span>, <span class="hl-n">60</span>);       <span class="hl-c">// 60초 TTL</span>
<span class="hl-v">$val</span> = <span class="hl-f">cache</span>()-&gt;<span class="hl-f">get</span>(<span class="hl-s">'key'</span>);              <span class="hl-c">// 'value'</span>
<span class="hl-f">cache</span>()-&gt;<span class="hl-f">has</span>(<span class="hl-s">'key'</span>);                       <span class="hl-c">// true</span>
<span class="hl-f">cache</span>()-&gt;<span class="hl-f">del</span>(<span class="hl-s">'key'</span>);                       <span class="hl-c">// 삭제</span>

<span class="hl-c">// remember 패턴 (없으면 콜백 → 캐시 저장)</span>
<span class="hl-v">$stats</span> = <span class="hl-f">cache</span>()-&gt;<span class="hl-f">remember</span>(<span class="hl-s">'stats'</span>, <span class="hl-k">fn</span>() =&gt;
    <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">count</span>()
, <span class="hl-n">3600</span>);

<span class="hl-c">// 전체 삭제</span>
<span class="hl-f">cache</span>()-&gt;<span class="hl-f">clear</span>();</code></pre>

            <h6 class="mb-2">실행 결과</h6>
<?php
try {
    cache()->set('demo_key', 'Hello CatPHP!', 60);
    cache()->set('demo_counter', 42, 120);
    $cached = cache()->get('demo_key');
    $counter = cache()->get('demo_counter');
    $has = cache()->has('demo_key') ? 'true' : 'false';
    $miss = cache()->get('nonexistent', '(기본값)');
?>
            <div class="d-flex flex-column gap-2 mb-3">
                <div class="alert alert--success mb-0"><span class="alert__message"><strong>set('demo_key', 'Hello CatPHP!', 60)</strong> → 저장 완료</span></div>
                <div class="alert alert--info mb-0"><span class="alert__message"><strong>get('demo_key')</strong> → <code><?= htmlspecialchars((string)$cached) ?></code></span></div>
                <div class="alert alert--info mb-0"><span class="alert__message"><strong>get('demo_counter')</strong> → <code><?= htmlspecialchars((string)$counter) ?></code></span></div>
                <div class="alert alert--info mb-0"><span class="alert__message"><strong>has('demo_key')</strong> → <code><?= $has ?></code></span></div>
                <div class="alert alert--warning mb-0"><span class="alert__message"><strong>get('nonexistent', '(기본값)')</strong> → <code><?= htmlspecialchars($miss) ?></code></span></div>
            </div>
<?php } catch (\Throwable $e) { ?>
            <div class="alert alert--warning"><span class="alert__message">캐시 오류: <?= htmlspecialchars($e->getMessage()) ?></span></div>
<?php } ?>
        </div>
    </div>

    <!-- Log -->
    <div class="card card--outlined mb-4" id="demo-log">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">receipt_long</i> Log</h5>
            <span class="badge badge--primary badge--sm">Cat\Log</span>
        </div>
        <div class="card__body">
            <p class="mb-3">PSR-3 호환 로거. 레벨별 기록, 일별 파일, tail 역방향 읽기, 컨텍스트 배열 지원</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 레벨별 로그</span>
<span class="hl-f">logger</span>()-&gt;<span class="hl-f">info</span>(<span class="hl-s">'사용자 로그인'</span>, [<span class="hl-s">'user_id'</span> =&gt; <span class="hl-n">1</span>]);
<span class="hl-f">logger</span>()-&gt;<span class="hl-f">warn</span>(<span class="hl-s">'느린 쿼리'</span>, [<span class="hl-s">'ms'</span> =&gt; <span class="hl-n">1200</span>]);
<span class="hl-f">logger</span>()-&gt;<span class="hl-f">error</span>(<span class="hl-s">'결제 실패'</span>, [<span class="hl-s">'code'</span> =&gt; <span class="hl-s">'PAY_001'</span>]);

<span class="hl-c">// 최근 로그 읽기</span>
<span class="hl-v">$lines</span> = <span class="hl-f">logger</span>()-&gt;<span class="hl-f">tail</span>(<span class="hl-n">5</span>);</code></pre>

            <h6 class="mb-2">실행 결과</h6>
<?php
try {
    logger()->info('데모 페이지 로드', ['page' => 'basic', 'ip' => ip()->address()]);
    logger()->warn('데모 경고 메시지', ['demo' => true]);
    logger()->error('데모 에러 메시지', ['code' => 'DEMO_ERR']);
    $tail = logger()->tail(5);
?>
            <div class="d-flex flex-column gap-1 mb-3">
                <div class="alert alert--info mb-0"><span class="alert__message"><i class="material-icons-outlined" style="font-size:14px;">info</i> INFO — 데모 페이지 로드</span></div>
                <div class="alert alert--warning mb-0"><span class="alert__message"><i class="material-icons-outlined" style="font-size:14px;">warning</i> WARNING — 데모 경고 메시지</span></div>
                <div class="alert alert--danger mb-0"><span class="alert__message"><i class="material-icons-outlined" style="font-size:14px;">error</i> ERROR — 데모 에러 메시지</span></div>
            </div>
<?php if (!empty($tail)): ?>
            <h6 class="mt-3 mb-2">tail(5) — 최근 로그</h6>
            <pre class="demo-code"><code><?= htmlspecialchars($tail) ?></code></pre>
<?php endif; ?>
<?php } catch (\Throwable $e) { ?>
            <div class="alert alert--warning"><span class="alert__message">로그 오류: <?= htmlspecialchars($e->getMessage()) ?></span></div>
<?php } ?>
        </div>
    </div>
</div>
