<?php declare(strict_types=1);

require __DIR__ . '/../catphp/catphp.php';
config(require __DIR__ . '/../config/app.php');
errors(config('app.debug'));

// 미들웨어
router()->use(guard()->middleware());

// 웹 라우트 — SPA 셸 (include/head + 홈 콘텐츠 + include/foot)
router()->get('/', fn() => render('index'));

// 홈 프래그먼트 (SPA 내부 이동용 — head/foot 없이 콘텐츠만)
router()->get('/home', fn() => render('home'));

// 데모 프래그먼트 (SPA AJAX 로드용 — head/foot 없이 콘텐츠만)
$demoPages = ['basic', 'security', 'network', 'data', 'util', 'web', 'infra', 'modern', 'admin'];
foreach ($demoPages as $page) {
    router()->get("/demo/{$page}", fn() => render("demo/{$page}"));
}

// 도구 소개 페이지 (51개)
$toolPages = [
    'db','router','cache','log',
    'auth','csrf','encrypt','firewall','ip','guard',
    'http','rate','cors',
    'json','api',
    'valid','upload','paginate','cookie',
    'event','slug','cli','spider',
    'telegram','image','flash','perm','search','meta','geo',
    'tag','feed','text',
    'redis','mail','queue','storage','schedule','notify','hash','excel',
    'env','request','response','session','collection','migration','debug','captcha','faker','user',
    'sitemap','backup','dbview','webhook','swoole',
];
foreach ($toolPages as $tool) {
    router()->get("/tool/{$tool}", fn() => render("tools/{$tool}"));
}

// API 미들웨어 설정
$apiMiddleware = fn() => api()->cors()->rateLimit(60, 100)->guard()->apply();

// API 라우트 그룹
router()->group('/api', function() use ($apiMiddleware) {
    router()->get('/posts', function() use ($apiMiddleware) {
        $apiMiddleware();
        json()->ok(db()->table('posts')->all());
    });
    router()->post('/posts', function() use ($apiMiddleware) {
        $apiMiddleware();
        $v = valid()->rules(['title' => 'required|min:2', 'body' => 'required'])->check(input());
        if ($v->fails()) {
            json()->fail('입력값 검증 실패', 422, $v->errors());
            return;
        }
        $data = ['title' => input('title'), 'body' => input('body')];
        $id = db()->table('posts')->insert($data);
        json()->created(['id' => $id]);
    });
});

// 404 핸들러
router()->notFound(fn() => render('404'));

router()->dispatch();
