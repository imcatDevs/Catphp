<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <h3 class="mb-1">유틸리티</h3>
    <p class="text-muted mb-4">Event · Slug · Cli · Spider · Debug — 이벤트, 슬러그, CLI, 크롤러, 디버깅</p>

    <!-- Event -->
    <div class="card card--outlined mb-4" id="demo-event">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">bolt</i> Event</h5>
            <span class="badge badge--warning badge--sm">Cat\Event</span>
        </div>
        <div class="card__body">
            <p class="mb-3">Pub/Sub 이벤트 시스템. on(), once(), emit(), off(), 리스너 ID 기반 제거</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 리스너 등록</span>
<span class="hl-v">$id</span> = <span class="hl-f">event</span>()-&gt;<span class="hl-f">on</span>(<span class="hl-s">'user.created'</span>, <span class="hl-k">fn</span>(<span class="hl-v">$d</span>) =&gt; <span class="hl-f">mailer</span>()-&gt;...);
<span class="hl-f">event</span>()-&gt;<span class="hl-f">once</span>(<span class="hl-s">'app.boot'</span>, <span class="hl-k">fn</span>() =&gt; ...);  <span class="hl-c">// 1회만</span>

<span class="hl-c">// 이벤트 발행</span>
<span class="hl-f">event</span>()-&gt;<span class="hl-f">emit</span>(<span class="hl-s">'user.created'</span>, [<span class="hl-s">'id'</span> =&gt; <span class="hl-n">1</span>, <span class="hl-s">'name'</span> =&gt; <span class="hl-s">'Cat'</span>]);

<span class="hl-c">// 리스너 제거</span>
<span class="hl-f">event</span>()-&gt;<span class="hl-f">off</span>(<span class="hl-s">'user.created'</span>, <span class="hl-v">$id</span>);</code></pre>

            <h6 class="mb-2">실행 결과</h6>
<?php
try {
    $log = [];
    event()->on('demo.ping', function (array $data) use (&$log) {
        $log[] = 'Listener A: ' . ($data['msg'] ?? '');
    });
    event()->on('demo.ping', function (array $data) use (&$log) {
        $log[] = 'Listener B: strtoupper → ' . strtoupper($data['msg'] ?? '');
    });
    event()->once('demo.ping', function (array $data) use (&$log) {
        $log[] = 'Once Listener: 1회만 실행';
    });
    event()->emit('demo.ping', ['msg' => 'Hello Event!']);
?>
            <div class="alert alert--success mb-2"><span class="alert__message"><strong>emit('demo.ping')</strong> → 리스너 <?= count($log) ?>개 실행</span></div>
<?php foreach ($log as $entry): ?>
            <div class="alert alert--info mb-1"><span class="alert__message"><code><?= htmlspecialchars($entry) ?></code></span></div>
<?php endforeach; ?>
<?php } catch (\Throwable $e) { ?>
            <div class="alert alert--warning"><span class="alert__message">Event 오류: <?= htmlspecialchars($e->getMessage()) ?></span></div>
<?php } ?>
        </div>
    </div>

    <!-- Slug -->
    <div class="card card--outlined mb-4" id="demo-slug">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">link</i> Slug</h5>
            <span class="badge badge--warning badge--sm">Cat\Slug</span>
        </div>
        <div class="card__body">
            <p class="mb-3">URL 친화적 슬러그 생성. 한국어/일본어/중국어 음역, 커스텀 구분자, 중복 방지</p>

            <pre class="demo-code mb-3"><code><span class="hl-f">slug</span>()-&gt;<span class="hl-f">make</span>(<span class="hl-s">'Hello World!'</span>);           <span class="hl-c">// hello-world</span>
<span class="hl-f">slug</span>()-&gt;<span class="hl-f">make</span>(<span class="hl-s">'안녕하세요 CatPHP'</span>);      <span class="hl-c">// annyeonghaseyo-catphp</span>
<span class="hl-f">slug</span>()-&gt;<span class="hl-f">make</span>(<span class="hl-s">'제목'</span>, <span class="hl-s">'_'</span>);             <span class="hl-c">// jemok (구분자 변경)</span></code></pre>

            <h6 class="mb-2">변환 결과</h6>
