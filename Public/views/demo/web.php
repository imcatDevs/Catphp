<?php declare(strict_types=1); defined('CATPHP') || exit; ?>
<div class="demo-section">
    <h3 class="mb-1">웹 / CMS</h3>
    <p class="text-muted mb-4">Telegram · Image · Flash · Search · Meta · Geo · Tag · Feed · Text — 봇, 이미지, 알림, 검색, SEO, 지역화, 태그, 피드, 텍스트</p>

    <!-- Telegram -->
    <div class="card card--outlined mb-4" id="demo-telegram">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">send</i> Telegram</h5>
            <span class="badge badge--secondary badge--sm">Cat\Telegram</span>
        </div>
        <div class="card__body">
            <p class="mb-3">Telegram Bot API 클라이언트. 메시지/사진/파일 전송, 웹훅, 인라인 키보드</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 텍스트 메시지</span>
<span class="hl-f">telegram</span>()-&gt;<span class="hl-f">to</span>(<span class="hl-v">$chatId</span>)-&gt;<span class="hl-f">message</span>(<span class="hl-s">'배포 완료!'</span>)-&gt;<span class="hl-f">send</span>();

<span class="hl-c">// 마크다운 + 인라인 키보드</span>
<span class="hl-f">telegram</span>()-&gt;<span class="hl-f">to</span>(<span class="hl-v">$chatId</span>)
    -&gt;<span class="hl-f">markdown</span>(<span class="hl-s">'*서버 알림*\n CPU 사용률 90%'</span>)
    -&gt;<span class="hl-f">keyboard</span>([[
        [<span class="hl-s">'text'</span> =&gt; <span class="hl-s">'대시보드'</span>, <span class="hl-s">'url'</span> =&gt; <span class="hl-s">'https://dash.example.com'</span>],
    ]])
    -&gt;<span class="hl-f">send</span>();

<span class="hl-c">// 사진 전송 (URL 또는 로컬 경로)</span>
<span class="hl-f">telegram</span>()-&gt;<span class="hl-f">to</span>(<span class="hl-v">$chatId</span>)-&gt;<span class="hl-f">photo</span>(<span class="hl-s">'https://catphp.dev/og.png'</span>)-&gt;<span class="hl-f">send</span>();

<span class="hl-c">// 파일 업로드</span>
<span class="hl-f">telegram</span>()-&gt;<span class="hl-f">to</span>(<span class="hl-v">$chatId</span>)-&gt;<span class="hl-f">file</span>(<span class="hl-s">'/path/to/report.pdf'</span>)-&gt;<span class="hl-f">send</span>();</code></pre>

            <h6 class="mb-2">메시지 전송 시뮬레이션</h6>
            <div class="card card--flat" style="background:#0e1621;color:#fff;border-radius:12px;max-width:360px;">
                <div class="card__body p-3">
                    <div style="color:#7eb6e4;font-size:.8rem;font-weight:600;margin-bottom:4px;">CatPHP Bot</div>
                    <div style="background:#182533;padding:8px 12px;border-radius:0 12px 12px 12px;font-size:.85rem;margin-bottom:8px;">
                        <strong>서버 알림</strong><br>CPU 사용률 90%<br>
                        <span style="color:#7eb6e4;font-size:.75rem;">메모리: 78% | 디스크: 45%</span>
                    </div>
                    <div class="d-flex gap-1">
                        <span style="background:#2b5278;color:#fff;padding:4px 12px;border-radius:6px;font-size:.75rem;">대시보드</span>
                        <span style="background:#2b5278;color:#fff;padding:4px 12px;border-radius:6px;font-size:.75rem;">재시작</span>
                    </div>
                    <div style="text-align:right;font-size:.65rem;color:#6d7f8e;margin-top:4px;"><?= date('H:i') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image -->
    <div class="card card--outlined mb-4" id="demo-image">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">image</i> Image</h5>
            <span class="badge badge--secondary badge--sm">Cat\Image</span>
        </div>
        <div class="card__body">
            <p class="mb-3">GD 기반 이미지 처리. 리사이즈, 크롭, 워터마크, 포맷 변환, 체이닝 API</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 리사이즈 + 포맷 변환</span>
