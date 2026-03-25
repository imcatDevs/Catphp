<?php declare(strict_types=1);

/** Cat\Spider 테스트 */
return function (TestRunner $t): void {
    $t->suite('Spider — pattern / regex / find / parseContent / startAt / skipAfter');

    $html = '<div class="list">'
        . '<h2>상품A</h2><span class="price">$1,500</span>'
        . '<h2>상품B</h2><span class="price">$2,300</span>'
        . '<h2>상품C</h2><span class="price">$900</span>'
        . '</div>';

    $t->test('parseContent() 토큰 패턴 기본', function () use ($t, $html) {
        $items = spider()
            ->sanitize(false)
            ->pattern('name', '<h2>', '</h2>')
            ->pattern('price', '<span class="price">', '</span>')
            ->parseContent($html);
        $t->count(3, $items);
        $t->eq('상품A', $items[0]['name']);
        $t->eq('$1,500', $items[0]['price']);
        $t->eq('상품C', $items[2]['name']);
    });

    $t->test('pattern() remove 문자 제거', function () use ($t, $html) {
        $items = spider()
            ->sanitize(false)
            ->pattern('price', '<span class="price">', '</span>', ['$', ','])
            ->parseContent($html);
        $t->eq('1500', $items[0]['price']);
    });

    $t->test('startAt() 시작점 이동', function () use ($t) {
        $content = '<header>SKIP</header><main><h2>First</h2><h2>Second</h2></main>';
        $items = spider()
            ->sanitize(false)
            ->pattern('title', '<h2>', '</h2>')
            ->startAt('<main>')
            ->parseContent($content);
        $t->count(2, $items);
        $t->eq('First', $items[0]['title']);
    });

    $t->test('startAt() 토큰 없으면 빈 배열', function () use ($t) {
        $items = spider()
            ->sanitize(false)
            ->pattern('x', '<a>', '</a>')
            ->startAt('<NOTFOUND>')
            ->parseContent('<a>test</a>');
        $t->count(0, $items);
    });

    $t->test('regex() 정규식 패턴', function () use ($t) {
        $content = 'Email: user@example.com, admin@test.org';
        $items = spider()
            ->sanitize(false)
            ->regex('email', '/[\w.+-]+@[\w-]+\.[\w.]+/')
            ->parseContent($content);
        $t->count(2, $items);
        $t->eq('user@example.com', $items[0]['email']);
        $t->eq('admin@test.org', $items[1]['email']);
    });

    $t->test('regex() 캡처 그룹', function () use ($t) {
        $content = '<strong>Alice</strong> <strong>Bob</strong>';
        $items = spider()
            ->sanitize(false)
            ->regex('name', '/<strong>(.+?)<\/strong>/', 1)
            ->parseContent($content);
        $t->count(2, $items);
        $t->eq('Alice', $items[0]['name']);
        $t->eq('Bob', $items[1]['name']);
    });

    $t->test('find() 단일 값 추출', function () use ($t) {
        $content = '<title>My Page</title><body>content</body>';
        $result = spider()->find($content, '<title>', '</title>');
        $t->eq('My Page', $result);
    });

    $t->test('find() 매칭 실패 → null', function () use ($t) {
        $t->isNull(spider()->find('no match', '<x>', '</x>'));
    });

    $t->test('find() remove 문자 제거', function () use ($t) {
        $result = spider()->sanitize(false)->find('<p>$100</p>', '<p>', '</p>', '$');
        $t->eq('100', $result);
    });

    $t->test('pattern() 빈 토큰 예외', function () use ($t) {
        $t->throws(function () {
            spider()->pattern('x', '', '</end>');
        }, \RuntimeException::class);
    });

    $t->test('parse() 패턴 없으면 예외', function () use ($t) {
        $t->throws(function () {
            spider()->parseContent('test');
        }, \RuntimeException::class);
    });

    $t->test('이뮤터블 체이닝 — 원본 불변', function () use ($t) {
        $base = spider()->sanitize(false);
        $withPattern = $base->pattern('a', '<a>', '</a>');
        // base에는 패턴이 없어야 함
        $t->throws(function () use ($base) {
            $base->parseContent('<a>test</a>');
        }, \RuntimeException::class);
    });

    $t->test('Guard 살균 기본 ON', function () use ($t) {
        $content = '<h2><script>xss</script>Title</h2>';
        $items = spider()
            ->pattern('title', '<h2>', '</h2>')
            ->parseContent($content);
        $t->count(1, $items);
        $t->notContains($items[0]['title'], '<script');
    });

    $t->test('sanitize(false) 살균 OFF', function () use ($t) {
        $content = '<h2><b>Bold</b></h2>';
        $items = spider()
            ->sanitize(false)
            ->pattern('title', '<h2>', '</h2>')
            ->parseContent($content);
        $t->eq('<b>Bold</b>', $items[0]['title']);
    });
};