<?php
try {
    $tests = [
        'Hello World!',
        '안녕하세요 CatPHP 프레임워크',
        'PHP 8.2 — New Features & 변경사항',
        '   Extra   Spaces   &   Symbols!!!  ',
        '2024년 1월 신규 기능 릴리즈',
        'café résumé naïve',
    ];
?>
            <table class="table table--sm table--bordered">
                <thead><tr><th>입력</th><th>슬러그</th></tr></thead>
                <tbody>
<?php foreach ($tests as $test): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($test) ?></code></td>
                        <td><code class="text-primary"><?= htmlspecialchars(slug()->make($test)) ?></code></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
<?php } catch (\Throwable $e) { ?>
            <div class="alert alert--warning"><span class="alert__message">Slug 오류: <?= htmlspecialchars($e->getMessage()) ?></span></div>
<?php } ?>
        </div>
    </div>

    <!-- Cli -->
    <div class="card card--outlined mb-4" id="demo-cli">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">terminal</i> Cli</h5>
            <span class="badge badge--warning badge--sm">Cat\Cli</span>
        </div>
        <div class="card__body">
            <p class="mb-3">CLI 명령어 프레임워크. 명령어 등록, 옵션/인수 파싱, 색상 출력, 프롬프트</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 명령어 등록 (name, description, handler)</span>
<span class="hl-f">cli</span>()-&gt;<span class="hl-f">command</span>(<span class="hl-s">'greet'</span>, <span class="hl-s">'Hello 인사'</span>, <span class="hl-k">function</span>() {
    <span class="hl-v">$name</span> = <span class="hl-f">cli</span>()-&gt;<span class="hl-f">arg</span>(<span class="hl-n">0</span>) ?? <span class="hl-s">'World'</span>;
    <span class="hl-f">cli</span>()-&gt;<span class="hl-f">success</span>(<span class="hl-s">"Hello, {<span class="hl-v">$name</span>}!"</span>);
});

<span class="hl-c">// 출력 헬퍼</span>
<span class="hl-f">cli</span>()-&gt;<span class="hl-f">info</span>(<span class="hl-s">'정보 메시지'</span>);
<span class="hl-f">cli</span>()-&gt;<span class="hl-f">success</span>(<span class="hl-s">'성공!'</span>);
<span class="hl-f">cli</span>()-&gt;<span class="hl-f">error</span>(<span class="hl-s">'오류 발생'</span>);
<span class="hl-f">cli</span>()-&gt;<span class="hl-f">table</span>([<span class="hl-s">'이름'</span>, <span class="hl-s">'역할'</span>], <span class="hl-v">$rows</span>);</code></pre>

            <h6 class="mb-2">내장 CLI 명령어</h6>
            <table class="table table--sm table--bordered">
                <thead><tr><th>명령어</th><th>설명</th></tr></thead>
                <tbody>
                    <tr><td><code>php cli.php config:init</code></td><td>모든 도구의 기본 config/app.php 생성</td></tr>
                    <tr><td><code>php cli.php key:generate</code></td><td>보안 키 자동 생성 (app.key, auth.secret, encrypt.key)</td></tr>
                    <tr><td><code>php cli.php setup</code></td><td>인터랙티브 config 설정</td></tr>
                    <tr><td><code>php cli.php check:env</code></td><td>최적화 환경 진단</td></tr>
                    <tr><td><code>php cli.php serve</code></td><td>개발 서버 시작 (127.0.0.1:8000)</td></tr>
                    <tr><td><code>php cli.php cache:clear</code></td><td>캐시 전체 삭제</td></tr>
                    <tr><td><code>php cli.php queue:work</code></td><td>큐 워커 실행</td></tr>
                    <tr><td><code>php cli.php schedule:run</code></td><td>스케줄 태스크 실행</td></tr>
                    <tr><td><code>php cli.php migrate</code></td><td>마이그레이션 실행</td></tr>
                    <tr><td><code>php cli.php migrate:rollback</code></td><td>마이그레이션 롤백</td></tr>
                    <tr><td><code>php cli.php firewall:ban {ip}</code></td><td>IP 차단</td></tr>
                </tbody>
            </table>

            <h6 class="mt-3 mb-2">터미널 출력 시뮬레이션</h6>
            <pre class="demo-code" style="background:#0c0c0c;"><code><span style="color:#6ee7b7;">$</span> php cli.php serve
<span style="color:#7dd3fc;">[INFO]</span> Development server started: http://0.0.0.0:8000
<span style="color:#7dd3fc;">[INFO]</span> Press Ctrl+C to stop.

<span style="color:#6ee7b7;">$</span> php cli.php cache:clear
<span style="color:#86efac;">[SUCCESS]</span> Cache cleared: 42 files deleted.