<span class="hl-f">image</span>()-&gt;<span class="hl-f">open</span>(<span class="hl-s">'photo.jpg'</span>)
    -&gt;<span class="hl-f">resize</span>(<span class="hl-n">800</span>, <span class="hl-n">600</span>)
    -&gt;<span class="hl-f">save</span>(<span class="hl-s">'thumb.webp'</span>);  <span class="hl-c">// 품질은 config('image.quality')로 설정</span>

<span class="hl-c">// 크롭 + 워터마크</span>
<span class="hl-f">image</span>()-&gt;<span class="hl-f">open</span>(<span class="hl-s">'banner.png'</span>)
    -&gt;<span class="hl-f">crop</span>(<span class="hl-n">400</span>, <span class="hl-n">400</span>)
    -&gt;<span class="hl-f">watermark</span>(<span class="hl-s">'CatPHP'</span>, <span class="hl-n">10</span>, <span class="hl-n">10</span>)
    -&gt;<span class="hl-f">save</span>(<span class="hl-s">'result.png'</span>);

<span class="hl-c">// 정보 읽기</span>
<span class="hl-v">$w</span> = <span class="hl-f">image</span>()-&gt;<span class="hl-f">open</span>(<span class="hl-s">'photo.jpg'</span>)-&gt;<span class="hl-f">width</span>();
<span class="hl-v">$h</span> = <span class="hl-f">image</span>()-&gt;<span class="hl-f">open</span>(<span class="hl-s">'photo.jpg'</span>)-&gt;<span class="hl-f">height</span>();</code></pre>

            <h6 class="mb-2">변환 파이프라인 시뮬레이션</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="card card--flat p-2 text-center" style="background:var(--bg-secondary,#f8fafc);">
                    <div style="width:120px;height:80px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:4px;margin-bottom:4px;"></div>
                    <code class="caption">원본 1920×1080</code>
                </div>
                <i class="material-icons-outlined text-muted">arrow_forward</i>
                <div class="card card--flat p-2 text-center" style="background:var(--bg-secondary,#f8fafc);">
                    <div style="width:80px;height:53px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:4px;margin-bottom:4px;"></div>
                    <code class="caption">resize 800×533</code>
                </div>
                <i class="material-icons-outlined text-muted">arrow_forward</i>
                <div class="card card--flat p-2 text-center" style="background:var(--bg-secondary,#f8fafc);">
                    <div style="width:53px;height:53px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:4px;margin-bottom:4px;position:relative;overflow:hidden;">
                        <span style="position:absolute;bottom:2px;right:2px;color:#fff;font-size:6px;opacity:.7;">CatPHP</span>
                    </div>
                    <code class="caption">crop + watermark</code>
                </div>
                <i class="material-icons-outlined text-muted">arrow_forward</i>
                <div class="card card--flat p-2 text-center" style="background:var(--bg-secondary,#f8fafc);">
                    <code class="caption">save .webp (85%)</code><br>
                    <span class="badge badge--success badge--sm mt-1">-72% 용량</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash -->
    <div class="card card--outlined mb-4" id="demo-flash">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">flash_on</i> Flash</h5>
            <span class="badge badge--secondary badge--sm">Cat\Flash</span>
        </div>
        <div class="card__body">
            <p class="mb-3">세션 기반 1회성 플래시 메시지. 리다이렉트 후 알림 표시, 레벨별 분류</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 메시지 설정 (리다이렉트 전)</span>
<span class="hl-f">flash</span>()-&gt;<span class="hl-f">success</span>(<span class="hl-s">'저장 완료!'</span>);
<span class="hl-f">flash</span>()-&gt;<span class="hl-f">error</span>(<span class="hl-s">'오류가 발생했습니다'</span>);
<span class="hl-f">flash</span>()-&gt;<span class="hl-f">warning</span>(<span class="hl-s">'주의: 비밀번호가 약합니다'</span>);
<span class="hl-f">flash</span>()-&gt;<span class="hl-f">info</span>(<span class="hl-s">'새 메시지가 있습니다'</span>);

