<?php declare(strict_types=1);

/**
 * CatPHP 라우트 정의 (Swoole 전용)
 *
 * Swoole 워커 시작 시 1회 로드되어 라우트 테이블을 구축한다.
 * `server.php`의 `swoole()->onBoot()` 콜백 또는 워커 부트 훅에서 require.
 *
 * 주의:
 *   - 이 파일은 FPM 진입점 `Public/index.php`와 라우트 정의를 공유한다.
 *   - 모든 라우트/미들웨어 정의는 **여기에 통합**해야 한다.
 *   - `router()->dispatch()` 호출은 포함하지 않는다 — Swoole 브리지가 요청 시 호출.
 */

defined('CATPHP') || exit('Direct access denied');

// ──────────────────────────────────────────────
// 전역 미들웨어
// ──────────────────────────────────────────────

router()->use(guard()->middleware());

// ──────────────────────────────────────────────
// 웹 라우트 (SPA 셸)
// ──────────────────────────────────────────────

router()->get('/', fn() => render('index'));
router()->get('/home', fn() => render('home'));

// 데모 프래그먼트 (SPA AJAX 로드용)
$demoPages = ['basic', 'security', 'network', 'data', 'util', 'web', 'infra', 'modern', 'admin'];
foreach ($demoPages as $page) {
    router()->get("/demo/{$page}", fn() => render("demo/{$page}"));
}

// 도구 소개 페이지 (51개)
$toolPages = [
    'db', 'router', 'cache', 'log',
    'auth', 'csrf', 'encrypt', 'firewall', 'ip', 'guard',
    'http', 'rate', 'cors',
    'json', 'api',
    'valid', 'upload', 'paginate', 'cookie',
    'event', 'slug', 'cli', 'spider',
    'telegram', 'image', 'flash', 'perm', 'search', 'meta', 'geo',
    'tag', 'feed', 'text',
    'redis', 'mail', 'queue', 'storage', 'schedule', 'notify', 'hash', 'excel',
    'env', 'request', 'response', 'session', 'collection', 'migration', 'debug', 'captcha', 'faker', 'user',
    'sitemap', 'backup', 'dbview', 'webhook', 'swoole',
];
foreach ($toolPages as $tool) {
    router()->get("/tool/{$tool}", fn() => render("tools/{$tool}"));
}

// ──────────────────────────────────────────────
// API 라우트
// ──────────────────────────────────────────────

$apiMiddleware = fn() => api()->cors()->rateLimit(60, 100)->guard()->apply();

router()->group('/api', function () use ($apiMiddleware) {
    router()->get('/posts', function () use ($apiMiddleware) {
        $apiMiddleware();
        json()->ok(db()->table('posts')->all());
    });

    router()->post('/posts', function () use ($apiMiddleware) {
        $apiMiddleware();
        $v = valid()->rules(['title' => 'required|min:2', 'body' => 'required'])->check(input());
        if ($v->fails()) {
            json()->fail('입력값 검증 실패', 422, $v->errors());
            return;
        }
        $id = db()->table('posts')->insert(['title' => input('title'), 'body' => input('body')]);
        json()->created(['id' => $id]);
    });
});

// ──────────────────────────────────────────────
// 404
// ──────────────────────────────────────────────

router()->notFound(fn() => render('404'));
