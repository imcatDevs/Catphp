<?php declare(strict_types=1);

/** Hash 해싱/HMAC/비밀번호 테스트 */
return function (TestRunner $t): void {
    $t->suite('Hash — string / hmac / password / equals / file / directory');

    $t->test('string() SHA-256 해시', function () use ($t) {
        $hash = hasher()->string('hello');
        $t->eq(64, strlen($hash));
        $t->eq(hash('sha256', 'hello'), $hash);
    });

    $t->test('string() MD5 알고리즘', function () use ($t) {
        $hash = hasher()->string('test', 'md5');
        $t->eq(32, strlen($hash));
        $t->eq(md5('test'), $hash);
    });

    $t->test('string() 동일 입력 → 동일 출력', function () use ($t) {
        $t->eq(hasher()->string('abc'), hasher()->string('abc'));
    });

    $t->test('string() 다른 입력 → 다른 출력', function () use ($t) {
        $t->neq(hasher()->string('a'), hasher()->string('b'));
    });

    $t->test('hmac() HMAC 서명', function () use ($t) {
        $mac = hasher()->hmac('message', 'secret');
        $t->eq(64, strlen($mac));
        $t->eq(hash_hmac('sha256', 'message', 'secret'), $mac);
    });

    $t->test('verifyHmac() 검증 성공', function () use ($t) {
        $mac = hasher()->hmac('data', 'key');
        $t->ok(hasher()->verifyHmac('data', $mac, 'key'));
    });

    $t->test('verifyHmac() 변조 데이터 실패', function () use ($t) {
        $mac = hasher()->hmac('data', 'key');
        $t->notOk(hasher()->verifyHmac('modified', $mac, 'key'));
    });

    $t->test('verifyHmac() 변조 키 실패', function () use ($t) {
        $mac = hasher()->hmac('data', 'key');
        $t->notOk(hasher()->verifyHmac('data', $mac, 'wrong'));
    });

    $t->test('password() 해시 생성', function () use ($t) {
        $hash = hasher()->password('secret123');
        $t->ok(strlen($hash) > 20);
        $t->neq('secret123', $hash);
    });

    $t->test('passwordVerify() 검증 성공', function () use ($t) {
        $hash = hasher()->password('mypassword');
        $t->ok(hasher()->passwordVerify('mypassword', $hash));
    });

    $t->test('passwordVerify() 검증 실패', function () use ($t) {
        $hash = hasher()->password('correct');
        $t->notOk(hasher()->passwordVerify('wrong', $hash));
    });

    $t->test('equals() 타이밍 안전 비교 — 일치', function () use ($t) {
        $t->ok(hasher()->equals('abc123', 'abc123'));
    });

    $t->test('equals() 타이밍 안전 비교 — 불일치', function () use ($t) {
        $t->notOk(hasher()->equals('abc123', 'abc124'));
    });

    $t->test('algorithms() 알고리즘 목록', function () use ($t) {
        $algos = hasher()->algorithms();
        $t->ok(is_array($algos));
        $t->ok(in_array('sha256', $algos, true));
        $t->ok(in_array('md5', $algos, true));
    });

    // 파일 해싱 테스트 (임시 파일 사용)
    $tmpFile = __DIR__ . '/../_tmp/hash_test.txt';
    @mkdir(dirname($tmpFile), 0755, true);
    file_put_contents($tmpFile, 'hello world');

    $t->test('file() 파일 해시', function () use ($t, $tmpFile) {
        $hash = hasher()->file($tmpFile);
        $t->eq(hash_file('sha256', $tmpFile), $hash);
    });

    $t->test('file() MD5 알고리즘', function () use ($t, $tmpFile) {
        $hash = hasher()->file($tmpFile, 'md5');
        $t->eq(md5_file($tmpFile), $hash);
    });

    $t->test('verify() 파일 무결성 검증', function () use ($t, $tmpFile) {
        $hash = hasher()->file($tmpFile);
        $t->ok(hasher()->verify($tmpFile, $hash));
    });

    $t->test('file() 존재하지 않는 파일 예외', function () use ($t) {
        $t->throws(fn() => hasher()->file('/nonexistent/file.txt'), \RuntimeException::class);
    });

    $t->test('checksum() CRC32', function () use ($t, $tmpFile) {
        $crc = hasher()->checksum($tmpFile);
        $t->eq(hash_file('crc32b', $tmpFile), $crc);
    });

    // 디렉토리 해싱 테스트
    $tmpDir = __DIR__ . '/../_tmp/hash_dir';
    @mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/a.txt', 'aaa');
    file_put_contents($tmpDir . '/b.txt', 'bbb');

    $t->test('directory() 매니페스트 생성', function () use ($t, $tmpDir) {
        $manifest = hasher()->directory($tmpDir);
        $t->ok(is_array($manifest));
        $t->hasKey($manifest, 'a.txt');
        $t->hasKey($manifest, 'b.txt');
    });

    $t->test('verifyDirectory() 무결성 통과', function () use ($t, $tmpDir) {
        $manifest = hasher()->directory($tmpDir);
        $diff = hasher()->verifyDirectory($tmpDir, $manifest);
        $t->count(0, $diff['modified']);
        $t->count(0, $diff['added']);
        $t->count(0, $diff['removed']);
    });

    $t->test('verifyDirectory() 변조 감지', function () use ($t, $tmpDir) {
        $manifest = hasher()->directory($tmpDir);
        file_put_contents($tmpDir . '/a.txt', 'modified');
        $diff = hasher()->verifyDirectory($tmpDir, $manifest);
        $t->count(1, $diff['modified']);
        // 원복
        file_put_contents($tmpDir . '/a.txt', 'aaa');
    });

    // 정리
    @unlink($tmpFile);
    @unlink($tmpDir . '/a.txt');
    @unlink($tmpDir . '/b.txt');
    @rmdir($tmpDir);
};