<span class="hl-c">// 메시지 읽기 (1회 읽으면 자동 삭제)</span>
<span class="hl-v">$messages</span> = <span class="hl-f">flash</span>()-&gt;<span class="hl-f">get</span>();  <span class="hl-c">// [{type, message}, ...]</span></code></pre>

            <h6 class="mb-2">CatUI Toast 시뮬레이션</h6>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn--success btn--sm" onclick="IMCAT.toast.success('저장 완료!')">success</button>
                <button class="btn btn--danger btn--sm" onclick="IMCAT.toast.error('오류 발생!')">error</button>
                <button class="btn btn--warning btn--sm" onclick="IMCAT.toast.warning('주의! 비밀번호가 약합니다')">warning</button>
                <button class="btn btn--info btn--sm" onclick="IMCAT.toast.info('새 메시지가 3건 있습니다')">info</button>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="card card--outlined mb-4" id="demo-search">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">search</i> Search</h5>
            <span class="badge badge--secondary badge--sm">Cat\Search</span>
        </div>
        <div class="card__body">
            <p class="mb-3">DB 기반 전문 검색. LIKE 패턴, 다중 컬럼, 페이지네이션 연동, 결과 하이라이트</p>

            <pre class="demo-code mb-3"><code><span class="hl-v">$results</span> = <span class="hl-f">search</span>()-&gt;<span class="hl-f">in</span>(<span class="hl-s">'posts'</span>, [<span class="hl-s">'title'</span>, <span class="hl-s">'content'</span>])
    -&gt;<span class="hl-f">query</span>(<span class="hl-s">'CatPHP 프레임워크'</span>)
    -&gt;<span class="hl-f">limit</span>(<span class="hl-n">10</span>)
    -&gt;<span class="hl-f">offset</span>(<span class="hl-n">0</span>)
    -&gt;<span class="hl-f">results</span>();

<span class="hl-c">// 검색어 하이라이트</span>
<span class="hl-v">$highlighted</span> = <span class="hl-f">search</span>()-&gt;<span class="hl-f">query</span>(<span class="hl-s">'CatPHP'</span>)-&gt;<span class="hl-f">highlight</span>(<span class="hl-v">$text</span>);</code></pre>

            <h6 class="mb-2">검색 시뮬레이션</h6>
            <div class="d-flex gap-2 mb-3">
                <input type="text" class="form-control" id="searchInput" placeholder="검색어 입력.." value="CatPHP" style="max-width:300px;">
                <button class="btn btn--primary btn--sm" id="searchBtn"><i class="material-icons-outlined" style="font-size:14px;">search</i></button>
            </div>
            <div id="searchResults">
                <div class="list list--divided">
                    <div class="list__item"><div class="list__content"><span class="list__title"><mark>CatPHP</mark> v1.0 출시</span><span class="list__subtitle">PHP 8.2+ 전용 경량 프레임워크 <mark>CatPHP</mark>의 첫 릴리즈입니다.</span></div></div>
                    <div class="list__item"><div class="list__content"><span class="list__title"><mark>CatPHP</mark> 보안 도구 가이드</span><span class="list__subtitle">Auth, Encrypt, Guard 등 <mark>CatPHP</mark>의 보안 도구를 소개합니다.</span></div></div>
                    <div class="list__item"><div class="list__content"><span class="list__title"><mark>CatPHP</mark> vs 다른 프레임워크</span><span class="list__subtitle">Laravel, Slim과 비교한 <mark>CatPHP</mark>의 장단점 분석.</span></div></div>
                </div>
            </div>
            <script>
            document.getElementById('searchBtn')?.addEventListener('click', () => {
                IMCAT.toast.info('검색 결과 3건 (시뮬레이션)');
            });
            </script>
        </div>
    </div>

    <!-- Meta -->
    <div class="card card--outlined mb-4" id="demo-meta">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">sell</i> Meta</h5>
            <span class="badge badge--secondary badge--sm">Cat\Meta</span>
        </div>
        <div class="card__body">
            <p class="mb-3">SEO 메타 태그 빌더. title, description, Open Graph, Twitter Card, JSON-LD 구조화 데이터</p>

            <pre class="demo-code mb-3"><code><span class="hl-f">meta</span>()-&gt;<span class="hl-f">title</span>(<span class="hl-s">'CatPHP 블로그'</span>)
     -&gt;<span class="hl-f">description</span>(<span class="hl-s">'PHP 8.2+ 경량 프레임워크'</span>)
     -&gt;<span class="hl-f">og</span>(<span class="hl-s">'type'</span>, <span class="hl-s">'website'</span>)
     -&gt;<span class="hl-f">og</span>(<span class="hl-s">'image'</span>, <span class="hl-s">'https://catphp.dev/og.png'</span>)
     -&gt;<span class="hl-f">twitter</span>(<span class="hl-s">'card'</span>, <span class="hl-s">'summary_large_image'</span>);
