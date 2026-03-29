<?php declare(strict_types=1);

/** Cat\Csrf 보안 테스트 */
return function (TestRunner $t): void {
    $t->suite('Csrf — token / maskedToken / verify / verifyMasked');

    // CLI 환경에서 세션 시뮬레이션
    if (!isset($_SESSION)) {
        $_SESSION = [];
    }

    // ── 토큰 생성 ──
    $t->test('token() 64자 hex 문자열 반환', function () use ($t) {
        unset($_SESSION['_csrf_token']); // 초기화
        $token = csrf()->token();
        $t->ok(is_string($token));
        $t->eq(64, strlen($token)); // 32bytes = 64 hex chars
        $t->ok(ctype_xdigit($token));
    });

    $t->test('token() 동일 세션 내 같은 값 반환', function () use ($t) {
        $t1 = csrf()->token();
        $t2 = csrf()->token();
        $t->eq($t1, $t2);
    });

    // ── 마스킹 ──
    $t->test('maskedToken() 매 호출 다른 값 (랜덤 마스크)', function () use ($t) {
        $m1 = csrf()->maskedToken();
        $m2 = csrf()->maskedToken();
        $t->ok($m1 !== $m2); // 랜덤 마스크 → 매번 다른 결과
    });

    $t->test('maskedToken() 128자 hex 문자열 (32 mask + 32 encrypted = 64 bytes)', function () use ($t) {
        $masked = csrf()->maskedToken();
        $t->eq(128, strlen($masked)); // 64 bytes = 128 hex chars
        $t->ok(ctype_xdigit($masked));
    });

    // ── 검증 ──
    $t->test('verify() GET 요청은 항상 true', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $t->ok(csrf()->verify());
    });

    $t->test('verify() HEAD/OPTIONS 요청은 항상 true', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $t->ok(csrf()->verify());
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $t->ok(csrf()->verify());
    });

    $t->test('verify() POST + 원본 토큰 직접 제출 → true', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $raw = csrf()->token();
        $_POST['_csrf_token'] = $raw;
        $t->ok(csrf()->verify());
        unset($_POST['_csrf_token']);
    });

    $t->test('verify() POST + 마스킹 토큰 제출 → true', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $masked = csrf()->maskedToken();
        $_POST['_csrf_token'] = $masked;
        $t->ok(csrf()->verify());
        unset($_POST['_csrf_token']);
    });

    $t->test('verify() POST + 토큰 누락 → false', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['_csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
        $t->notOk(csrf()->verify());
    });

    $t->test('verify() POST + 변조된 토큰 → false', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = str_repeat('a', 64);
        $t->notOk(csrf()->verify());
        unset($_POST['_csrf_token']);
    });

    $t->test('verify() POST + 잘못된 hex → false', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf_token'] = 'not_hex_at_all!!!';
        $t->notOk(csrf()->verify());
        unset($_POST['_csrf_token']);
    });

    $t->test('verify() X-CSRF-TOKEN 헤더로 제출', function () use ($t) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['_csrf_token']);
        $_SERVER['HTTP_X_CSRF_TOKEN'] = csrf()->maskedToken();
        $t->ok(csrf()->verify());
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    });

    $t->test('field() hidden input HTML 반환', function () use ($t) {
        $html = csrf()->field();
        $t->contains($html, '<input type="hidden"');
        $t->contains($html, 'name="_csrf_token"');
        $t->contains($html, 'value="');
    });

    // 정리
    $_SERVER['REQUEST_METHOD'] = 'GET';
};
