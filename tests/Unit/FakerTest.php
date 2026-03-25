<?php declare(strict_types=1);

/** Faker 테스트 데이터 생성 테스트 */
return function (TestRunner $t): void {
    $t->suite('Faker — name / email / phone / uuid / number / make / unique');

    $t->test('name() 한글 이름 (구분자 없음)', function () use ($t) {
        $name = faker()->name();
        $t->ok(mb_strlen($name) >= 2);
        $t->notContains($name, ' ');
    });

    $t->test('name() 영문 이름 (공백 구분자)', function () use ($t) {
        $name = faker()->locale('en')->name();
        $t->contains($name, ' ');
        faker()->locale('ko');
    });

    $t->test('firstName() 비어있지 않음', function () use ($t) {
        $t->ok(mb_strlen(faker()->firstName()) > 0);
    });

    $t->test('lastName() 비어있지 않음', function () use ($t) {
        $t->ok(mb_strlen(faker()->lastName()) > 0);
    });

    $t->test('email() @ 포함', function () use ($t) {
        $email = faker()->email();
        $t->contains($email, '@');
        $t->contains($email, '.');
    });

    $t->test('safeEmail() example.com 도메인', function () use ($t) {
        $email = faker()->safeEmail();
        $t->contains($email, '@example.com');
    });

    $t->test('phone() 한국 형식', function () use ($t) {
        $phone = faker()->phone();
        $t->ok(str_starts_with($phone, '01'));
        $t->contains($phone, '-');
    });

    $t->test('address() 비어있지 않음', function () use ($t) {
        $t->ok(mb_strlen(faker()->address()) > 5);
    });

    $t->test('city() 비어있지 않음', function () use ($t) {
        $t->ok(mb_strlen(faker()->city()) > 0);
    });

    $t->test('zipCode() 한국 5자리', function () use ($t) {
        $zip = faker()->zipCode();
        $t->eq(5, strlen($zip));
        $t->ok(ctype_digit($zip));
    });

    $t->test('word() 비어있지 않음', function () use ($t) {
        $t->ok(mb_strlen(faker()->word()) > 0);
    });

    $t->test('sentence() 마침표 끝', function () use ($t) {
        $sentence = faker()->sentence();
        $t->ok(str_ends_with($sentence, '.'));
    });

    $t->test('paragraph() 여러 문장', function () use ($t) {
        $para = faker()->paragraph();
        $t->ok(mb_strlen($para) > 20);
        $t->contains($para, '.');
    });

    $t->test('number() 범위 내', function () use ($t) {
        $n = faker()->number(10, 20);
        $t->ok($n >= 10 && $n <= 20);
    });

    $t->test('float() 범위 내', function () use ($t) {
        $f = faker()->float(1.0, 5.0);
        $t->ok($f >= 1.0 && $f <= 5.0);
    });

    $t->test('boolean() bool 반환', function () use ($t) {
        $t->isType('bool', faker()->boolean());
    });

    $t->test('date() Y-m-d 형식', function () use ($t) {
        $date = faker()->date();
        $t->ok((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date));
    });

    $t->test('time() H:i:s 형식', function () use ($t) {
        $time = faker()->time();
        $t->ok((bool) preg_match('/^\d{2}:\d{2}:\d{2}$/', $time));
    });

    $t->test('uuid() v4 형식', function () use ($t) {
        $uuid = faker()->uuid();
        $t->ok((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid));
    });

    $t->test('url() https:// 시작', function () use ($t) {
        $url = faker()->url();
        $t->ok(str_starts_with($url, 'https://'));
    });

    $t->test('ipv4() 유효한 IP', function () use ($t) {
        $ip = faker()->ipv4();
        $t->ok(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);
    });

    $t->test('color() HEX 형식', function () use ($t) {
        $color = faker()->color();
        $t->ok((bool) preg_match('/^#[0-9a-f]{6}$/', $color));
    });

    $t->test('password() 지정 길이', function () use ($t) {
        $pass = faker()->password(16);
        $t->eq(16, strlen($pass));
    });

    $t->test('make() 대량 생성', function () use ($t) {
        $items = faker()->make(5, fn($f, $i) => ['id' => $i, 'name' => $f->name()]);
        $t->count(5, $items);
        $t->hasKey($items[0], 'id');
        $t->hasKey($items[0], 'name');
        $t->eq(0, $items[0]['id']);
        $t->eq(4, $items[4]['id']);
    });

    $t->test('unique() 중복 없는 값 생성', function () use ($t) {
        faker()->resetUnique();
        $values = [];
        for ($i = 0; $i < 10; $i++) {
            $values[] = faker()->unique(fn() => faker()->number(1, 1000), 'test_group');
        }
        $t->eq(10, count(array_unique($values)));
        faker()->resetUnique('test_group');
    });

    $t->test('unique() 최대 시도 초과 시 예외', function () use ($t) {
        faker()->resetUnique();
        $t->throws(function () {
            for ($i = 0; $i < 5; $i++) {
                faker()->unique(fn() => 1, 'overflow', 3);
            }
        }, \RuntimeException::class);
        faker()->resetUnique('overflow');
    });

    $t->test('resetUnique() 그룹 초기화', function () use ($t) {
        faker()->resetUnique();
        faker()->unique(fn() => 'x', 'reset_test');
        faker()->resetUnique('reset_test');
        // 초기화 후 같은 값 다시 생성 가능
        $val = faker()->unique(fn() => 'x', 'reset_test');
        $t->eq('x', $val);
        faker()->resetUnique();
    });

    $t->test('randomElement() 배열에서 선택', function () use ($t) {
        $arr = ['a', 'b', 'c'];
        $val = faker()->randomElement($arr);
        $t->ok(in_array($val, $arr, true));
    });

    $t->test('slug() 영문 소문자-하이픈', function () use ($t) {
        $slug = faker()->slug();
        $t->ok((bool) preg_match('/^[a-z]+([-][a-z]+)*$/', $slug));
    });
};
