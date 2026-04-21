<?php declare(strict_types=1);
$f = '/www/wwwroot/catphp.imcat.dev/config/app.php';
$c = include $f;
$c['swoole']['pool']['db'] = 0;      // 풀 비활성화 (Cat\DB가 요청당 연결 재사용)
$c['swoole']['pool']['redis'] = 0;   // 동일
$c['swoole']['worker_num'] = 4;      // 24 CPU → 4 워커로 축소 (기본 동시성 충분)
$c['swoole']['task_worker_num'] = 2;
file_put_contents($f, "<?php declare(strict_types=1);\n\nreturn " . var_export($c, true) . ";\n");
echo "pool.db=0 pool.redis=0 worker_num=4 task_worker_num=2\n";
