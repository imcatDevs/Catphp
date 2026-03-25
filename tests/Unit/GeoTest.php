<?php declare(strict_types=1);

/** Cat\Geo 테스트 */
return function (TestRunner $t): void {
    $t->suite('Geo — t / currency / date / locale / url / flatten');

    $t->test('getLocale() 기본값', function () use ($t) {
        $t->eq('ko', geo()->getLocale());
    });

    $t->test('locale() 지원 언어 설정', function () use ($t) {
        geo()->locale('en');
        $t->eq('en', geo()->getLocale());
        geo()->locale('ko'); // 복원
    });

    $t->test('locale() 미지원 언어 무시', function () use ($t) {
        geo()->locale('ko');
        geo()->locale('fr'); // 미지원
        $t->eq('ko', geo()->getLocale());
    });

    $t->test('t() 번역 키 없으면 키 반환', function () use ($t) {
        $t->eq('nonexistent.key', geo()->t('nonexistent.key'));
    });

    $t->test('t() 치환', function () use ($t) {
        // lang/ko.php에 키가 있다면 치환 동작 확인
        $result = geo()->t('missing.key', ['name' => 'Cat']);
        $t->eq('missing.key', $result); // 키 없으면 원본 반환
    });

    $t->test('url() 다국어 경로', function () use ($t) {
        geo()->locale('ko');
        $t->eq('/ko/about', geo()->url('about'));
        $t->eq('/en/about', geo()->url('about', 'en'));
    });

    $t->test('currency() 한국어 포맷', function () use ($t) {
        geo()->locale('ko');
        $result = geo()->currency(10000);
        $t->ok(str_contains($result, '10,000') || str_contains($result, '10000'));
    });

    $t->test('date() 한국어 포맷', function () use ($t) {
        geo()->locale('ko');
        $result = geo()->date(mktime(0, 0, 0, 3, 15, 2024));
        $t->ok(str_contains($result, '2024') && str_contains($result, '3'));
    });
};
