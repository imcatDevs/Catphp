<?php declare(strict_types=1);

/** Cat\Encrypt 테스트 */
return function (TestRunner $t): void {
    $t->suite('Encrypt — seal / open / sign / verify');

    $t->test('seal + open 라운드트립', function () use ($t) {
        $plain = '비밀 메시지 123';
        $encrypted = encrypt()->seal($plain);
        $decrypted = encrypt()->open($encrypted);
        $t->eq($plain, $decrypted);
    });

    $t->test('seal 결과는 원문과 다름', function () use ($t) {
        $encrypted = encrypt()->seal('test');
        $t->neq('test', $encrypted);
    });

    $t->test('open 변조된 데이터 → null', function () use ($t) {
        $encrypted = encrypt()->seal('test');
        $tampered = $encrypted . 'X';
        $t->isNull(encrypt()->open($tampered));
    });

    $t->test('open 잘못된 base64 → null', function () use ($t) {
        $t->isNull(encrypt()->open('not-valid-base64!!!'));
    });

    $t->test('sign + verify 라운드트립', function () use ($t) {
        $message = 'signed message';
        $sig = encrypt()->sign($message);
        $t->ok(encrypt()->verify($message, $sig));
    });

    $t->test('verify 변조된 메시지 실패', function () use ($t) {
        $sig = encrypt()->sign('original');
        $t->notOk(encrypt()->verify('tampered', $sig));
    });
};
