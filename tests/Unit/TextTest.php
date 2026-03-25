<?php declare(strict_types=1);

/** Cat\Text 테스트 */
return function (TestRunner $t): void {
    $t->suite('Text — excerpt / readingTime / wordCount / charCount / truncate');

    $t->test('excerpt() 짧은 텍스트 그대로', function () use ($t) {
        $t->eq('짧은 텍스트', text()->excerpt('짧은 텍스트', 200));
    });

    $t->test('excerpt() 긴 텍스트 자르기', function () use ($t) {
        $long = str_repeat('가나다라 ', 100);
        $result = text()->excerpt($long, 20);
        $t->ok(mb_strlen($result) <= 24); // 20 + '...'
        $t->contains($result, '...');
    });

    $t->test('excerpt() HTML 태그 제거', function () use ($t) {
        $html = '<p>Hello <strong>World</strong></p>';
        $t->eq('Hello World', text()->excerpt($html, 200));
    });

    $t->test('readingTime() 한글', function () use ($t) {
        $text = str_repeat('가', 500); // 500자 한글, 기본 500 WPM
        $t->eq('1분', text()->readingTime($text));
    });

    $t->test('readingTime() 영문', function () use ($t) {
        $words = implode(' ', array_fill(0, 400, 'word')); // 400 단어
        $result = text()->readingTime($words);
        $t->eq('2분', $result); // 400/200 = 2분
    });

    $t->test('wordCount() 한글+영문', function () use ($t) {
        $count = text()->wordCount('안녕하세요 hello world');
        $t->ok($count >= 4); // 한글3 + 영문2 이상
    });

    $t->test('charCount() 공백 제외', function () use ($t) {
        $t->eq(5, text()->charCount('a b c d e'));
    });

    $t->test('charCount() 공백 포함', function () use ($t) {
        $t->eq(9, text()->charCount('a b c d e', true));
    });

    $t->test('truncate() 짧으면 그대로', function () use ($t) {
        $t->eq('abc', text()->truncate('abc', 10));
    });

    $t->test('truncate() 길면 자르기', function () use ($t) {
        $t->eq('ab...', text()->truncate('abcdef', 2));
    });
};
