<?php declare(strict_types=1);

/**
 * CatPHP 테스트 부트스트랩
 *
 * 프레임워크 로드 + 테스트용 config 설정
 */

require __DIR__ . '/TestRunner.php';
require __DIR__ . '/../catphp/catphp.php';

// 테스트용 config (DB 없이 동작하는 최소 설정)
config([
    'app'       => ['debug' => true, 'timezone' => 'Asia/Seoul', 'key' => 'base64:dGVzdGtleV8xMjM0NTY3ODkwYWJjZGVm'],
    'cache'     => ['path' => __DIR__ . '/_tmp/cache', 'ttl' => 60],
    'log'       => ['path' => __DIR__ . '/_tmp/logs', 'level' => 'debug'],
    'auth'      => ['secret' => 'test_secret_key_for_unit_tests_only', 'ttl' => 3600, 'algo' => 'Argon2id'],
    'encrypt'   => ['key' => 'base64:dGVzdGtleV8xMjM0NTY3ODkwYWJjZGVm'],
    'firewall'  => ['path' => __DIR__ . '/_tmp/firewall'],
    'rate'      => ['storage' => 'cache', 'path' => __DIR__ . '/_tmp/rate'],
    'guard'     => ['auto_ban' => false, 'max_body_size' => '10M'],
    'upload'    => ['max_size' => '10M', 'allowed' => ['jpg', 'png', 'pdf']],
    'view'      => ['path' => __DIR__ . '/../Public/views'],
    'geo'       => ['default' => 'ko', 'supported' => ['ko', 'en'], 'path' => __DIR__ . '/../lang'],
    'spider'    => ['user_agent' => 'CatPHP Test/1.0', 'timeout' => 10],
    'cors'      => ['origins' => ['https://example.com'], 'methods' => ['GET', 'POST']],
    'session'   => ['lifetime' => 7200, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'],
    'cookie'    => ['encrypt' => false, 'samesite' => 'Lax', 'secure' => false],
    'hash'      => ['algo' => 'sha256'],
    'image'     => ['driver' => 'gd', 'quality' => 85],
    'captcha'   => ['width' => 150, 'height' => 50, 'length' => 5, 'session_key' => '_captcha'],
    'user'      => ['table' => 'users', 'primary_key' => 'id', 'hidden' => ['password']],
    'response'  => ['allowed_hosts' => []],
    'faker'     => ['locale' => 'ko'],
    'ip'        => ['provider' => 'api', 'mmdb_path' => null, 'cache_ttl' => 86400, 'trusted_proxies' => []],
    'perm'      => ['roles' => ['admin', 'editor', 'user']],
    'feed'      => ['limit' => 20, 'cache_ttl' => 3600],
    'search'    => ['driver' => 'fulltext', 'cache_ttl' => 300],
    'migration' => ['path' => __DIR__ . '/_tmp/migrations', 'table' => 'migrations'],
    'telegram'  => ['bot_token' => '', 'chat_id' => '', 'admin_chat' => ''],
    'mail'      => ['host' => 'localhost', 'port' => 587, 'username' => '', 'password' => '', 'encryption' => 'tls', 'from_email' => '', 'from_name' => 'CatPHP'],
    'queue'     => ['driver' => 'redis', 'default' => 'default'],
    'storage'   => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => __DIR__ . '/_tmp/storage']]],
    'redis'     => ['host' => '127.0.0.1', 'port' => 6379, 'password' => '', 'database' => 0, 'prefix' => 'test:', 'timeout' => 2.0],
]);

// 테스트용 임시 디렉토리 생성
$tmpDirs = [__DIR__ . '/_tmp/cache', __DIR__ . '/_tmp/logs', __DIR__ . '/_tmp/firewall', __DIR__ . '/_tmp/rate'];
foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/** 테스트 종료 시 임시 디렉토리 정리 */
function cleanTestTmp(): void
{
    $tmpBase = __DIR__ . '/_tmp';
    if (!is_dir($tmpBase)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpBase, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($tmpBase);
}
