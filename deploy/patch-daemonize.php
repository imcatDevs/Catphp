<?php declare(strict_types=1);
$f = '/www/wwwroot/catphp.imcat.dev/config/app.php';
$c = include $f;
$c['swoole']['daemonize'] = false;
file_put_contents($f, "<?php declare(strict_types=1);\n\nreturn " . var_export($c, true) . ";\n");
echo "daemonize=false (systemd 호환)\n";
