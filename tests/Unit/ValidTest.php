<?php declare(strict_types=1);

/** Cat\Valid 테스트 */
return function (TestRunner $t): void {
    $t->suite('Valid — rules / check / fails / errors');

    $t->test('required 통과', function () use ($t) {
        $v = valid(['name' => 'required'])->check(['name' => 'John']);
        $t->notOk($v->fails());
    });

    $t->test('required 실패', function () use ($t) {
        $v = valid(['name' => 'required'])->check(['name' => '']);
        $t->ok($v->fails());
        $t->hasKey($v->errors(), 'name');
    });

    $t->test('email 유효', function () use ($t) {
        $v = valid(['email' => 'email'])->check(['email' => 'user@example.com']);
        $t->notOk($v->fails());
    });

    $t->test('email 무효', function () use ($t) {
        $v = valid(['email' => 'email'])->check(['email' => 'not-email']);
        $t->ok($v->fails());
    });

    $t->test('min 문자열 길이', function () use ($t) {
        $v = valid(['pw' => 'min:8'])->check(['pw' => '1234567']);
        $t->ok($v->fails());
        $v2 = valid(['pw' => 'min:8'])->check(['pw' => '12345678']);
        $t->notOk($v2->fails());
    });

    $t->test('max 문자열 길이', function () use ($t) {
        $v = valid(['name' => 'max:5'])->check(['name' => 'abcdef']);
        $t->ok($v->fails());
    });

    $t->test('between 범위', function () use ($t) {
        $v = valid(['age' => 'between:1,100'])->check(['age' => '50']);
        $t->notOk($v->fails());
        $v2 = valid(['age' => 'between:1,100'])->check(['age' => '150']);
        $t->ok($v2->fails());
    });

    $t->test('in 목록 포함', function () use ($t) {
        $v = valid(['role' => 'in:admin,user'])->check(['role' => 'admin']);
        $t->notOk($v->fails());
        $v2 = valid(['role' => 'in:admin,user'])->check(['role' => 'hacker']);
        $t->ok($v2->fails());
    });

    $t->test('nullable 빈 값 건너뛰기', function () use ($t) {
        $v = valid(['bio' => 'nullable|min:10'])->check(['bio' => '']);
        $t->notOk($v->fails());
    });

    $t->test('confirmed 일치', function () use ($t) {
        $v = valid(['pw' => 'confirmed'])->check(['pw' => 'abc', 'pw_confirmation' => 'abc']);
        $t->notOk($v->fails());
    });

    $t->test('confirmed 불일치', function () use ($t) {
        $v = valid(['pw' => 'confirmed'])->check(['pw' => 'abc', 'pw_confirmation' => 'xyz']);
        $t->ok($v->fails());
    });

    $t->test('커스텀 규칙 등록', function () use ($t) {
        \Cat\Valid::extend('even', function (string $field, mixed $value) {
            return ((int) $value % 2 !== 0) ? "{$field}은(는) 짝수여야 합니다" : null;
        });
        $v = valid(['num' => 'even'])->check(['num' => '3']);
        $t->ok($v->fails());
        $v2 = valid(['num' => 'even'])->check(['num' => '4']);
        $t->notOk($v2->fails());
    });
};
