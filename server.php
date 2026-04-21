<?php declare(strict_types=1);

/**
 * CatPHP Swoole 서버 진입점
 *
 * 실행:
 *   php server.php          # 포그라운드 (Ctrl+C로 중지)
 *   php cli.php swoole:start # daemonize=true 설정 시 백그라운드
 *
 * 중지/리로드:
 *   php cli.php swoole:stop
 *   php cli.php swoole:reload
 *
 * 아키텍처:
 *   - 상주 프로세스: 부트스트랩 1회 + 요청 N회
 *   - 워커별 DB/Redis 연결 풀 재사용
 *   - 싱글턴 요청 간 리셋 (Swoole.php::handleRequest)
 *   - Response/Json 직접 $res->end() 호출 (출력 버퍼 우회)
 */

// ── 1. 프레임워크 코어 로드 ──
require __DIR__ . '/catphp/catphp.php';
config(require __DIR__ . '/config/app.php');

// ── 2. 에러 모드 ──
errors((bool) config('app.debug', false));

// ── 3. Swoole 확장 확인 ──
if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
    fwrite(STDERR, "[CatPHP] Swoole 확장이 필요합니다. `pecl install swoole` 실행 후 php.ini에 추가하세요.\n");
    exit(1);
}

// ── 4. Swoole 서버 빌드 ──
$host = (string) config('swoole.host', '127.0.0.1');
$port = (int) config('swoole.port', 3005);

$sw = swoole()->listen($host, $port);
$sw->http();

// SSL 설정 (선택)
$sslCert = (string) config('swoole.ssl_cert', '');
$sslKey  = (string) config('swoole.ssl_key', '');
if ($sslCert !== '' && $sslKey !== '') {
    $sw->ssl($sslCert, $sslKey);
}

// Hot Reload (개발 모드)
if ((bool) config('swoole.hot_reload', false)) {
    echo "[CatPHP] Hot Reload 활성화\n";
}

// ── 5. 워커 부트스트랩: 라우트 등록 ──
$sw->onBoot(function ($svr, int $workerId): void {
    // 각 워커 시작 시 1회 실행 — 라우트 테이블 구축
    $routeFile = __DIR__ . '/routes/swoole.php';
    if (!is_file($routeFile)) {
        throw new \RuntimeException("라우트 파일이 없습니다: {$routeFile}");
    }
    require $routeFile;
});

// ── 6. 서버 시작 ──
// 주의: `echo` 대신 `fwrite(STDOUT)`을 사용한다.
// `echo`는 PHP 내부의 `headers_sent` 상태를 true로 만들어 워커가 상속받으면
// `header()` 호출 시 "headers already sent" 에러가 발생한다.
fwrite(STDOUT, "[CatPHP] Swoole 서버 시작 — http://{$host}:{$port}\n");
fwrite(STDOUT, "[CatPHP] 워커 수: " . (int) config('swoole.worker_num', 0) . " (0 = nproc 자동)\n");
fwrite(STDOUT, "[CatPHP] Ctrl+C로 중지하거나 'php cli.php swoole:stop' 실행\n");

$sw->start();