<span class="hl-k">echo</span> <span class="hl-f">meta</span>()-&gt;<span class="hl-f">render</span>();</code></pre>

            <h6 class="mb-2">실행 결과</h6>
<?php
try {
    meta()->reset()
        ->title('CatPHP 데모')
        ->description('PHP 8.2+ 전용 경량 프레임워크 데모')
        ->og('type', 'website')
        ->og('url', 'https://catphp.dev/demo');
    $metaHtml = meta()->render();
?>
            <pre class="demo-code"><code><?= htmlspecialchars($metaHtml) ?></code></pre>
<?php } catch (\Throwable $e) { ?>
            <div class="alert alert--warning"><span class="alert__message">Meta 오류: <?= htmlspecialchars($e->getMessage()) ?></span></div>
<?php } ?>
        </div>
    </div>

    <!-- Geo -->
    <div class="card card--outlined mb-4" id="demo-geo">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">language</i> Geo</h5>
            <span class="badge badge--secondary badge--sm">Cat\Geo</span>
        </div>
        <div class="card__body">
            <p class="mb-3">IP 기반 지역 감지, 로케일 관리, 다국어 번역 (lang/ 디렉토리)</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 로케일 감지 + 설정</span>
<span class="hl-v">$locale</span> = <span class="hl-f">geo</span>()-&gt;<span class="hl-f">getLocale</span>();    <span class="hl-c">// 'ko'</span>
<span class="hl-f">geo</span>()-&gt;<span class="hl-f">locale</span>(<span class="hl-s">'en'</span>);              <span class="hl-c">// 변경</span>

<span class="hl-c">// 번역</span>
<span class="hl-f">trans</span>(<span class="hl-s">'welcome'</span>);                  <span class="hl-c">// '환영합니다'</span>
<span class="hl-f">trans</span>(<span class="hl-s">'error.not_found'</span>);          <span class="hl-c">// '페이지를 찾을 수 없습니다'</span></code></pre>

            <h6 class="mb-2">실행 결과</h6>
<?php
try {
    $locale = geo()->getLocale();
?>
            <div class="alert alert--info mb-2"><span class="alert__message"><strong>getLocale()</strong> → <code><?= htmlspecialchars($locale) ?></code></span></div>
<?php
    $langTests = ['welcome', 'error.not_found', 'app.name'];
    foreach ($langTests as $key):
        $translated = trans($key);
?>
            <div class="alert alert--info mb-1"><span class="alert__message"><strong>trans('<?= $key ?>')</strong> → <code><?= htmlspecialchars($translated) ?></code></span></div>
<?php endforeach; ?>
<?php } catch (\Throwable $e) { ?>
            <div class="alert alert--warning"><span class="alert__message">Geo 오류: <?= htmlspecialchars($e->getMessage()) ?></span></div>
<?php } ?>
        </div>
    </div>

    <!-- Tag -->
    <div class="card card--outlined mb-4" id="demo-tag">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">label</i> Tag</h5>
            <span class="badge badge--secondary badge--sm">Cat\Tag</span>
        </div>
        <div class="card__body">
            <p class="mb-3">다형성 태그 시스템. attach/detach/sync, 인기 태그, 태그 클라우드</p>

            <pre class="demo-code mb-3"><code><span class="hl-c">// 태그 부착</span>