<span style="color:#6ee7b7;">$</span> php cli.php migrate:status
<span style="color:#fde68a;">+----+------------------------------------+-------+</span>
<span style="color:#fde68a;">| #  | Migration                          | Batch |</span>
<span style="color:#fde68a;">+----+------------------------------------+-------+</span>
<span style="color:#fde68a;">| 1  | 2024_01_15_create_users_table      |   1   |</span>
<span style="color:#fde68a;">| 2  | 2024_01_16_create_posts_table      |   1   |</span>
<span style="color:#fde68a;">+----+------------------------------------+-------+</span></code></pre>
        </div>
    </div>

    <!-- Spider -->
    <div class="card card--outlined mb-4" id="demo-spider">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">bug_report</i> Spider</h5>
            <span class="badge badge--warning badge--sm">Cat\Spider</span>
        </div>
        <div class="card__body">
            <p class="mb-3">토큰 기반 콘텐츠 파서. pattern/regex로 구조화 데이터 추출, 페이지네이션, 콜백 처리</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 토큰 패턴 매칭 (시작 태그, 종료 태그)</span>
<span class="hl-v">$items</span> = <span class="hl-f">spider</span>()
    -&gt;<span class="hl-f">pattern</span>(<span class="hl-s">'title'</span>, <span class="hl-s">'&lt;h2&gt;'</span>, <span class="hl-s">'&lt;/h2&gt;'</span>)
    -&gt;<span class="hl-f">pattern</span>(<span class="hl-s">'price'</span>, <span class="hl-s">'&lt;span class="price"&gt;'</span>, <span class="hl-s">'&lt;/span&gt;'</span>, [<span class="hl-s">'$'</span>, <span class="hl-s">','</span>])
    -&gt;<span class="hl-f">startAt</span>(<span class="hl-s">'&lt;div class="list"&gt;'</span>)
    -&gt;<span class="hl-f">parse</span>(<span class="hl-s">'https://example.com/products'</span>);

<span class="hl-c">// 정규식 패턴</span>
<span class="hl-v">$emails</span> = <span class="hl-f">spider</span>()
    -&gt;<span class="hl-f">regex</span>(<span class="hl-s">'email'</span>, <span class="hl-s">'/[\w.+-]+@[\w-]+\.[\w.]+/'</span>)
    -&gt;<span class="hl-f">parse</span>(<span class="hl-s">'https://example.com/contacts'</span>);

<span class="hl-c">// 페이지네이션 + 콜백</span>
<span class="hl-f">spider</span>()
    -&gt;<span class="hl-f">pattern</span>(<span class="hl-s">'title'</span>, <span class="hl-s">'&lt;h2&gt;'</span>, <span class="hl-s">'&lt;/h2&gt;'</span>)
    -&gt;<span class="hl-f">paginate</span>(<span class="hl-s">'page'</span>, <span class="hl-n">1</span>, <span class="hl-n">10</span>)
    -&gt;<span class="hl-f">delay</span>(<span class="hl-n">2</span>)
    -&gt;<span class="hl-f">each</span>(<span class="hl-k">function</span>(<span class="hl-k">array</span> <span class="hl-v">$pageItems</span>, <span class="hl-k">int</span> <span class="hl-v">$pageNo</span>) {
        <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'items'</span>)-&gt;<span class="hl-f">insert</span>(<span class="hl-v">$pageItems</span>);
    })
    -&gt;<span class="hl-f">parse</span>(<span class="hl-s">'https://example.com/list'</span>);</code></pre>

            <h6 class="mb-2">파싱 결과 시뮬레이션</h6>
            <div class="card card--flat" style="background:var(--bg-secondary,#f8fafc);">
                <div class="card__body p-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge badge--success badge--sm">200</span>
                        <code>https://example.com/products?page=1</code>
                    </div>
                    <table class="table table--sm mb-0">
                        <thead><tr><th>#</th><th>title</th><th>price</th></tr></thead>
                        <tbody>
                            <tr><td>1</td><td>PHP 8.2 입문서</td><td>29000</td></tr>
                            <tr><td>2</td><td>웹 보안 가이드</td><td>35000</td></tr>
                            <tr><td>3</td><td>CatPHP 실전 프로젝트</td><td>42000</td></tr>
                        </tbody>
                    </table>
                    <div class="d-flex gap-1 mt-2">
                        <span class="badge badge--soft badge--info badge--sm">pattern('title')</span>
                        <span class="badge badge--soft badge--info badge--sm">pattern('price', strip: ['$',','])</span>
                        <span class="badge badge--soft badge--warning badge--sm">3 items × 10 pages</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug -->
    <div class="card card--outlined mb-4" id="demo-debug">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">bug_report</i> Debug</h5>
            <span class="badge badge--warning badge--sm">Cat\Debug</span>
        </div>
        <div class="card__body">
            <p class="mb-3">개발 디버깅 도구. dd()/dump(), 타이머, 메모리 측정, 디버그 바</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 변수 덤프</span>
