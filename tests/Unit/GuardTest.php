<?php declare(strict_types=1);

/** Cat\Guard 테스트 */
return function (TestRunner $t): void {
    $t->suite('Guard — path / xss / clean / cleanArray / header / filename');

    // ── path() ──
    $t->test('path() ../ 트래버설 제거', function () use ($t) {
        $t->notContains(guard()->path('../../etc/passwd'), '..');
    });

    $t->test('path() URL 인코딩 트래버설 제거', function () use ($t) {
        $result = guard()->path('%2e%2e/etc/passwd');
        $t->notContains($result, '..');
    });

    $t->test('path() null 바이트 제거', function () use ($t) {
        $result = guard()->path("file.php\0.jpg");
        $t->notContains($result, "\0");
    });

    // ── xss() ──
    $t->test('xss() script 태그 제거', function () use ($t) {
        $result = guard()->xss('<script>alert("xss")</script>');
        $t->notContains($result, '<script');
        $t->notContains($result, '</script>');
    });

    $t->test('xss() 이벤트 핸들러 제거', function () use ($t) {
        $result = guard()->xss('<div onmouseover="alert(1)">test</div>');
        $t->notContains($result, 'onmouseover');
    });

    $t->test('xss() javascript: 프로토콜 제거', function () use ($t) {
        $result = guard()->xss('javascript:alert(1)');
        $t->notContains(strtolower($result), 'javascript:');
    });

    $t->test('xss() iframe 제거', function () use ($t) {
        $result = guard()->xss('<iframe src="evil.com"></iframe>');
        $t->notContains($result, '<iframe');
    });

    $t->test('xss() svg/onload 바이패스 방어', function () use ($t) {
        $result = guard()->xss('<svg/onload=alert(1)>');
        $t->notContains(strtolower($result), 'onload');
    });

    // ── clean() ──
    $t->test('clean() 제어문자 제거', function () use ($t) {
        $result = guard()->clean("hello\x00\x01\x02world");
        $t->eq('helloworld', $result);
    });

    $t->test('clean() CRLF 제거', function () use ($t) {
        $result = guard()->clean("line1\r\nline2");
        $t->notContains($result, "\r");
    });

    // ── cleanArray() ──
    $t->test('cleanArray() 재귀 살균', function () use ($t) {
        $data = ['name' => '<script>xss</script>', 'nested' => ['val' => '<img onerror=x>']];
        $result = guard()->cleanArray($data);
        $t->notContains($result['name'], '<script');
        $t->notContains($result['nested']['val'], 'onerror');
    });

    $t->test('cleanArray() except 키 제외', function () use ($t) {
        $data = ['safe' => 'clean', 'raw' => '<b>bold</b>'];
        $result = guard()->cleanArray($data, ['raw']);
        $t->eq('<b>bold</b>', $result['raw']);
    });

    // ── header() ──
    $t->test('header() 개행 제거', function () use ($t) {
        $result = guard()->header("Content-Type\r\nX-Injected: evil");
        $t->notContains($result, "\r");
        $t->notContains($result, "\n");
    });

    // ── filename() ──
    $t->test('filename() PHP 확장자 차단', function () use ($t) {
        $result = guard()->filename('evil.php');
        $t->contains($result, 'blocked');
    });

    $t->test('filename() 이중 확장자 방어', function () use ($t) {
        $result = guard()->filename('image.php.jpg');
        $t->contains($result, 'blocked');
    });

    $t->test('filename() null 바이트 제거', function () use ($t) {
        $result = guard()->filename("file.php\0.jpg");
        $t->notContains($result, "\0");
    });

    $t->test('filename() 안전한 파일명 통과', function () use ($t) {
        $result = guard()->filename('photo-2024.jpg');
        $t->eq('photo-2024.jpg', $result);
    });

    $t->test('filename() 빈 입력 → unnamed', function () use ($t) {
        $result = guard()->filename('...');
        $t->eq('unnamed', $result);
    });

    // ── XSS 회귀테스트 — 최신 페이로드 ──

    $t->test('xss() noscript 태그 제거', function () use ($t) {
        $result = guard()->xss('<noscript><img src=x onerror=alert(1)></noscript>');
        $t->notContains(strtolower($result), '<noscript');
    });

    $t->test('xss() template 태그 제거', function () use ($t) {
        $result = guard()->xss('<template><img src=x onerror=alert(1)></template>');
        $t->notContains(strtolower($result), '<template');
    });

    $t->test('xss() style 태그 + expression 제거', function () use ($t) {
        $result = guard()->xss('<style>body{background:url(javascript:alert(1))}</style>');
        $t->notContains(strtolower($result), '<style');
    });

    $t->test('xss() data: URI 제거', function () use ($t) {
        $result = guard()->xss('<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">click</a>');
        $t->notContains(strtolower($result), 'data:');
    });

    $t->test('xss() CSS @import 방어', function () use ($t) {
        $result = guard()->xss('@import url("https://evil.com/xss.css");');
        $t->notContains(strtolower($result), '@import');
    });

    $t->test('xss() CSS behavior 방어', function () use ($t) {
        $result = guard()->xss('behavior: url(evil.htc)');
        $t->notContains(strtolower($result), 'behavior:');
    });

    $t->test('xss() vbscript: 프로토콜 제거', function () use ($t) {
        $result = guard()->xss('vbscript:MsgBox("xss")');
        $t->notContains(strtolower($result), 'vbscript:');
    });

    $t->test('xss() 제어문자 삽입 javascript 우회 방어', function () use ($t) {
        // java\x08script: 형태의 우회 시도
        $result = guard()->xss("java\x08script:alert(1)");
        $t->notContains(strtolower($result), 'javascript:');
    });

    $t->test('xss() style 속성 전체 제거', function () use ($t) {
        $result = guard()->xss('<div style="background:url(javascript:alert(1))">test</div>');
        $t->notContains(strtolower($result), 'style=');
    });

    $t->test('xss() annotation-xml 태그 제거', function () use ($t) {
        $result = guard()->xss('<annotation-xml><img src=x onerror=alert(1)></annotation-xml>');
        $t->notContains(strtolower($result), '<annotation-xml');
    });

    $t->test('xss() 이중 인코딩 공격 방어', function () use ($t) {
        $result = guard()->xss('&lt;script&gt;alert(1)&lt;/script&gt;');
        $t->notContains(strtolower($result), '<script');
    });

    // ── 경로 탐색 회귀테스트 — 최신 우회 ──

    $t->test('path() 오버롱 UTF-8 dot 방어', function () use ($t) {
        // \xc0\xae = overlong encoding of '.'
        $result = guard()->path("\xc0\xae\xc0\xae/etc/passwd");
        $t->notContains($result, '..');
    });

    $t->test('path() Tomcat semicolon 방어', function () use ($t) {
        $result = guard()->path('..;/..;/etc/passwd');
        $t->notContains($result, '..');
    });

    $t->test('path() 백슬래시 정규화', function () use ($t) {
        $result = guard()->path('..\\..\\etc\\passwd');
        $t->notContains($result, '..');
    });

    $t->test('path() 제로폭 유니코드 제거', function () use ($t) {
        // \u200B (zero-width space) 삽입
        $result = guard()->path(".\xe2\x80\x8b./etc/passwd");
        $t->notContains($result, '..');
    });

    $t->test('path() 삼중 URL 인코딩 방어', function () use ($t) {
        // %252e%252e = double-encoded '..'
        $result = guard()->path('%252e%252e/etc/passwd');
        $t->notContains($result, '..');
    });
};
