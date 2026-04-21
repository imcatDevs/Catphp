<?php declare(strict_types=1);

$file = "/www/wwwroot/catphp.imcat.dev/config/app.php";
$cfg = include $file;

$cfg['session'] = [
    'driver' => 'redis',
    'lifetime' => 7200,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
    'cookie' => 'CATPHP_SID',
    'redis_prefix' => 'sess:',
];

$cfg['swoole'] = [
    'host' => '127.0.0.1',
    'port' => 3005,
    'mode' => 'process',
    'worker_num' => 0,
    'task_worker_num' => 4,
    'max_request' => 10000,
    'max_conn' => 10000,
    'daemonize' => true,
    'dispatch_mode' => 2,
    'open_tcp_nodelay' => true,
    'enable_coroutine' => true,
    'log_file' => '/www/wwwroot/catphp.imcat.dev/storage/logs/swoole.log',
    'log_level' => 2,
    'pid_file' => '/www/wwwroot/catphp.imcat.dev/storage/swoole.pid',
    'static_handler' => false,
    'document_root' => '',
    'hot_reload' => false,
    'hot_reload_paths' => [],
    'heartbeat_idle' => 600,
    'heartbeat_check' => 60,
    'ssl_cert' => '',
    'ssl_key' => '',
    'buffer_output_size' => 2097152,
    'package_max_length' => 2097152,
    'pool' => ['db' => 32, 'redis' => 32],
];

$out = "<?php declare(strict_types=1);\n\nreturn " . var_export($cfg, true) . ";\n";
file_put_contents($file, $out);

echo "패치 완료\n";
echo "session.driver = " . $cfg['session']['driver'] . "\n";
echo "swoole = " . $cfg['swoole']['host'] . ':' . $cfg['swoole']['port']
    . ' daemon=' . ($cfg['swoole']['daemonize'] ? '1' : '0') . "\n";
echo 'redis.password set = ' . (!empty($cfg['redis']['password']) ? 'YES' : 'NO') . "\n";
echo 'db.pass set = ' . (!empty($cfg['db']['pass']) ? 'YES' : 'NO') . "\n";
echo 'auth.secret set = ' . (!empty($cfg['auth']['secret']) ? 'YES' : 'NO') . "\n";
echo 'encrypt.key set = ' . (!empty($cfg['encrypt']['key']) ? 'YES' : 'NO') . "\n";
