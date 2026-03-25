<?php declare(strict_types=1);

/** Captcha 테스트 — GD 확장 필수 기능만 (수학 캡차 로직 등) */
return function (TestRunner $t): void {
    $t->suite('Captcha — GD 확장 체크 + 수학 캡차 로직');

    // GD 확장이 없으면 생성자에서 RuntimeException 발생해야 함
    if (!extension_loaded('gd')) {
        $t->test('GD 미설치 시 RuntimeException', function () use ($t) {
            $t->throws(fn() => captcha(), \RuntimeException::class);
        });
        return;
    }

    // GD 확장이 있는 환경에서만 아래 테스트 실행

    $t->test('getInstance() 싱글턴', function () use ($t) {
        $a = \Cat\Captcha::getInstance();
        $b = \Cat\Captcha::getInstance();
        $t->ok($a === $b);
    });

    $t->test('math() 수학 캡차 배열 반환', function () use ($t) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $result = captcha()->math();
        $t->ok(is_array($result));
        $t->hasKey($result, 'question');
        $t->hasKey($result, 'html');
        $t->ok((bool) preg_match('/\d+/', $result['question']));
        $t->contains($result['html'], '<span');
    });

    $t->test('src() base64 data URI', function () use ($t) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $src = captcha()->src();
        $t->ok(str_starts_with($src, 'data:image/png;base64,'));
    });

    $t->test('html() img 태그 반환', function () use ($t) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $html = captcha()->html('myCaptcha');
        $t->contains($html, '<img');
        $t->contains($html, 'id="myCaptcha"');
        $t->contains($html, 'data:image/png;base64,');
    });

    $t->test('verify() — 세션 기반 검증', function () use ($t) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // 세션에 직접 정답 설정
        $key = \config('captcha.session_key', '_captcha');
        $_SESSION[$key] = 'ABC12';
        $t->ok(captcha()->verify('ABC12'));
    });

    $t->test('verify() — 대소문자 무시', function () use ($t) {
        $key = \config('captcha.session_key', '_captcha');
        $_SESSION[$key] = 'XyZ99';
        $t->ok(captcha()->verify('xyz99'));
    });

    $t->test('verify() — 오답 실패', function () use ($t) {
        $key = \config('captcha.session_key', '_captcha');
        $_SESSION[$key] = 'CORRECT';
        $t->notOk(captcha()->verify('WRONG'));
    });

    $t->test('verify() — 1회 사용 후 삭제', function () use ($t) {
        $key = \config('captcha.session_key', '_captcha');
        $_SESSION[$key] = 'ONETIME';
        captcha()->verify('ONETIME');
        // 검증 후 세션에서 삭제되어야 함
        $t->notOk(isset($_SESSION[$key]) && $_SESSION[$key] !== '');
    });
};
