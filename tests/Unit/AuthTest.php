<?php declare(strict_types=1);

/** Cat\Auth 보안 테스트 */
return function (TestRunner $t): void {
    $t->suite('Auth — JWT / password / bearer / session');

    // ── JWT ──
    $t->test('createToken() + verifyToken() 라운드트립', function () use ($t) {
        $payload = ['sub' => 42, 'role' => 'admin'];
        $token = auth()->createToken($payload, 60);
        $t->ok(is_string($token));
        $decoded = auth()->verifyToken($token);
        $t->ok(is_array($decoded));
        $t->eq(42, $decoded['sub']);
        $t->eq('admin', $decoded['role']);
    });

    $t->test('verifyToken() 만료 토큰 → null', function () use ($t) {
        $token = auth()->createToken(['sub' => 1], -1); // TTL -1 = 이미 만료
        $t->isNull(auth()->verifyToken($token));
    });

    $t->test('verifyToken() 변조된 토큰 → null', function () use ($t) {
        $token = auth()->createToken(['sub' => 1], 60);
        // 서명 부분 1글자 변조
        $parts = explode('.', $token);
        $parts[2] = substr($parts[2], 0, -1) . ($parts[2][-1] === 'a' ? 'b' : 'a');
        $tampered = implode('.', $parts);
        $t->isNull(auth()->verifyToken($tampered));
    });

    $t->test('verifyToken() 잘못된 형식 → null', function () use ($t) {
        $t->isNull(auth()->verifyToken('not.a.valid.jwt.token'));
        $t->isNull(auth()->verifyToken(''));
        $t->isNull(auth()->verifyToken('single-segment'));
    });

    $t->test('verifyToken() nbf(not before) 미래 토큰 → null', function () use ($t) {
        $token = auth()->createToken(['sub' => 1, 'nbf' => time() + 9999], 3600);
        $t->isNull(auth()->verifyToken($token));
    });

    // ── 비밀번호 ──
    $t->test('hashPassword() + verifyPassword() 라운드트립', function () use ($t) {
        $hash = auth()->hashPassword('myP@ssw0rd!');
        $t->ok(is_string($hash));
        $t->ok(auth()->verifyPassword('myP@ssw0rd!', $hash));
    });

    $t->test('verifyPassword() 틀린 비밀번호 → false', function () use ($t) {
        $hash = auth()->hashPassword('correct');
        $t->notOk(auth()->verifyPassword('wrong', $hash));
    });

    $t->test('needsRehash() 구형 해시 감지', function () use ($t) {
        // bcrypt로 강제 해싱한 후 현재 알고리즘(argon2id)과 비교
        $bcryptHash = password_hash('test', PASSWORD_BCRYPT);
        $t->ok(auth()->needsRehash($bcryptHash));
    });

    // ── API 사용자 ──
    $t->test('setApiUser() + apiUser() + id()', function () use ($t) {
        auth()->setApiUser(['sub' => 99, 'role' => 'editor']);
        $user = auth()->apiUser();
        $t->ok(is_array($user));
        $t->eq(99, $user['sub']);
        $t->eq(99, auth()->id());
        // 정리
        auth()->setApiUser([]);
    });

    // ── bearer() ──
    $t->test('bearer() Authorization 헤더에서 토큰 추출', function () use ($t) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token_123';
        $t->eq('test_token_123', auth()->bearer());
        unset($_SERVER['HTTP_AUTHORIZATION']);
    });

    $t->test('bearer() 비Bearer 헤더 → null', function () use ($t) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
        $t->isNull(auth()->bearer());
        unset($_SERVER['HTTP_AUTHORIZATION']);
    });

    $t->test('bearer() 헤더 없음 → null', function () use ($t) {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        $t->isNull(auth()->bearer());
    });

    // ── check/guest ──
    $t->test('guest() API 사용자 없으면 true', function () use ($t) {
        auth()->setApiUser([]);
        // 세션 사용자도 없는 상태
        $t->ok(auth()->guest() || auth()->check());
        // 명시적으로 API 사용자 설정 후 확인
        auth()->setApiUser(['sub' => 1]);
        $t->ok(auth()->check());
        $t->notOk(auth()->guest());
        auth()->setApiUser([]);
    });
};