<span class="hl-f">tag</span>()-&gt;<span class="hl-f">attach</span>(<span class="hl-s">'posts'</span>, <span class="hl-v">$id</span>, [<span class="hl-s">'PHP'</span>, <span class="hl-s">'CatPHP'</span>, <span class="hl-s">'프레임워크'</span>]);

<span class="hl-c">// 동기화 (기존 → 새 목록으로 교체)</span>
<span class="hl-f">tag</span>()-&gt;<span class="hl-f">sync</span>(<span class="hl-s">'posts'</span>, <span class="hl-v">$id</span>, [<span class="hl-s">'PHP'</span>, <span class="hl-s">'보안'</span>]);

<span class="hl-c">// 인기 태그 (상위 10개)</span>
<span class="hl-v">$popular</span> = <span class="hl-f">tag</span>()-&gt;<span class="hl-f">popular</span>(<span class="hl-n">10</span>);

<span class="hl-c">// 태그로 콘텐츠 조회</span>
<span class="hl-v">$posts</span> = <span class="hl-f">tag</span>()-&gt;<span class="hl-f">find</span>(<span class="hl-s">'posts'</span>, <span class="hl-s">'PHP'</span>);</code></pre>

            <h6 class="mb-2">태그 클라우드 시뮬레이션</h6>
            <div class="d-flex flex-wrap gap-1">
<?php
$tagSamples = [
    ['PHP', 'primary', 42], ['CatPHP', 'info', 38], ['프레임워크', 'success', 25],
    ['보안', 'danger', 18], ['API', 'warning', 15], ['캐시', 'secondary', 12],
    ['JWT', 'dark', 10], ['ORM', 'primary', 8], ['REST', 'info', 7],
    ['SEO', 'success', 5], ['WebSocket', 'warning', 3], ['Redis', 'danger', 20],
    ['Docker', 'info', 14], ['테스트', 'success', 9],
];
foreach ($tagSamples as [$name, $color, $count]): ?>
                <span class="badge badge--soft badge--<?= $color ?>" style="font-size:<?= max(0.7, min(1.2, 0.6 + $count / 40)) ?>rem;"><?= $name ?> <small>(<?= $count ?>)</small></span>
<?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Feed -->
    <div class="card card--outlined mb-4" id="demo-feed">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">rss_feed</i> Feed</h5>
            <span class="badge badge--secondary badge--sm">Cat\Feed</span>
        </div>
        <div class="card__body">
            <p class="mb-3">RSS 2.0 / Atom 피드 생성. 블로그, 뉴스 등을 XML 피드로 발행</p>

            <pre class="demo-code mb-3"><code><span class="hl-v">$xml</span> = <span class="hl-f">feed</span>()-&gt;<span class="hl-f">title</span>(<span class="hl-s">'CatPHP 블로그'</span>)
    -&gt;<span class="hl-f">link</span>(<span class="hl-s">'https://catphp.dev'</span>)
    -&gt;<span class="hl-f">description</span>(<span class="hl-s">'PHP 프레임워크 소식'</span>)
    -&gt;<span class="hl-f">item</span>([
        <span class="hl-s">'title'</span>   =&gt; <span class="hl-s">'CatPHP v1.0 출시'</span>,
        <span class="hl-s">'link'</span>    =&gt; <span class="hl-s">'https://catphp.dev/posts/v1'</span>,
        <span class="hl-s">'pubDate'</span> =&gt; <span class="hl-f">date</span>(<span class="hl-s">'r'</span>),
    ])
    -&gt;<span class="hl-f">render</span>();</code></pre>

            <h6 class="mb-2">RSS 출력 미리보기</h6>
            <pre class="demo-code" style="font-size:.75rem;"><code>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;rss version="2.0"&gt;
  &lt;channel&gt;
    &lt;title&gt;CatPHP 블로그&lt;/title&gt;
    &lt;link&gt;https://catphp.dev&lt;/link&gt;
    &lt;description&gt;PHP 프레임워크 소식&lt;/description&gt;
    &lt;item&gt;
      &lt;title&gt;CatPHP v1.0 출시&lt;/title&gt;
      &lt;link&gt;https://catphp.dev/posts/v1-release&lt;/link&gt;
      &lt;guid isPermaLink="true"&gt;https://catphp.dev/posts/v1-release&lt;/guid&gt;
      &lt;pubDate&gt;<?= date('r') ?>&lt;/pubDate&gt;
    &lt;/item&gt;
    &lt;item&gt;
      &lt;title&gt;보안 도구 가이드&lt;/title&gt;
      &lt;link&gt;https://catphp.dev/posts/security-guide&lt;/link&gt;
      &lt;guid isPermaLink="true"&gt;https://catphp.dev/posts/security-guide&lt;/guid&gt;
      &lt;pubDate&gt;<?= date('r', strtotime('-1 day')) ?>&lt;/pubDate&gt;
    &lt;/item&gt;
  &lt;/channel&gt;