<span class="hl-f">dump</span>(<span class="hl-v">$user</span>, <span class="hl-v">$request</span>);   <span class="hl-c">// 출력, 계속 실행</span>
<span class="hl-f">dd</span>(<span class="hl-v">$data</span>);               <span class="hl-c">// 출력 후 즉시 종료</span>

<span class="hl-c">// 실행 시간 측정</span>
<span class="hl-f">debug</span>()-&gt;<span class="hl-f">timer</span>(<span class="hl-s">'query'</span>);
<span class="hl-v">$result</span> = <span class="hl-f">db</span>()-&gt;<span class="hl-f">table</span>(<span class="hl-s">'users'</span>)-&gt;<span class="hl-f">all</span>();
<span class="hl-v">$ms</span> = <span class="hl-f">debug</span>()-&gt;<span class="hl-f">timerEnd</span>(<span class="hl-s">'query'</span>);  <span class="hl-c">// 12.5 ms</span>

<span class="hl-c">// measure (콜백 자동 측정)</span>
<span class="hl-v">$result</span> = <span class="hl-f">debug</span>()-&gt;<span class="hl-f">measure</span>(<span class="hl-s">'heavy'</span>, <span class="hl-k">fn</span>() =&gt; ...);

<span class="hl-c">// 메모리 + 디버그 바</span>
<span class="hl-f">debug</span>()-&gt;<span class="hl-f">memory</span>();       <span class="hl-c">// "2.4 MB"</span>
<span class="hl-f">debug</span>()-&gt;<span class="hl-f">peakMemory</span>();   <span class="hl-c">// "4.1 MB"</span>
<span class="hl-k">echo</span> <span class="hl-f">debug</span>()-&gt;<span class="hl-f">bar</span>();     <span class="hl-c">// HTML 디버그 바</span></code></pre>

            <h6 class="mb-2">dd() 출력 시뮬레이션</h6>
            <pre class="demo-code mb-3" style="background:#1a1a2e;"><code><span style="color:#fbbf24;">array</span><span style="color:#94a3b8;">(3)</span> {
  [<span style="color:#7dd3fc;">"name"</span>] =&gt; <span style="color:#86efac;">string</span><span style="color:#94a3b8;">(9)</span> <span style="color:#fde68a;">"김개발"</span>
  [<span style="color:#7dd3fc;">"email"</span>] =&gt; <span style="color:#86efac;">string</span><span style="color:#94a3b8;">(14)</span> <span style="color:#fde68a;">"kim@catphp.dev"</span>
  [<span style="color:#7dd3fc;">"roles"</span>] =&gt; <span style="color:#fbbf24;">array</span><span style="color:#94a3b8;">(2)</span> {
    [<span style="color:#c084fc;">0</span>] =&gt; <span style="color:#86efac;">string</span><span style="color:#94a3b8;">(5)</span> <span style="color:#fde68a;">"admin"</span>
    [<span style="color:#c084fc;">1</span>] =&gt; <span style="color:#86efac;">string</span><span style="color:#94a3b8;">(6)</span> <span style="color:#fde68a;">"editor"</span>
  }
}</code></pre>

            <h6 class="mb-2">디버그 바 시뮬레이션</h6>
            <div class="d-flex flex-wrap gap-2" style="background:#1e293b;padding:.75rem 1rem;border-radius:8px;">
                <span class="badge badge--soft badge--info badge--sm">⏱ <?= number_format(microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)), 1) ?>ms</span>
                <span class="badge badge--soft badge--success badge--sm">💾 <?= round(memory_get_usage() / 1024 / 1024, 1) ?> MB</span>
                <span class="badge badge--soft badge--warning badge--sm">📊 Peak: <?= round(memory_get_peak_usage() / 1024 / 1024, 1) ?> MB</span>
                <span class="badge badge--soft badge--primary badge--sm">PHP <?= PHP_VERSION ?></span>
            </div>
            <div class="alert alert--warning mt-3"><span class="alert__message"><strong>프로덕션:</strong> <code>app.debug = false</code>이면 bar()는 빈 문자열, dd()는 일반 500 에러를 반환합니다.</span></div>
        </div>
    </div>
</div>
