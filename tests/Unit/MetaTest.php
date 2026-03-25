<?php declare(strict_types=1);

/** Cat\Meta 테스트 */
return function (TestRunner $t): void {
    $t->suite('Meta — title / description / og / render / sitemap / reset');

    $t->test('title render', function () use ($t) {
        meta()->reset()->title('테스트 제목');
        $html = meta()->render();
        $t->contains($html, '<title>테스트 제목</title>');
        $t->contains($html, 'og:title');
    });

    $t->test('description render', function () use ($t) {
        meta()->reset()->description('설명 텍스트');
        $html = meta()->render();
        $t->contains($html, 'name="description"');
        $t->contains($html, '설명 텍스트');
    });

    $t->test('canonical render', function () use ($t) {
        meta()->reset()->canonical('https://example.com/page');
        $html = meta()->render();
        $t->contains($html, 'rel="canonical"');
        $t->contains($html, 'https://example.com/page');
    });

    $t->test('og 태그', function () use ($t) {
        meta()->reset()->og('image', 'https://example.com/img.jpg');
        $html = meta()->render();
        $t->contains($html, 'og:image');
    });

    $t->test('twitter 태그', function () use ($t) {
        meta()->reset()->twitter('card', 'summary');
        $html = meta()->render();
        $t->contains($html, 'twitter:card');
    });

    $t->test('jsonLd render', function () use ($t) {
        meta()->reset()->jsonLd(['@type' => 'Article', 'name' => 'Test']);
        $html = meta()->render();
        $t->contains($html, 'application/ld+json');
        $t->contains($html, '"@type"');
    });

    $t->test('reset() 초기화', function () use ($t) {
        meta()->reset()->title('before');
        meta()->reset();
        $html = meta()->render();
        $t->notContains($html, 'before');
    });

    $t->test('sitemap XML 생성', function () use ($t) {
        $xml = meta()->sitemap([
            ['loc' => 'https://example.com/', 'lastmod' => '2024-01-01', 'priority' => '1.0'],
            ['loc' => 'https://example.com/about'],
        ]);
        $t->contains($xml, '<urlset');
        $t->contains($xml, '<loc>https://example.com/</loc>');
        $t->contains($xml, '<priority>1.0</priority>');
        $t->contains($xml, 'https://example.com/about');
    });

    $t->test('XSS 이스케이프', function () use ($t) {
        meta()->reset()->title('<script>alert(1)</script>');
        $html = meta()->render();
        $t->notContains($html, '<script>alert');
        $t->contains($html, '&lt;script&gt;');
    });
};