&lt;/rss&gt;</code></pre>
        </div>
    </div>

    <!-- Text -->
    <div class="card card--outlined mb-4" id="demo-text">
        <div class="card__header d-flex justify-content-between align-items-center">
            <h5 class="card__title mb-0"><i class="material-icons-outlined" style="font-size:18px;vertical-align:middle;">text_fields</i> Text</h5>
            <span class="badge badge--secondary badge--sm">Cat\Text</span>
        </div>
        <div class="card__body">
            <p class="mb-3">텍스트 유틸리티. 읽기 시간, 요약(excerpt), 단어 수, 하이라이트, 마스킹</p>

            <pre class="demo-code mb-3"><code><span class="hl-v">$time</span>    = <span class="hl-f">text</span>()-&gt;<span class="hl-f">readingTime</span>(<span class="hl-v">$content</span>);  <span class="hl-c">// 3 (분)</span>
<span class="hl-v">$summary</span> = <span class="hl-f">text</span>()-&gt;<span class="hl-f">excerpt</span>(<span class="hl-v">$content</span>, <span class="hl-n">100</span>); <span class="hl-c">// 100자 요약</span>
<span class="hl-v">$words</span>   = <span class="hl-f">text</span>()-&gt;<span class="hl-f">wordCount</span>(<span class="hl-v">$content</span>);   <span class="hl-c">// 42</span>
<span class="hl-v">$hl</span>      = <span class="hl-f">text</span>()-&gt;<span class="hl-f">highlight</span>(<span class="hl-v">$content</span>, <span class="hl-s">'PHP'</span>);</code></pre>

            <h6 class="mb-2">실행 결과</h6>
<?php
try {
    $sample = 'CatPHP는 PHP 8.2 이상을 위한 경량 프레임워크입니다. 코어 파일 1개와 require 1회로 부팅되며, 51개의 도구를 제공합니다. PDO 기반 쿼리 빌더, Argon2id 해싱, Sodium 암호화, JWT 토큰, 파일 캐시, 이벤트 시스템 등 웹 개발에 필요한 핵심 기능을 모두 포함합니다. CatUI와 함께 사용하면 SPA 기반 관리자 패널을 빠르게 구축할 수 있습니다.';
    $readTime = text()->readingTime($sample);
    $excerpt = text()->excerpt($sample, 50);
    $wordCount = text()->wordCount($sample);
?>
            <div class="card card--flat mb-3" style="background:var(--bg-secondary,#f8fafc);">
                <div class="card__body p-2">
                    <p class="caption text-muted mb-1">원문</p>
                    <p class="mb-0" style="font-size:.85rem;"><?= htmlspecialchars($sample) ?></p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge badge--soft badge--primary">읽기 시간: <?= $readTime ?></span>
                <span class="badge badge--soft badge--info">단어 수: <?= $wordCount ?></span>
            </div>
            <div class="alert alert--info"><span class="alert__message"><strong>excerpt(50)</strong> → <code><?= htmlspecialchars($excerpt) ?></code></span></div>
<?php } catch (\Throwable $e) { ?>
            <div class="alert alert--warning"><span class="alert__message">Text 오류: <?= htmlspecialchars($e->getMessage()) ?></span></div>
<?php } ?>
        </div>
    </div>
</div>
