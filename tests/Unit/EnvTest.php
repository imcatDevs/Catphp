<?php declare(strict_types=1);

/** Env 환경변수 관리 테스트 */
return function (TestRunner $t): void {
    $t->suite('Env — load / get / set / has / required / requiredNotEmpty / remove / write / parseValue / castValue');

    $tmpDir = __DIR__ . '/../_tmp';
    @mkdir($tmpDir, 0755, true);
    $envFile = $tmpDir . '/test.env';

    $t->test('set() + get() 라운드트립', function () use ($t) {
        $e = env();
        $e->set('TEST_CATPHP_KEY', 'hello');
        $t->eq('hello', $e->get('TEST_CATPHP_KEY'));
        // 정리
        putenv('TEST_CATPHP_KEY');
    });

    $t->test('get() 기본값 반환', function () use ($t) {
        $t->eq('default', env()->get('NONEXISTENT_KEY_XYZ', 'default'));
    });

    $t->test('has() 존재 확인', function () use ($t) {
        env()->set('TEST_CATPHP_HAS', 'yes');
        $t->ok(env()->has('TEST_CATPHP_HAS'));
        $t->notOk(env()->has('NONEXISTENT_KEY_12345'));
        putenv('TEST_CATPHP_HAS');
    });

    $t->test('load() .env 파일 파싱', function () use ($t, $envFile) {
        file_put_contents($envFile, implode(PHP_EOL, [
            '# 주석',
            'ENV_TEST_APP=myapp',
            'ENV_TEST_DEBUG=true',
            'ENV_TEST_PORT=8080',
            'ENV_TEST_NULL=null',
            'ENV_TEST_EMPTY=',
            'ENV_TEST_QUOTED="hello world"',
            "ENV_TEST_SINGLE='raw value'",
            'ENV_TEST_COMMENT=value # 인라인 주석',
            'export ENV_TEST_EXPORT=exported',
        ]));

        env()->load($envFile);

        $t->eq('myapp', env()->get('ENV_TEST_APP'));
        $t->eq(true, env()->get('ENV_TEST_DEBUG'));
        $t->eq('8080', env()->get('ENV_TEST_PORT'));
        $t->isNull(env()->get('ENV_TEST_NULL'));
        $t->eq('', env()->get('ENV_TEST_EMPTY'));
        $t->eq('hello world', env()->get('ENV_TEST_QUOTED'));
        $t->eq('raw value', env()->get('ENV_TEST_SINGLE'));
        $t->eq('value', env()->get('ENV_TEST_COMMENT'));
        $t->eq('exported', env()->get('ENV_TEST_EXPORT'));
    });

    $t->test('isLoaded() 상태 확인', function () use ($t) {
        $t->ok(env()->isLoaded());
    });

    $t->test('castValue — true/false/null', function () use ($t) {
        env()->set('CAST_TRUE', 'true');
        env()->set('CAST_FALSE', 'false');
        env()->set('CAST_NULL', 'null');
        $t->eq(true, env()->get('CAST_TRUE'));
        $t->eq(false, env()->get('CAST_FALSE'));
        $t->isNull(env()->get('CAST_NULL'));
    });

    $t->test('변수 참조 치환 ${VAR}', function () use ($t, $envFile) {
        file_put_contents($envFile, implode(PHP_EOL, [
            'REF_BASE=http://localhost',
            'REF_URL=${REF_BASE}/api',
        ]));
        env()->load($envFile, force: true);
        $t->eq('http://localhost/api', env()->get('REF_URL'));
    });

    $t->test('변수 참조 치환 $VAR (중괄호 없이)', function () use ($t, $envFile) {
        file_put_contents($envFile, implode(PHP_EOL, [
            'BARE_HOST=example.com',
            'BARE_URL=https://$BARE_HOST/path',
        ]));
        env()->load($envFile, force: true);
        $t->eq('https://example.com/path', env()->get('BARE_URL'));
    });

    $t->test('미정의 변수 참조 → 원본 문자열 유지', function () use ($t, $envFile) {
        file_put_contents($envFile, implode(PHP_EOL, [
            'UNDEF_REF=${TOTALLY_UNDEFINED_XYZ_999}',
        ]));
        env()->load($envFile, force: true);
        $t->eq('${TOTALLY_UNDEFINED_XYZ_999}', env()->get('UNDEF_REF'));
    });

    $t->test('required() 필수 키 통과', function () use ($t) {
        env()->set('REQ_A', 'a');
        env()->set('REQ_B', 'b');
        // 예외 없이 통과
        env()->required(['REQ_A', 'REQ_B']);
        $t->ok(true);
    });

    $t->test('required() 누락 시 예외', function () use ($t) {
        $t->throws(
            fn() => env()->required(['MISSING_REQUIRED_KEY_XYZ']),
            \RuntimeException::class
        );
    });

    $t->test('required() 빈 문자열도 "존재"로 인정', function () use ($t) {
        env()->set('REQ_EMPTY', '');
        // 빈 문자열이지만 키가 존재하므로 예외 없이 통과
        env()->required(['REQ_EMPTY']);
        $t->ok(true);
        env()->remove('REQ_EMPTY');
    });

    $t->test('requiredNotEmpty() 빈 문자열 시 예외', function () use ($t) {
        env()->set('RNEQ_EMPTY', '');
        $t->throws(
            fn() => env()->requiredNotEmpty(['RNEQ_EMPTY']),
            \RuntimeException::class
        );
        env()->remove('RNEQ_EMPTY');
    });

    $t->test('requiredNotEmpty() 값 있으면 통과', function () use ($t) {
        env()->set('RNEQ_OK', 'value');
        env()->requiredNotEmpty(['RNEQ_OK']);
        $t->ok(true);
        env()->remove('RNEQ_OK');
    });

    $t->test('all() 로드된 변수 반환', function () use ($t) {
        $all = env()->all();
        $t->ok(is_array($all));
        $t->ok(count($all) > 0);
    });

    $t->test('write() 새 키 추가', function () use ($t, $envFile) {
        file_put_contents($envFile, "EXISTING=old\n");
        env()->write($envFile, 'NEW_KEY', 'new_value');
        $content = file_get_contents($envFile);
        $t->contains($content, 'NEW_KEY=new_value');
        $t->contains($content, 'EXISTING=old');
    });

    $t->test('write() 기존 키 수정', function () use ($t, $envFile) {
        file_put_contents($envFile, "MY_KEY=old_value\n");
        env()->write($envFile, 'MY_KEY', 'new_value');
        $content = file_get_contents($envFile);
        $t->contains($content, 'MY_KEY=new_value');
        $t->notContains($content, 'old_value');
    });

    $t->test('write() export 접두사 보존', function () use ($t, $envFile) {
        file_put_contents($envFile, "export EXP_KEY=old\n");
        env()->write($envFile, 'EXP_KEY', 'updated');
        $content = file_get_contents($envFile);
        $t->contains($content, 'export EXP_KEY=updated');
    });

    $t->test('write() 공백 포함 값 따옴표 이스케이프', function () use ($t, $envFile) {
        file_put_contents($envFile, '');
        env()->write($envFile, 'SPACE_KEY', 'hello world');
        $content = file_get_contents($envFile);
        $t->contains($content, 'SPACE_KEY="hello world"');
    });

    $t->test('load() 존재하지 않는 파일 안전', function () use ($t) {
        env()->load('/nonexistent/path/.env');
        $t->ok(true);
    });

    $t->test('load() 멱등성 — 같은 파일 2회 로드 시 중복 파싱 방지', function () use ($t, $envFile) {
        file_put_contents($envFile, "IDEM_KEY=first\n");
        env()->load($envFile, force: true);
        $t->eq('first', env()->get('IDEM_KEY'));

        // 파일 내용 변경 후 재로드 시도 (멱등성으로 스킵)
        file_put_contents($envFile, "IDEM_KEY=second\n");
        env()->load($envFile);
        $t->eq('first', env()->get('IDEM_KEY'));

        // force=true로 재로드
        env()->load($envFile, force: true);
        $t->eq('second', env()->get('IDEM_KEY'));
        env()->remove('IDEM_KEY');
    });

    $t->test('remove() 환경변수 삭제', function () use ($t) {
        env()->set('RM_KEY', 'to_remove');
        $t->ok(env()->has('RM_KEY'));
        env()->remove('RM_KEY');
        $t->notOk(env()->has('RM_KEY'));
        $t->eq('fallback', env()->get('RM_KEY', 'fallback'));
    });

    $t->test('write() 주석 줄 보호 (주석된 키 덕어쓰기 방지)', function () use ($t, $envFile) {
        file_put_contents($envFile, "# WP_KEY=old_comment\nWP_KEY=real\n");
        env()->write($envFile, 'WP_KEY', 'updated');
        $content = file_get_contents($envFile);
        $t->contains($content, '# WP_KEY=old_comment');
        $t->contains($content, 'WP_KEY=updated');
    });

    // 정리
    @unlink($envFile);
};
