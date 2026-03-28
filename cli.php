<?php declare(strict_types=1);

if (php_sapi_name() !== 'cli') { exit; }

require __DIR__ . '/catphp/catphp.php';
config(require __DIR__ . '/config/app.php');

cli()->command('serve',          '개발 서버 실행',        function() {
    $host = cli()->option('host', '127.0.0.1');
    $port = cli()->option('port', '8000');
    $docRoot = __DIR__ . '/Public';
    // host/port 검증 (명령어 주입 방어)
    if (!preg_match('/^[\w.\-]+$/', $host) || !ctype_digit($port)) {
        cli()->error('유효하지 않은 host 또는 port입니다.');
        return;
    }
    cli()->info("개발 서버 시작: http://{$host}:{$port}");
    cli()->info("종료: Ctrl+C");
    passthru("php -S " . escapeshellarg($host) . ":" . escapeshellarg($port) . " -t " . escapeshellarg($docRoot));
});

cli()->command('cache:clear',    '캐시 전체 삭제',        function() { cache()->clear(); cli()->success('Done'); });
cli()->command('firewall:list',  '차단 IP 목록',          fn() => cli()->table(['IP', '시간'], firewall()->bannedList()));
cli()->command('firewall:ban',   'IP 차단',              fn() => ($ip = cli()->arg(0)) ? firewall()->ban($ip) && cli()->success("차단: {$ip}") : cli()->error('IP를 입력하세요'));
cli()->command('firewall:unban', 'IP 차단 해제',          fn() => ($ip = cli()->arg(0)) ? firewall()->unban($ip) && cli()->success("해제: {$ip}") : cli()->error('IP를 입력하세요'));
cli()->command('log:tail',       '로그 마지막 N줄 출력',  fn() => cli()->info(logger()->tail((int) cli()->option('lines', 20))));
cli()->command('log:clean',      'N일 이전 로그 삭제',    function () {
    $days = (int) (cli()->option('days', '30') ?: '30');
    $deleted = logger()->clean($days);
    cli()->success("{$days}일 이전 로그 {$deleted}개 삭제됨");
});
cli()->command('log:clear',      '오늘 로그 삭제',        fn() => logger()->clear() ? cli()->success('오늘 로그 삭제됨') : cli()->info('삭제할 로그 없음'));
cli()->command('check:env',      '최적화 환경 체크',      function() {
    $c = cli();
    $pass = 0;
    $warn = 0;
    $fail = 0;

    $check = function (string $label, bool $ok, string $good, string $bad, bool $critical = false) use ($c, &$pass, &$warn, &$fail): void {
        if ($ok) {
            $c->success("{$label}: {$good}");
            $pass++;
        } elseif ($critical) {
            $c->error("{$label}: {$bad}");
            $fail++;
        } else {
            $c->warn("{$label}: {$bad}");
            $warn++;
        }
    };

    $c->info('═══ CatPHP 환경 진단 ═══');
    $c->newLine();

    // ── 1. PHP 버전 ──
    $c->info('── PHP 런타임 ──');
    $ver = PHP_VERSION;
    $check('PHP 버전', version_compare($ver, '8.2.0', '>='), $ver, "{$ver} (8.2+ 필수)", true);
    $check('SAPI', PHP_SAPI === 'cli', PHP_SAPI, PHP_SAPI . ' (CLI 확인용)');
    $check('메모리 제한', ((int) ini_get('memory_limit')) >= 128 || ini_get('memory_limit') === '-1',
        ini_get('memory_limit'), ini_get('memory_limit') . ' (128M+ 권장)');
    $check('max_execution_time', (int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 30,
        ini_get('max_execution_time') . 's', ini_get('max_execution_time') . 's (30s+ 권장)');
    $c->newLine();

    // ── 2. OPcache ──
    $c->info('── OPcache ──');
    $opcacheLoaded = extension_loaded('Zend OPcache');
    $check('OPcache 확장', $opcacheLoaded, '로드됨', '미로드 (성능 저하)', true);

    if ($opcacheLoaded) {
        $opcEnabled = (bool) ini_get('opcache.enable');
        $check('opcache.enable', $opcEnabled, 'On', 'Off (활성화 필요)', true);

        $preload = ini_get('opcache.preload') ?: '';
        if ($preload !== '') {
            $check('opcache.preload', true, basename($preload), '');
        } else {
            $c->info('opcache.preload: 미사용 (선택 사항, Linux 전용)');
            $pass++;
        }

        $memSize = (int) ini_get('opcache.memory_consumption');
        $check('opcache.memory_consumption', $memSize >= 128, "{$memSize}MB", "{$memSize}MB (128MB+ 권장)");

        $strings = (int) ini_get('opcache.interned_strings_buffer');
        $check('opcache.interned_strings_buffer', $strings >= 8, "{$strings}MB", "{$strings}MB (8MB+ 권장)");

        $maxFiles = (int) ini_get('opcache.max_accelerated_files');
        $check('opcache.max_accelerated_files', $maxFiles >= 10000, number_format($maxFiles), number_format($maxFiles) . ' (10000+ 권장)');

        $revalidate = (int) ini_get('opcache.revalidate_freq');
        $validate = (bool) ini_get('opcache.validate_timestamps');
        if ($validate) {
            $check('opcache.validate_timestamps', false, '', "On (프로덕션에서 Off 권장, revalidate_freq={$revalidate}s)");
        } else {
            $check('opcache.validate_timestamps', true, 'Off (프로덕션 최적)', '');
        }
    }
    $c->newLine();

    // ── 3. JIT ──
    $c->info('── JIT ──');
    if ($opcacheLoaded && function_exists('opcache_get_status')) {
        $status = @opcache_get_status(false);
        $jitEnabled = isset($status['jit']['enabled']) && $status['jit']['enabled'];
        $check('JIT 활성화', $jitEnabled, 'On', 'Off (PHP 8.x JIT 비활성)');

        $jitBufferRaw = ini_get('opcache.jit_buffer_size') ?: '0';
        $jitBuffer = (int) $jitBufferRaw;
        if (preg_match('/^(\d+)\s*([KMG])$/i', trim($jitBufferRaw), $m)) {
            $jitBuffer = (int) $m[1] * match (strtoupper($m[2])) {
                'K' => 1024, 'M' => 1048576, 'G' => 1073741824,
            };
        }
        $jitBufferMB = $jitBuffer > 0 ? (int) round($jitBuffer / 1048576) : 0;
        $check('opcache.jit_buffer_size', $jitBuffer >= 32 * 1048576, "{$jitBufferMB}MB", "{$jitBufferMB}MB (32MB+ 권장)");

        $jitMode = ini_get('opcache.jit') ?: '0';
        $check('opcache.jit', in_array($jitMode, ['1255', 'tracing', '1205'], true),
            $jitMode, "{$jitMode} (1255/tracing 권장)");
    } else {
        $check('JIT', false, '', 'OPcache 미로드로 확인 불가');
    }
    $c->newLine();

    // ── 4. 필수/권장 확장 ──
    $c->info('── PHP 확장 모듈 ──');
    $required = ['pdo', 'mbstring', 'json', 'openssl', 'sodium', 'fileinfo', 'curl'];
    $recommended = ['gd', 'intl', 'zip', 'readline'];
    if (PHP_OS_FAMILY !== 'Windows') {
        $recommended[] = 'pcntl';
    }

    foreach ($required as $ext) {
        $check($ext, extension_loaded($ext), '✔', '미설치 (필수)', true);
    }
    foreach ($recommended as $ext) {
        $check($ext, extension_loaded($ext), '✔', '미설치 (권장)');
    }

    // PDO 드라이버
    if (extension_loaded('pdo')) {
        $drivers = \PDO::getAvailableDrivers();
        $dbDriver = config('db.driver') ?: 'mysql';
        $check("PDO 드라이버 ({$dbDriver})", in_array($dbDriver, $drivers, true),
            implode(', ', $drivers), "{$dbDriver} 드라이버 없음", true);
    }
    $c->newLine();

    // ── 5. 보안 설정 ──
    $c->info('── 보안 설정 ──');
    $check('display_errors', !((bool) ini_get('display_errors')),
        'Off', 'On (프로덕션에서 Off 필수)');
    $check('expose_php', !((bool) ini_get('expose_php')),
        'Off', 'On (Off 권장)');
    $check('allow_url_include', !((bool) ini_get('allow_url_include')),
        'Off', 'On (Off 필수)', true);
    $check('session.cookie_httponly', (bool) ini_get('session.cookie_httponly'),
        'On', 'Off (On 권장)');
    $check('session.use_strict_mode', (bool) ini_get('session.use_strict_mode'),
        'On', 'Off (On 권장)');
    $c->newLine();

    // ── 6. 설정 기본값 경고 ──
    $c->info('── CatPHP 설정 검증 ──');
    $authSecret = config('auth.secret') ?: '';
    $check('auth.secret', $authSecret !== '' && $authSecret !== 'CHANGE_ME_TO_RANDOM_SECRET',
        '설정됨', '기본값 사용 중 (변경 필수!)', true);

    $encryptKey = config('encrypt.key') ?: '';
    $check('encrypt.key', $encryptKey !== '' && $encryptKey !== 'base64:CHANGE_ME_TO_RANDOM_32_BYTES',
        '설정됨', '기본값 사용 중 (변경 필수!)', true);

    $appKey = config('app.key') ?: '';
    $check('app.key', $appKey !== '' && $appKey !== 'base64:CHANGE_ME_TO_RANDOM_32_BYTES',
        '설정됨', '기본값 사용 중 (변경 필수!)', true);

    $debug = (bool) config('app.debug');
    $check('app.debug', !$debug, 'false', 'true (프로덕션에서 false 권장)');
    $c->newLine();

    // ── 7. 스토리지 디렉토리 ──
    $c->info('── 스토리지 디렉토리 ──');
    $storageDirs = [
        'cache'    => config('cache.path'),
        'logs'     => config('log.path'),
        'firewall' => config('firewall.path'),
        'rate'     => config('rate.path'),
    ];
    foreach ($storageDirs as $name => $dir) {
        if ($dir === null) {
            $check("storage/{$name}", false, '', '경로 미설정');
            continue;
        }
        $exists = is_dir($dir);
        $writable = $exists && is_writable($dir);
        if ($writable) {
            $check("storage/{$name}", true, '쓰기 가능', '');
        } elseif ($exists) {
            $check("storage/{$name}", false, '', '쓰기 불가 (권한 확인)', true);
        } else {
            $check("storage/{$name}", false, '', '디렉토리 없음 (자동 생성 예정)');
        }
    }
    $c->newLine();

    // ── 결과 요약 ──
    $c->hr('═', 40);
    $total = $pass + $warn + $fail;
    $c->info("검사 항목: {$total}개");
    $c->success("통과: {$pass}개");
    if ($warn > 0) $c->warn("경고: {$warn}개");
    if ($fail > 0) $c->error("실패: {$fail}개");
    $c->newLine();

    if ($fail === 0 && $warn === 0) {
        $c->success('모든 항목 통과! 프로덕션 배포 준비 완료.');
    } elseif ($fail === 0) {
        $c->warn('필수 항목은 통과했지만 경고 항목을 확인하세요.');
    } else {
        $c->error('실패 항목을 반드시 해결한 후 배포하세요.');
    }
});

// ── setup / key:generate ──

cli()->command('setup', '인터랙티브 config 설정', function () {
    $c = cli();
    $configFile = __DIR__ . '/config/app.php';

    if (!is_file($configFile)) {
        $c->error('config/app.php 파일이 없습니다.');
        return;
    }

    $cfg = require $configFile;
    $c->info('═══ CatPHP Setup ═══');
    $c->info('각 섹션의 사용 여부를 선택한 뒤 상세 설정을 진행합니다.');
    $c->info('Enter 키를 누르면 [기본값]을 사용합니다.');

    // ════════════════════════════════════════
    // 1. 앱 (필수)
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [1/13] 앱 설정 (필수) ──');
    $cfg['app']['debug'] = $c->confirm('디버그 모드 활성화?');
    $cfg['app']['timezone'] = $c->prompt('타임존', $cfg['app']['timezone'] ?? 'Asia/Seoul');

    if (
        ($cfg['app']['key'] ?? '') === 'base64:CHANGE_ME_TO_RANDOM_32_BYTES'
        || $c->confirm('app.key 자동 생성?')
    ) {
        $cfg['app']['key'] = 'base64:' . base64_encode(random_bytes(32));
        $c->success('app.key 생성됨');
    }

    // ════════════════════════════════════════
    // 2. 데이터베이스
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [2/13] 데이터베이스 ──');
    if ($c->confirm('데이터베이스 사용?', true)) {
        $dbChoice = $c->choice('DB 드라이버', ['mysql', 'pgsql', 'sqlite']);
        $cfg['db']['driver'] = $dbChoice ?? ($cfg['db']['driver'] ?? 'mysql');

        if ($cfg['db']['driver'] === 'sqlite') {
            $defaultDbPath = __DIR__ . '/storage/database.sqlite';
            $cfg['db']['dbname'] = $c->prompt('SQLite 파일 경로', $cfg['db']['dbname'] ?? $defaultDbPath);
            $cfg['db']['host'] = '';
            $cfg['db']['port'] = 0;
            $cfg['db']['user'] = '';
            $cfg['db']['pass'] = '';
            $cfg['db']['charset'] = '';

            // SQLite 상세 옵션
            $journal = $c->choice('저널 모드', ['wal', 'delete', 'truncate', 'memory']);
            $cfg['db']['journal_mode'] = $journal ?? 'wal';
            $cfg['db']['foreign_keys'] = $c->confirm('외래 키 제약 활성화?', true);
            $cfg['db']['busy_timeout'] = (int) $c->prompt('Busy Timeout (ms)', (string) ($cfg['db']['busy_timeout'] ?? 5000));
            $cfg['db']['synchronous'] = $c->choice('Synchronous 레벨', ['normal', 'full', 'off']);
            $cfg['db']['synchronous'] = $cfg['db']['synchronous'] ?? 'normal';

            // SQLite 파일 자동 생성 안내
            if (!is_file($cfg['db']['dbname'])) {
                $dir = dirname($cfg['db']['dbname']);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                touch($cfg['db']['dbname']);
                $c->success("SQLite 파일 생성: {$cfg['db']['dbname']}");
            }
        } else {
            $defaultPort = match ($cfg['db']['driver']) {
                'pgsql' => 5432,
                default => 3306,
            };
            $defaultUser = match ($cfg['db']['driver']) {
                'pgsql' => 'postgres',
                default => 'root',
            };
            $defaultCharset = match ($cfg['db']['driver']) {
                'pgsql' => 'utf8',
                default => 'utf8mb4',
            };
            $cfg['db']['host'] = $c->prompt('DB 호스트', $cfg['db']['host'] ?? '127.0.0.1');
            $cfg['db']['port'] = (int) $c->prompt('DB 포트', (string) $defaultPort);
            $cfg['db']['dbname'] = $c->prompt('DB 이름', $cfg['db']['dbname'] ?? 'catphp');
            $cfg['db']['user'] = $c->prompt('DB 사용자', $defaultUser);
            $cfg['db']['pass'] = $c->prompt('DB 비밀번호', '');
            $cfg['db']['charset'] = $c->prompt('DB 문자셋', $cfg['db']['charset'] ?? $defaultCharset);

            // 연결 테스트 (선택)
            if ($c->confirm('DB 연결 테스트?')) {
                try {
                    $dsn = $cfg['db']['driver'] . ':host=' . $cfg['db']['host']
                         . ';port=' . $cfg['db']['port']
                         . ';dbname=' . $cfg['db']['dbname']
                         . ';charset=' . $cfg['db']['charset'];
                    $pdo = new \PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
                        \PDO::ATTR_TIMEOUT => 5,
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    ]);
                    $ver = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
                    $c->success("연결 성공! (서버: {$cfg['db']['driver']} {$ver})");
                    $pdo = null;
                } catch (\PDOException $e) {
                    $c->error('연결 실패: ' . $e->getMessage());
                    if ($c->confirm('DB 설정을 다시 입력?')) {
                        $c->warn('setup을 다시 실행해주세요.');
                        return;
                    }
                }
            }
        }
    } else {
        $c->warn('→ DB 건너뜀 (기존 설정 유지)');
    }

    // ════════════════════════════════════════
    // 3. 인증/암호화
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [3/13] 인증/암호화 ──');
    if ($c->confirm('인증/암호화 설정?', true)) {
        if (
            ($cfg['auth']['secret'] ?? '') === 'CHANGE_ME_TO_RANDOM_SECRET'
            || $c->confirm('auth.secret 자동 생성?')
        ) {
            $cfg['auth']['secret'] = bin2hex(random_bytes(32));
            $c->success('auth.secret 생성됨');
        }
        $cfg['auth']['ttl'] = (int) $c->prompt('JWT TTL (초)', (string) ($cfg['auth']['ttl'] ?? 86400));

        $algoChoice = $c->choice('비밀번호 해싱 알고리즘', ['Argon2id', 'Bcrypt']);
        $cfg['auth']['algo'] = $algoChoice ?? ($cfg['auth']['algo'] ?? 'Argon2id');

        if (
            ($cfg['encrypt']['key'] ?? '') === 'base64:CHANGE_ME_TO_RANDOM_32_BYTES'
            || $c->confirm('encrypt.key 자동 생성?')
        ) {
            $cfg['encrypt']['key'] = 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
            $c->success('encrypt.key 생성됨');
        }
    } else {
        $c->warn('→ 인증/암호화 건너뜀');
    }

    // ════════════════════════════════════════
    // 4. 세션
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [4/13] 세션 ──');
    if ($c->confirm('세션 설정?', true)) {
        $cfg['session']['lifetime'] = (int) $c->prompt('세션 수명 (초)', (string) ($cfg['session']['lifetime'] ?? 7200));
        $cfg['session']['secure'] = $c->confirm('Secure 쿠키? (HTTPS 전용)');
        $sameSite = $c->choice('SameSite 정책', ['Lax', 'Strict', 'None']);
        $cfg['session']['samesite'] = $sameSite ?? ($cfg['session']['samesite'] ?? 'Lax');
    } else {
        $c->warn('→ 세션 건너뜀');
    }

    // ════════════════════════════════════════
    // 5. CORS
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [5/13] CORS ──');
    if ($c->confirm('CORS 설정?', true)) {
        $originsInput = $c->prompt('허용 Origin (쉼표 구분, * = 전체)', implode(', ', $cfg['cors']['origins'] ?? ['*']));
        $cfg['cors']['origins'] = array_map('trim', explode(',', $originsInput));
        $methodsInput = $c->prompt('허용 메서드 (쉼표 구분)', implode(', ', $cfg['cors']['methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']));
        $cfg['cors']['methods'] = array_map('trim', explode(',', $methodsInput));
    } else {
        $c->warn('→ CORS 건너뜀');
    }

    // ════════════════════════════════════════
    // 6. 업로드
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [6/13] 업로드 ──');
    if ($c->confirm('업로드 설정?', true)) {
        $cfg['upload']['max_size'] = $c->prompt('최대 업로드 크기', $cfg['upload']['max_size'] ?? '10M');
        $allowedInput = $c->prompt('허용 확장자 (쉼표 구분)', implode(', ', $cfg['upload']['allowed'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip']));
        $cfg['upload']['allowed'] = array_map('trim', explode(',', $allowedInput));
    } else {
        $c->warn('→ 업로드 건너뜀');
    }

    // ════════════════════════════════════════
    // 7. Telegram
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [7/13] Telegram ──');
    if ($c->confirm('Telegram 알림 사용?')) {
        $cfg['telegram']['bot_token'] = $c->prompt('Bot Token', $cfg['telegram']['bot_token'] ?? '');
        $cfg['telegram']['chat_id'] = $c->prompt('Chat ID', $cfg['telegram']['chat_id'] ?? '');
        $cfg['telegram']['admin_chat'] = $c->prompt('Admin Chat ID (선택)', $cfg['telegram']['admin_chat'] ?? '');
    } else {
        $c->warn('→ Telegram 건너뜀');
    }

    // ════════════════════════════════════════
    // 8. 다국어 (Geo)
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [8/13] 다국어 ──');
    if ($c->confirm('다국어 설정?', true)) {
        $cfg['geo']['default'] = $c->prompt('기본 언어', $cfg['geo']['default'] ?? 'ko');
        $supportedInput = $c->prompt('지원 언어 (쉼표 구분)', implode(', ', $cfg['geo']['supported'] ?? ['ko', 'en']));
        $cfg['geo']['supported'] = array_map('trim', explode(',', $supportedInput));
    } else {
        $c->warn('→ 다국어 건너뜀');
    }

    // ════════════════════════════════════════
    // 9. Redis
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [9/13] Redis ──');
    if ($c->confirm('Redis 사용?')) {
        if (!extension_loaded('redis')) {
            $c->warn('⚠ ext-redis 미설치. 런타임에 에러 발생 가능');
        }
        $cfg['redis']['host'] = $c->prompt('Redis 호스트', $cfg['redis']['host'] ?? '127.0.0.1');
        $cfg['redis']['port'] = (int) $c->prompt('Redis 포트', (string) ($cfg['redis']['port'] ?? 6379));
        $cfg['redis']['password'] = $c->prompt('Redis 비밀번호 (없으면 빈칸)', $cfg['redis']['password'] ?? '');
        $cfg['redis']['database'] = (int) $c->prompt('Redis DB 번호', (string) ($cfg['redis']['database'] ?? 0));
        $cfg['redis']['prefix'] = $c->prompt('키 접두사', $cfg['redis']['prefix'] ?? 'catphp:');

        // Redis 연결 테스트
        if (extension_loaded('redis') && $c->confirm('Redis 연결 테스트?')) {
            try {
                $r = new \Redis();
                $r->connect($cfg['redis']['host'], $cfg['redis']['port'], 2.0);
                if (($cfg['redis']['password'] ?? '') !== '') {
                    $r->auth($cfg['redis']['password']);
                }
                $c->success('Redis 연결 성공! (v' . $r->info('server')['redis_version'] . ')');
                $r->close();
            } catch (\Throwable $e) {
                $c->error('Redis 연결 실패: ' . $e->getMessage());
            }
        }
    } else {
        $c->warn('→ Redis 건너뜀');
    }

    // ════════════════════════════════════════
    // 10. Mail (SMTP)
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [10/13] Mail (SMTP) ──');
    if ($c->confirm('Mail(SMTP) 사용?')) {
        $cfg['mail']['host'] = $c->prompt('SMTP 호스트', $cfg['mail']['host'] ?? 'localhost');
        $cfg['mail']['port'] = (int) $c->prompt('SMTP 포트', (string) ($cfg['mail']['port'] ?? 587));
        $cfg['mail']['username'] = $c->prompt('SMTP 사용자', $cfg['mail']['username'] ?? '');
        $cfg['mail']['password'] = $c->prompt('SMTP 비밀번호', $cfg['mail']['password'] ?? '');
        $encChoice = $c->choice('암호화', ['tls', 'ssl', 'none']);
        $cfg['mail']['encryption'] = $encChoice ?? ($cfg['mail']['encryption'] ?? 'tls');
        $cfg['mail']['from_email'] = $c->prompt('발신 이메일', $cfg['mail']['from_email'] ?? '');
        $cfg['mail']['from_name'] = $c->prompt('발신자 이름', $cfg['mail']['from_name'] ?? 'CatPHP');
    } else {
        $c->warn('→ Mail 건너뜀');
    }

    // ════════════════════════════════════════
    // 11. Queue
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [11/13] 큐 ──');
    if ($c->confirm('큐 사용?')) {
        $queueDriver = $c->choice('큐 드라이버', ['redis', 'db']);
        $cfg['queue']['driver'] = $queueDriver ?? ($cfg['queue']['driver'] ?? 'redis');
        $cfg['queue']['default'] = $c->prompt('기본 큐 이름', $cfg['queue']['default'] ?? 'default');

        if ($cfg['queue']['driver'] === 'redis' && !extension_loaded('redis')) {
            $c->warn('⚠ 큐 드라이버가 redis이지만 ext-redis 미설치');
        }
    } else {
        $c->warn('→ 큐 건너뜀');
    }

    // ════════════════════════════════════════
    // 12. Storage
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [12/13] 스토리지 ──');
    if ($c->confirm('스토리지 설정?', true)) {
        $storageDisk = $c->choice('기본 디스크', ['local', 'public', 's3']);
        $cfg['storage']['default'] = $storageDisk ?? ($cfg['storage']['default'] ?? 'local');

        if ($storageDisk === 's3') {
            $c->info('S3 설정');
            $s3 = $cfg['storage']['disks']['s3'] ?? [];
            $s3['driver'] = 's3';
            $s3['key'] = $c->prompt('AWS Access Key', $s3['key'] ?? '');
            $s3['secret'] = $c->prompt('AWS Secret Key', $s3['secret'] ?? '');
            $s3['region'] = $c->prompt('AWS Region', $s3['region'] ?? 'ap-northeast-2');
            $s3['bucket'] = $c->prompt('S3 Bucket', $s3['bucket'] ?? '');
            $s3['endpoint'] = $c->prompt('커스텀 엔드포인트 (없으면 빈칸)', $s3['endpoint'] ?? '');
            if ($s3['endpoint'] === '') {
                unset($s3['endpoint']);
            }
            $cfg['storage']['disks']['s3'] = $s3;
        }
    } else {
        $c->warn('→ 스토리지 건너뜀');
    }

    // ════════════════════════════════════════
    // 13. 해싱
    // ════════════════════════════════════════
    $c->newLine();
    $c->info('── [13/13] 해싱 ──');
    if ($c->confirm('해싱 알고리즘 변경?')) {
        $hashAlgo = $c->choice('기본 해시 알고리즘', ['sha256', 'sha384', 'sha512', 'xxh3']);
        $cfg['hash']['algo'] = $hashAlgo ?? ($cfg['hash']['algo'] ?? 'sha256');
    } else {
        $c->warn('→ 해싱 건너뜀 (기본: ' . ($cfg['hash']['algo'] ?? 'sha256') . ')');
    }

    // ════════════════════════════════════════
    // 설정 요약 + 저장
    // ════════════════════════════════════════
    $c->newLine();
    $c->hr('═', 40);
    $c->info('설정 요약:');
    $c->info("  앱        : debug=" . ($cfg['app']['debug'] ? 'true' : 'false') . ", tz={$cfg['app']['timezone']}");
    $c->info("  DB        : {$cfg['db']['driver']}" . ($cfg['db']['driver'] === 'sqlite' ? " ({$cfg['db']['dbname']})" : " → {$cfg['db']['host']}:{$cfg['db']['port']}/{$cfg['db']['dbname']}"));
    $c->info("  인증      : algo={$cfg['auth']['algo']}, ttl={$cfg['auth']['ttl']}s");
    $c->info("  세션      : {$cfg['session']['lifetime']}s, secure=" . ($cfg['session']['secure'] ? 'true' : 'false') . ", samesite={$cfg['session']['samesite']}");
    $c->info("  Redis     : " . (($cfg['redis']['host'] ?? '') !== '' ? "{$cfg['redis']['host']}:{$cfg['redis']['port']}" : '미사용'));
    $c->info("  Mail      : " . (($cfg['mail']['host'] ?? '') !== '' && ($cfg['mail']['host'] ?? '') !== 'localhost' ? "{$cfg['mail']['host']}:{$cfg['mail']['port']}" : (($cfg['mail']['from_email'] ?? '') !== '' ? $cfg['mail']['from_email'] : '미설정')));
    $c->info("  큐        : {$cfg['queue']['driver']}");
    $c->info("  스토리지  : {$cfg['storage']['default']}");
    $c->hr('═', 40);
    $c->newLine();

    if (!$c->confirm('config/app.php 저장?')) {
        $c->warn('취소됨.');
        return;
    }

    $output = generateConfigPhp($cfg);
    $backup = $configFile . '.bak.' . date('Ymd_His');
    copy($configFile, $backup);
    file_put_contents($configFile, $output, LOCK_EX);

    $c->success("설정 저장 완료!");
    $c->info("백업: {$backup}");

    // 스토리지 디렉토리 자동 생성
    $dirs = [
        $cfg['cache']['path'] ?? null,
        $cfg['log']['path'] ?? null,
        $cfg['firewall']['path'] ?? null,
        $cfg['rate']['path'] ?? null,
        $cfg['storage']['disks']['local']['root'] ?? null,
        $cfg['storage']['disks']['public']['root'] ?? null,
    ];
    foreach ($dirs as $dir) {
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
            $c->success("디렉토리 생성: {$dir}");
        }
    }
});

// ── migrate ──

cli()->command('migrate', '마이그레이션 실행', function () {
    $executed = migration()->run();
    if ($executed === []) {
        cli()->info('실행할 마이그레이션 없음');
        return;
    }
    foreach ($executed as $name) {
        cli()->success("✓ {$name}");
    }
    cli()->info(count($executed) . '개 마이그레이션 실행 완료');
});

cli()->command('migrate:rollback', '마지막 배치 롤백', function () {
    $steps = (int) (cli()->option('steps', '1') ?: '1');
    $rolled = migration()->rollback($steps);
    if ($rolled === []) {
        cli()->info('롤백할 마이그레이션 없음');
        return;
    }
    foreach ($rolled as $name) {
        cli()->warn("↩ {$name}");
    }
    cli()->info(count($rolled) . '개 롤백 완료');
});

cli()->command('migrate:status', '마이그레이션 상태', function () {
    $list = migration()->status();
    if ($list === []) {
        cli()->info('마이그레이션 파일 없음');
        return;
    }
    cli()->table(['Name', 'Batch', 'Status'], array_map(fn($m) => [
        $m['name'],
        $m['batch'] !== null ? (string) $m['batch'] : '-',
        $m['status'],
    ], $list));
});

cli()->command('migrate:create', '마이그레이션 파일 생성', function () {
    $name = cli()->arg(0);
    if (!$name) {
        cli()->error('사용법: php cli.php migrate:create <name> [--table=테이블] [--alter]');
        return;
    }
    $table = (string) cli()->option('table', '');
    $type = cli()->option('alter') ? 'alter' : 'create';
    $path = migration()->create($name, $table, $type);
    cli()->success("생성: {$path}");
});

cli()->command('migrate:fresh', '전체 롤백 + 재실행', function () {
    if (!cli()->confirm('모든 테이블을 삭제하고 재생성합니다. 계속?')) {
        cli()->warn('취소됨');
        return;
    }
    $executed = migration()->fresh();
    foreach ($executed as $name) {
        cli()->success("✓ {$name}");
    }
    cli()->info(count($executed) . '개 마이그레이션 재실행 완료');
});

cli()->command('config:init', '모든 도구의 기본 config/app.php 생성', function () {
    $c = cli();
    $configFile = __DIR__ . '/config/app.php';
    $configDir  = __DIR__ . '/config';

    // ── 전체 도구 기본 config 정의 (33개 섹션) ──
    $defaults = [
        // ── 코어 ──
        'app'       => [
            'debug'    => true,
            'timezone' => 'Asia/Seoul',
            'key'      => 'base64:CHANGE_ME_RUN_php_cli_key_generate',
        ],
        'db'        => [
            'driver'  => 'mysql',
            'host'    => '127.0.0.1',
            'port'    => 3306,
            'dbname'  => 'catphp',
            'user'    => 'root',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
        'cache'     => [
            'path' => $configDir . '/../storage/cache',
            'ttl'  => 3600,
        ],
        'log'       => [
            'path'  => $configDir . '/../storage/logs',
            'level' => 'debug',
        ],

        // ── 보안 ──
        'auth'      => [
            'secret' => 'CHANGE_ME_RUN_php_cli_key_generate',
            'ttl'    => 86400,
            'algo'   => 'Argon2id',
        ],
        'encrypt'   => [
            'key' => 'base64:CHANGE_ME_RUN_php_cli_key_generate',
        ],
        'firewall'  => [
            'path' => $configDir . '/../storage/firewall',
        ],
        'ip'        => [
            'provider'        => 'api',
            'mmdb_path'       => null,
            'cache_ttl'       => 86400,
            'trusted_proxies' => [],
        ],
        'guard'     => [
            'auto_ban'      => false,
            'max_body_size' => '10M',
        ],
        'perm'      => [
            'roles' => ['admin', 'editor', 'user'],
        ],

        // ── 네트워크 ──
        'cors'      => [
            'origins'  => ['*'],
            'methods'  => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'headers'  => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
            'max_age'  => 86400,
        ],
        'rate'      => [
            'storage' => 'cache',
            'path'    => $configDir . '/../storage/rate',
        ],

        // ── 데이터 ──
        'session'   => [
            'lifetime' => 7200,
            'path'     => '/',
            'secure'   => false,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ],
        'cookie'    => [
            'encrypt'  => true,
            'samesite' => 'Lax',
            'secure'   => false,
        ],
        'upload'    => [
            'max_size' => '10M',
            'allowed'  => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'zip'],
        ],
        'view'      => [
            'path' => $configDir . '/../Public/views',
        ],
        'user'      => [
            'table'       => 'users',
            'primary_key' => 'id',
            'hidden'      => ['password'],
        ],
        'migration' => [
            'path'  => $configDir . '/../migrations',
            'table' => 'migrations',
        ],

        // ── 웹/CMS ──
        'image'     => [
            'driver'  => 'gd',
            'quality' => 85,
        ],
        'search'    => [
            'driver'    => 'fulltext',
            'cache_ttl' => 300,
        ],
        'geo'       => [
            'default'   => 'ko',
            'supported' => ['ko', 'en'],
            'path'      => $configDir . '/../lang',
        ],
        'meta'      => [],
        'slug'      => [],
        'flash'     => [],
        'feed'      => [
            'limit'     => 20,
            'cache_ttl' => 3600,
        ],
        'spider'    => [
            'user_agent' => 'CatPHP Spider/1.0',
            'timeout'    => 30,
        ],
        'telegram'  => [
            'bot_token'  => '',
            'chat_id'    => '',
            'admin_chat' => '',
        ],

        // ── 인프라 ──
        'redis'     => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'password' => '',
            'database' => 0,
            'prefix'   => 'catphp:',
            'timeout'  => 2.0,
        ],
        'mail'      => [
            'host'       => 'localhost',
            'port'       => 587,
            'username'   => '',
            'password'   => '',
            'encryption' => 'tls',
            'from_email' => '',
            'from_name'  => 'CatPHP',
        ],
        'queue'     => [
            'driver'  => 'redis',
            'default' => 'default',
        ],
        'storage'   => [
            'default' => 'local',
            'disks'   => [
                'local'  => ['driver' => 'local', 'root' => $configDir . '/../storage/app'],
                'public' => ['driver' => 'local', 'root' => $configDir . '/../Public/uploads', 'url' => '/uploads'],
            ],
        ],
        'schedule'  => [],
        'hash'      => [
            'algo' => 'sha256',
        ],
        'captcha'   => [
            'width'       => 150,
            'height'      => 50,
            'length'      => 5,
            'charset'     => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
            'session_key' => '_captcha',
        ],
        'faker'     => [
            'locale' => 'ko',
        ],
        'response'  => [
            'allowed_hosts' => [],
        ],

        // ── 관리/연동 ──
        'sitemap'   => [
            'base_url'  => '',
            'cache_ttl' => 3600,
        ],
        'backup'    => [
            'path'      => $configDir . '/../storage/backup',
            'keep_days' => 30,
            'compress'  => false,
        ],
        'webhook'   => [
            'secret'      => '',
            'timeout'     => 10,
            'retry'       => 0,
            'retry_delay' => 1,
            'log'         => false,
        ],
        'dbview'    => [],
    ];

    // ── 모드 결정: 신규 생성 vs 병합 ──
    $isNew = !is_file($configFile);
    $merged = 0;

    if ($isNew) {
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        $cfg = $defaults;
        $c->info('새 config/app.php 생성 중...');
    } else {
        $cfg = require $configFile;
        $c->info('기존 config/app.php에 누락 키 병합 중...');

        // 누락된 섹션/키 추가 (기존 값 보존)
        foreach ($defaults as $section => $keys) {
            if (!isset($cfg[$section])) {
                $cfg[$section] = $keys;
                $merged++;
                $c->success("  + [{$section}] 섹션 추가");
            } elseif (is_array($keys)) {
                foreach ($keys as $k => $v) {
                    if (!array_key_exists($k, $cfg[$section])) {
                        $cfg[$section][$k] = $v;
                        $merged++;
                        $c->success("  + [{$section}.{$k}] 키 추가");
                    }
                }
            }
        }
    }

    // ── 보안 키 자동 생성 (플레이스홀더인 경우만) ──
    $keysGenerated = 0;
    $placeholder = 'CHANGE_ME_RUN_php_cli_key_generate';

    if (str_contains($cfg['app']['key'] ?? '', $placeholder)) {
        $cfg['app']['key'] = 'base64:' . base64_encode(random_bytes(32));
        $keysGenerated++;
    }
    if (($cfg['auth']['secret'] ?? '') === $placeholder || ($cfg['auth']['secret'] ?? '') === '') {
        $cfg['auth']['secret'] = bin2hex(random_bytes(32));
        $keysGenerated++;
    }
    if (str_contains($cfg['encrypt']['key'] ?? '', $placeholder)) {
        $cfg['encrypt']['key'] = 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $keysGenerated++;
    }

    // ── 파일 저장 ──
    if (!$isNew) {
        $backup = $configFile . '.bak.' . date('Ymd_His');
        copy($configFile, $backup);
        $c->info("백업: {$backup}");
    }

    file_put_contents($configFile, generateConfigPhp($cfg), LOCK_EX);

    // ── 스토리지 디렉토리 자동 생성 ──
    $dirs = array_filter([
        'cache'    => $cfg['cache']['path'] ?? null,
        'logs'     => $cfg['log']['path'] ?? null,
        'firewall' => $cfg['firewall']['path'] ?? null,
        'rate'     => $cfg['rate']['path'] ?? null,
        'storage'  => $cfg['storage']['disks']['local']['root'] ?? null,
        'uploads'  => $cfg['storage']['disks']['public']['root'] ?? null,
        'migrate'  => $cfg['migration']['path'] ?? null,
    ]);

    $dirsCreated = 0;
    foreach ($dirs as $name => $dir) {
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
            $dirsCreated++;
        }
    }

    // ── 결과 요약 ──
    $c->newLine();
    $c->hr('═', 40);
    $totalSections = count(array_filter($cfg, 'is_array'));
    $c->success($isNew ? 'config/app.php 생성 완료!' : 'config/app.php 병합 완료!');
    $c->info("  설정 섹션   : {$totalSections}개 (전체 도구)");
    if ($keysGenerated > 0) {
        $c->success("  보안 키 생성 : {$keysGenerated}개 (app.key, auth.secret, encrypt.key)");
    }
    if ($merged > 0) {
        $c->success("  누락 키 추가 : {$merged}개");
    }
    if ($dirsCreated > 0) {
        $c->success("  디렉토리 생성: {$dirsCreated}개");
    }
    $c->newLine();

    // ── 도구별 config 키 목록 표시 ──
    $toolTable = [];
    foreach ($defaults as $section => $keys) {
        if ($keys === []) {
            $toolTable[] = [$section, '(설정 불필요)', '—'];
            continue;
        }
        $keyNames = implode(', ', array_keys($keys));
        $status = isset($cfg[$section]) ? '✓' : '✗';
        $toolTable[] = [$section, $keyNames, $status];
    }
    $c->table(['섹션', '키', '상태'], $toolTable);

    $c->newLine();
    $c->info('다음 단계:');
    $c->info('  1. config/app.php를 열어 DB, SMTP, Redis 등 환경에 맞게 수정');
    $c->info('  2. php cli.php check:env 로 환경 진단');
    $c->info('  3. php cli.php setup 으로 인터랙티브 설정');
});

cli()->command('key:generate', '보안 키 자동 생성 (app.key, auth.secret, encrypt.key)', function () {
    $c = cli();
    $configFile = __DIR__ . '/config/app.php';
    $cfg = require $configFile;

    $cfg['app']['key'] = 'base64:' . base64_encode(random_bytes(32));
    $cfg['auth']['secret'] = bin2hex(random_bytes(32));
    $cfg['encrypt']['key'] = 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

    $backup = $configFile . '.bak.' . date('Ymd_His');
    copy($configFile, $backup);
    file_put_contents($configFile, generateConfigPhp($cfg), LOCK_EX);

    $c->success('app.key    생성됨');
    $c->success('auth.secret 생성됨');
    $c->success('encrypt.key 생성됨');
    $c->info("백업: {$backup}");
});

/**
 * config 배열 → config/app.php 소스코드 변환
 *
 * @param array<string, mixed> $cfg
 */
function generateConfigPhp(array $cfg): string
{
    $lines = ["<?php declare(strict_types=1);\n", "return ["];

    foreach ($cfg as $section => $values) {
        if (!is_array($values)) {
            continue;
        }
        $parts = [];
        foreach ($values as $k => $v) {
            $parts[] = "'" . addslashes((string) $k) . "' => " . exportValue($v);
        }
        $inner = implode(', ', $parts);
        $pad = str_pad("'{$section}'", 12);
        $lines[] = "    {$pad}=> [{$inner}],";
    }

    $lines[] = "];\n";
    return implode("\n", $lines);
}

/**
 * PHP 값 → 소스코드 문자열 변환
 *
 * config/app.php 내 __DIR__ 은 config/ 디렉토리를 가리키므로,
 * 프로젝트 루트 기준 경로를 __DIR__ . '/../...' 형태로 변환한다.
 */
function exportValue(mixed $v): string
{
    if (is_null($v)) {
        return 'null';
    }
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    if (is_int($v)) {
        return (string) $v;
    }
    if (is_float($v)) {
        return (string) $v;
    }
    if (is_string($v)) {
        // config/app.php 의 __DIR__ = config/ 디렉토리
        $configDir = realpath(__DIR__ . '/config') ?: (__DIR__ . '/config');
        $normalV = str_replace('\\', '/', $v);
        $normalBase = str_replace('\\', '/', $configDir);
        if (str_starts_with($normalV, $normalBase . '/')) {
            $relative = substr($normalV, strlen($normalBase));
            return "__DIR__ . '" . $relative . "'";
        }
        // 프로젝트 루트 기준 경로 → __DIR__ . '/..' + 상대경로
        $rootDir = realpath(__DIR__) ?: __DIR__;
        $normalRoot = str_replace('\\', '/', $rootDir);
        if (str_starts_with($normalV, $normalRoot . '/')) {
            $relative = substr($normalV, strlen($normalRoot));
            return "__DIR__ . '/.." . $relative . "'";
        }
        return "'" . addslashes($v) . "'";
    }
    if (is_array($v)) {
        if (array_is_list($v)) {
            $items = array_map(fn($item) => exportValue($item), $v);
            return '[' . implode(', ', $items) . ']';
        }
        $items = [];
        foreach ($v as $key => $val) {
            $items[] = "'" . addslashes((string) $key) . "' => " . exportValue($val);
        }
        return '[' . implode(', ', $items) . ']';
    }
    return "'" . addslashes((string) $v) . "'";
}

// ── queue / schedule ──

cli()->command('queue:work', '큐 워커 실행', function () {
    if (config('queue.driver') === 'redis' && !extension_loaded('redis')) {
        cli()->warn('ext-redis 미설치: Redis 큐 드라이버를 사용할 수 없습니다.');
    }
    $queue = cli()->option('queue', 'default');
    $sleep = (int) cli()->option('sleep', '3');
    $maxJobs = (int) cli()->option('max-jobs', '0');
    cli()->info("큐 워커 시작: {$queue} (sleep={$sleep}s)");
    queue()->work($queue, $sleep, $maxJobs);
});

cli()->command('queue:size', '큐 대기 작업 수', function () {
    if (config('queue.driver') === 'redis' && !extension_loaded('redis')) {
        cli()->warn('ext-redis 미설치: Redis 큐 드라이버를 사용할 수 없습니다.');
    }
    $queue = cli()->option('queue', 'default');
    cli()->info("큐 [{$queue}] 대기: " . queue()->size($queue) . '개');
});

cli()->command('queue:clear', '큐 비우기', function () {
    if (config('queue.driver') === 'redis' && !extension_loaded('redis')) {
        cli()->warn('ext-redis 미설치: Redis 큐 드라이버를 사용할 수 없습니다.');
    }
    $queue = cli()->option('queue', 'default');
    $count = queue()->clear($queue);
    cli()->success("큐 [{$queue}] {$count}개 삭제됨");
});

cli()->command('queue:failed', '실패한 작업 목록', function () {
    $failed = queue()->failed();
    if (empty($failed)) {
        cli()->info('실패한 작업 없음');
        return;
    }
    cli()->table(['ID', 'Job', 'Error', 'Failed At'], array_map(fn($f) => [
        $f['id'] ?? '-',
        $f['job'] ?? '-',
        mb_substr($f['error'] ?? '-', 0, 40),
        date('Y-m-d H:i', $f['failed_at'] ?? 0),
    ], $failed));
});

cli()->command('queue:retry', '실패한 작업 재시도', function () {
    $id = cli()->arg(0);
    if (!$id) {
        cli()->error('작업 ID를 입력하세요: php cli.php queue:retry <id>');
        return;
    }
    queue()->retryFailed($id)
        ? cli()->success("재시도 등록: {$id}")
        : cli()->error("작업을 찾을 수 없음: {$id}");
});

cli()->command('schedule:run', '스케줄 실행 (크론탭용)', function () {
    $count = schedule()->run();
    if ($count > 0) {
        cli()->success("{$count}개 태스크 실행 완료");
    } else {
        cli()->info('실행할 스케줄 없음');
    }
});

cli()->command('schedule:list', '등록된 스케줄 목록', function () {
    $tasks = schedule()->list();
    if (empty($tasks)) {
        cli()->info('등록된 스케줄 없음');
        return;
    }
    cli()->table(['Expression', 'Type', 'Description'], $tasks);
});

cli()->command('test',           '전체 테스트 실행',      function() {
    passthru('php ' . escapeshellarg(__DIR__ . '/tests/run.php') . ' all', $code);
    exit($code);
});
cli()->command('test:unit',      '단위 테스트 실행',      function() {
    passthru('php ' . escapeshellarg(__DIR__ . '/tests/run.php') . ' unit', $code);
    exit($code);
});
cli()->command('test:integration','통합 테스트 실행',      function() {
    passthru('php ' . escapeshellarg(__DIR__ . '/tests/run.php') . ' integration', $code);
    exit($code);
});

cli()->command('release', '릴리즈 빌드 (버전 범프 + 체크 + CHANGELOG + ZIP 생성)', function () {
    $c = cli();
    $coreFile = __DIR__ . '/catphp/catphp.php';
    $changelogFile = __DIR__ . '/CHANGELOG.md';
    $releaseDir = __DIR__ . '/releases';

    // ZIP 확장 체크
    if (!class_exists('ZipArchive')) {
        $c->error('php-zip 확장이 필요합니다. php.ini에서 extension=zip을 활성화하세요.');
        return;
    }

    // ── 1. 현재 버전 읽기 ──
    $coreContent = file_get_contents($coreFile);
    if (!preg_match("/define\('CATPHP_VERSION',\s*'([^']+)'\)/", $coreContent, $m)) {
        $c->error('catphp.php에서 CATPHP_VERSION을 찾을 수 없습니다.');
        return;
    }
    $currentVersion = $m[1];
    $c->info("현재 버전: v{$currentVersion}");

    // ── 2. 버전 범프 선택 ──
    [$major, $minor, $patch] = array_map('intval', explode('.', $currentVersion));
    $choices = [
        "patch → " . "{$major}.{$minor}." . ($patch + 1),
        "minor → " . "{$major}." . ($minor + 1) . ".0",
        "major → " . ($major + 1) . ".0.0",
    ];
    $picked = $c->choice('버전 범프 타입', $choices);
    if ($picked === null) {
        $c->warn('취소됨');
        return;
    }

    $newVersion = match (true) {
        str_starts_with($picked, 'patch') => "{$major}.{$minor}." . ($patch + 1),
        str_starts_with($picked, 'minor') => "{$major}." . ($minor + 1) . ".0",
        str_starts_with($picked, 'major') => ($major + 1) . ".0.0",
        default => $currentVersion,
    };

    if ($newVersion === $currentVersion) {
        $c->warn('버전 변경 없음. 취소.');
        return;
    }

    $c->info("새 버전: v{$currentVersion} → v{$newVersion}");
    $c->newLine();

    // ── 3. 프리릴리즈 체크 ──
    $c->info('═══ 프리릴리즈 체크 ═══');
    $pass = 0;
    $warn = 0;
    $fail = 0;

    $check = function (string $label, bool $ok, string $good, string $bad, bool $critical = false) use ($c, &$pass, &$warn, &$fail): void {
        if ($ok) {
            $c->success("  ✓ {$label}: {$good}");
            $pass++;
        } elseif ($critical) {
            $c->error("  ✗ {$label}: {$bad}");
            $fail++;
        } else {
            $c->warn("  △ {$label}: {$bad}");
            $warn++;
        }
    };

    $check('PHP', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION, PHP_VERSION . ' (8.2+ 필수)', true);

    $debug = (bool) config('app.debug');
    $check('app.debug', !$debug, 'false', 'true (프로덕션에서 false 필수)', true);

    $placeholder = 'CHANGE_ME';
    $appKey = config('app.key') ?? '';
    $check('app.key', !str_contains($appKey, $placeholder), '설정됨', '기본값 사용 중', true);
    $authSecret = config('auth.secret') ?? '';
    $check('auth.secret', !str_contains($authSecret, $placeholder), '설정됨', '기본값 사용 중', true);
    $encKey = config('encrypt.key') ?? '';
    $check('encrypt.key', !str_contains($encKey, $placeholder), '설정됨', '기본값 사용 중', true);

    $lintFail = 0;
    $toolFiles = glob(__DIR__ . '/catphp/*.php');
    foreach ($toolFiles as $f) {
        exec('php -l ' . escapeshellarg($f) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $lintFail++;
            $c->error("  lint 실패: " . basename($f));
        }
    }
    $check('PHP lint (' . count($toolFiles) . '개)', $lintFail === 0, '전체 통과', "{$lintFail}개 실패", true);

    $storageDirs = array_filter([
        config('cache.path'), config('log.path'),
        config('firewall.path'), config('rate.path'),
    ]);
    $storageOk = true;
    foreach ($storageDirs as $dir) {
        if ($dir && is_dir($dir) && !is_writable($dir)) {
            $storageOk = false;
        }
    }
    $check('스토리지 권한', $storageOk, '쓰기 가능', '일부 디렉토리 쓰기 불가');

    $opcacheLoaded = extension_loaded('Zend OPcache');
    $check('OPcache', $opcacheLoaded, '로드됨', '미로드 (성능 저하)');

    $toolCount = count(glob(__DIR__ . '/catphp/*.php')) - 2;
    $check('도구 파일', $toolCount > 0, "{$toolCount}개", '0개');

    $c->newLine();
    $c->info("체크 결과: 통과 {$pass} / 경고 {$warn} / 실패 {$fail}");

    if ($fail > 0) {
        $c->error('실패 항목이 있습니다. --force 옵션으로 강제 진행 가능.');
        if (!cli()->option('force')) {
            return;
        }
        $c->warn('--force: 실패 무시하고 진행');
    }
    $c->newLine();

    // ── 4. CHANGELOG 엔트리 작성 ──
    $c->info('═══ CHANGELOG ═══');
    $changeTypes = ['added' => [], 'changed' => [], 'fixed' => [], 'removed' => []];
    $labels = ['added' => '추가', 'changed' => '변경', 'fixed' => '수정', 'removed' => '삭제'];

    foreach ($labels as $key => $label) {
        $c->info("  [{$label}] 항목 입력 (빈 줄로 종료):");
        while (true) {
            $entry = $c->prompt("    {$label}", '');
            if ($entry === '') break;
            $changeTypes[$key][] = $entry;
        }
    }

    $hasEntries = false;
    foreach ($changeTypes as $entries) {
        if ($entries !== []) { $hasEntries = true; break; }
    }

    $date = date('Y-m-d');
    $changelogEntry = "## [v{$newVersion}] — {$date}\n\n";
    foreach ($changeTypes as $key => $entries) {
        if ($entries === []) continue;
        $changelogEntry .= "### " . ucfirst($key) . "\n";
        foreach ($entries as $e) {
            $changelogEntry .= "- {$e}\n";
        }
        $changelogEntry .= "\n";
    }

    if (!$hasEntries) {
        $changelogEntry .= "- v{$newVersion} 릴리즈\n\n";
    }

    $c->newLine();
    $c->info('CHANGELOG 미리보기:');
    $c->info($changelogEntry);

    // ── 5. 확인 + 적용 ──
    if (!$c->confirm("v{$newVersion} 릴리즈 진행?")) {
        $c->warn('취소됨');
        return;
    }

    // catphp.php 버전 업데이트
    $newCoreContent = str_replace(
        "define('CATPHP_VERSION', '{$currentVersion}')",
        "define('CATPHP_VERSION', '{$newVersion}')",
        $coreContent
    );
    file_put_contents($coreFile, $newCoreContent, LOCK_EX);
    $c->success("catphp.php 버전 업데이트: v{$newVersion}");

    // CHANGELOG.md 업데이트
    if (is_file($changelogFile)) {
        $existing = file_get_contents($changelogFile);
        if (str_starts_with($existing, '# ')) {
            $headerEnd = strpos($existing, "\n");
            $header = substr($existing, 0, $headerEnd + 1);
            $rest = substr($existing, $headerEnd + 1);
            $content = $header . "\n" . $changelogEntry . $rest;
        } else {
            $content = $changelogEntry . $existing;
        }
    } else {
        $content = "# CHANGELOG\n\n" . $changelogEntry;
    }
    file_put_contents($changelogFile, $content, LOCK_EX);
    $c->success("CHANGELOG.md 업데이트");

    // 캐시 클리어
    if (is_dir(config('cache.path') ?? '')) {
        cache()->clear();
        $c->success('캐시 클리어');
    }

    // ── 6. ZIP 압축파일 생성 ──
    $c->newLine();
    $c->info('═══ ZIP 패키징 ═══');

    if (!is_dir($releaseDir)) {
        mkdir($releaseDir, 0755, true);
    }

    $zipName = "catphp-v{$newVersion}.zip";
    $zipPath = $releaseDir . '/' . $zipName;

    // 이전 동일 버전 파일 있으면 삭제
    if (is_file($zipPath)) {
        unlink($zipPath);
    }

    $zip = new \ZipArchive();
    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        $c->error("ZIP 파일 생성 실패: {$zipPath}");
        return;
    }

    // 제외 대상 (런타임/개발/임시 파일)
    $excludeDirs = [
        'storage',      // 런타임 데이터 (cache, logs, firewall, rate 등)
        'releases',     // 릴리즈 산출물
        'tests',        // 테스트 파일
        '.git',         // Git
        '.windsurf',    // IDE
        'node_modules', // Node
        'vendor',       // Composer
    ];
    $excludeFiles = [
        '.gitignore', '.gitattributes', '.env',
        '*.bak.*',    // config 백업
    ];
    $excludeExts = ['bak', 'log', 'tmp', 'swp'];

    $baseDir = __DIR__;
    $baseDirLen = strlen($baseDir) + 1; // +1 for trailing separator
    $prefix = "catphp-v{$newVersion}"; // ZIP 내부 루트 폴더명

    // 재귀 파일 수집
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST
    );

    $fileCount = 0;
    $totalSize = 0;

    foreach ($iterator as $item) {
        $realPath = $item->getPathname();
        $relativePath = str_replace('\\', '/', substr($realPath, $baseDirLen));

        // 제외 디렉토리 체크
        $skip = false;
        foreach ($excludeDirs as $excDir) {
            if (str_starts_with($relativePath, $excDir . '/') || $relativePath === $excDir) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        // 디렉토리 → 빈 디렉토리 엔트리 추가
        if ($item->isDir()) {
            $zip->addEmptyDir("{$prefix}/{$relativePath}");
            continue;
        }

        // 제외 파일 체크
        $basename = basename($relativePath);
        foreach ($excludeFiles as $excFile) {
            if (fnmatch($excFile, $basename)) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;

        // 제외 확장자 체크
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (in_array($ext, $excludeExts, true)) continue;

        $zip->addFile($realPath, "{$prefix}/{$relativePath}");
        $fileCount++;
        $totalSize += $item->getSize();
    }

    // storage 디렉토리 구조만 포함 (빈 .gitkeep 추가)
    $storageSubs = ['cache', 'logs', 'firewall', 'rate', 'app'];
    foreach ($storageSubs as $sub) {
        $zip->addEmptyDir("{$prefix}/storage/{$sub}");
        $zip->addFromString("{$prefix}/storage/{$sub}/.gitkeep", '');
    }

    $zip->close();

    $zipSize = filesize($zipPath);
    $zipSizeStr = $zipSize > 1048576
        ? round($zipSize / 1048576, 2) . ' MB'
        : round($zipSize / 1024, 1) . ' KB';

    $c->success("ZIP 생성 완료: {$zipName}");

    // ── 7. 결과 요약 ──
    $c->newLine();
    $c->hr('═', 40);
    $c->success("v{$newVersion} 릴리즈 빌드 완료!");
    $c->newLine();

    $c->table(['항목', '값'], [
        ['버전', "v{$currentVersion} → v{$newVersion}"],
        ['파일', "{$zipName}"],
        ['경로', $zipPath],
        ['포함 파일', "{$fileCount}개"],
        ['원본 크기', round($totalSize / 1024) . ' KB'],
        ['ZIP 크기', $zipSizeStr],
    ]);

    $c->newLine();
    $c->info('제외된 항목: ' . implode(', ', $excludeDirs));
    if ($debug) {
        $c->warn('⚠ app.debug = true — 배포 전 false로 변경하세요!');
    }
});

cli()->command('version', '현재 CatPHP 버전 출력', function () {
    cli()->info('CatPHP v' . CATPHP_VERSION . ' (PHP ' . PHP_VERSION . ')');
});

// ── sitemap ──

cli()->command('sitemap:generate', '사이트맵 XML 생성', function () {
    $output = cli()->option('output', 'Public/sitemap.xml');
    $table = cli()->option('table', '');
    $urlPattern = cli()->option('url', '');
    $dateCol = cli()->option('date-col', 'updated_at');

    if ($table === '' || $urlPattern === '') {
        cli()->info('사용법: php cli.php sitemap:generate --table=posts --url=/post/{slug} [--date-col=updated_at] [--output=Public/sitemap.xml]');
        return;
    }

    $rows = db()->table($table)->all();
    $sm = sitemap()->fromQuery($rows, $urlPattern, $dateCol);
    $path = __DIR__ . '/' . ltrim($output, '/');
    $sm->save($path);
    cli()->success("사이트맵 생성: {$path} ({$sm->count()}개 URL)");
});

// ── backup ──

cli()->command('db:backup', 'DB 백업', function () {
    $path = cli()->option('path', null);
    try {
        $result = backup()->database($path);
        if (!is_file($result)) {
            cli()->error('백업 파일 생성 실패');
            return;
        }
        $size = filesize($result);
        $sizeStr = $size > 1048576 ? round($size / 1048576, 2) . ' MB' : round($size / 1024, 1) . ' KB';
        cli()->success("백업 완료: {$result} ({$sizeStr})");
    } catch (\Throwable $e) {
        cli()->error('백업 실패: ' . $e->getMessage());
    }
});

cli()->command('db:restore', 'DB 복원', function () {
    $path = cli()->arg(0);
    if (!$path) {
        cli()->error('사용법: php cli.php db:restore <파일 경로>');
        return;
    }
    if (!is_file($path)) {
        cli()->error("파일 없음: {$path}");
        return;
    }
    if (!cli()->confirm("DB를 복원합니다: {$path}. 계속?")) {
        cli()->warn('취소됨');
        return;
    }
    backup()->restore($path);
    cli()->success('DB 복원 완료');
});

cli()->command('db:backup:list', '백업 파일 목록', function () {
    $list = backup()->list();
    if (empty($list)) {
        cli()->info('백업 파일 없음');
        return;
    }
    cli()->table(['파일명', '크기', '날짜'], array_map(fn($f) => [
        $f['name'],
        $f['size'] > 1048576 ? round($f['size'] / 1048576, 2) . ' MB' : round($f['size'] / 1024, 1) . ' KB',
        $f['date'],
    ], $list));
});

cli()->command('db:backup:clean', 'N일 이전 백업 삭제', function () {
    $days = (int) (cli()->option('days', '30') ?: '30');
    $deleted = backup()->clean($days);
    cli()->success("{$days}일 이전 백업 {$deleted}개 삭제됨");
});

// ── dbview ──

cli()->command('db:tables', '테이블 목록', function () {
    $tables = dbview()->tables();
    if (empty($tables)) {
        cli()->info('테이블 없음');
        return;
    }
    $rows = [];
    foreach ($tables as $table) {
        $count = dbview()->rowCount($table);
        $size = dbview()->size($table);
        $rows[] = [$table, (string) $count, $size];
    }
    cli()->table(['테이블', '행 수', '크기'], $rows);
});

cli()->command('db:columns', '테이블 컬럼 정보', function () {
    $table = cli()->arg(0);
    if (!$table) {
        cli()->error('사용법: php cli.php db:columns <테이블>');
        return;
    }
    $cols = dbview()->columns($table);
    cli()->table(['컬럼', '타입', 'Null', '기본값', '키'], array_map(fn($c) => [
        $c['name'], $c['type'], $c['nullable'] ? 'YES' : 'NO', (string) ($c['default'] ?? '-'), $c['key'] ?: '-',
    ], $cols));
});

cli()->command('db:describe', '테이블 상세 정보', function () {
    $table = cli()->arg(0);
    if (!$table) {
        cli()->error('사용법: php cli.php db:describe <테이블>');
        return;
    }
    $info = dbview()->describe($table);
    cli()->info("테이블: {$info['table']}  |  행: {$info['row_count']}  |  크기: {$info['size']}");
    cli()->newLine();
    cli()->info('── 컬럼 ──');
    cli()->table(['컬럼', '타입', 'Null', '기본값', '키'], array_map(fn($c) => [
        $c['name'], $c['type'], $c['nullable'] ? 'YES' : 'NO', (string) ($c['default'] ?? '-'), $c['key'] ?: '-',
    ], $info['columns']));
    if (!empty($info['indexes'])) {
        cli()->newLine();
        cli()->info('── 인덱스 ──');
        cli()->table(['이름', '컬럼', 'Unique'], array_map(fn($i) => [
            $i['name'], $i['columns'], $i['unique'] ? 'YES' : 'NO',
        ], $info['indexes']));
    }
});

cli()->command('db:preview', '테이블 데이터 미리보기', function () {
    $table = cli()->arg(0);
    if (!$table) {
        cli()->error('사용법: php cli.php db:preview <테이블> [--limit=10]');
        return;
    }
    $limit = (int) (cli()->option('limit', '10') ?: '10');
    $rows = dbview()->preview($table, $limit);
    if (empty($rows)) {
        cli()->info('데이터 없음');
        return;
    }
    $headers = array_keys($rows[0]);
    $data = array_map(fn($r) => array_map(fn($v) => mb_substr((string) ($v ?? 'NULL'), 0, 30), array_values($r)), $rows);
    cli()->table($headers, $data);
});

cli()->command('db:stats', 'DB 전체 통계', function () {
    $stats = dbview()->stats();
    cli()->table(['항목', '값'], [
        ['드라이버', $stats['driver']],
        ['데이터베이스', $stats['database']],
        ['테이블 수', (string) $stats['tables']],
        ['총 행 수', number_format($stats['total_rows'])],
        ['총 크기', $stats['total_size']],
    ]);
});

// ── swoole ──

cli()->command('swoole:start', 'Swoole HTTP 서버 시작', function () {
    if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
        cli()->error('Swoole 확장이 설치되지 않았습니다. pecl install swoole');
        return;
    }

    $host = cli()->option('host', (string) config('swoole.host', '0.0.0.0'));
    $port = (int) cli()->option('port', (string) config('swoole.port', 9501));
    $type = cli()->option('type', 'http'); // http | websocket
    $daemon = cli()->option('daemon', '') !== '';

    $sw = swoole()->listen($host, $port);

    if ($type === 'websocket') {
        $sw->websocket();
    } else {
        $sw->http();
    }

    if ($daemon) {
        $sw->daemonize();
    }

    // 정적 파일 서빙 (Public 디렉토리)
    $docRoot = cli()->option('docroot', '');
    if ($docRoot !== '') {
        $sw->staticFiles($docRoot);
    }

    // Hot Reload (개발 모드)
    if ((bool) config('swoole.hot_reload', false)) {
        cli()->info('Hot Reload 활성화');
    }

    // 부트스트랩: 라우트 및 설정 로드
    $sw->onBoot(function ($svr, int $workerId): void {
        // 워커 시작 시 index.php의 라우트 등록 로직을 여기서 실행 가능
        // 사용자가 별도의 swoole-routes.php를 만들어 require 할 수 있음
        $routeFile = __DIR__ . '/routes/swoole.php';
        if (is_file($routeFile)) {
            require $routeFile;
        }
    });

    $sw->start();
});

cli()->command('swoole:stop', 'Swoole 서버 중지', function () {
    if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
        cli()->warn('Swoole 확장이 설치되지 않았습니다.');
    }
    if (\Cat\Swoole::stop()) {
        cli()->success('Swoole 서버가 중지되었습니다.');
    } else {
        cli()->error('실행 중인 Swoole 서버를 찾을 수 없습니다.');
    }
});

cli()->command('swoole:reload', 'Swoole 워커 리로드', function () {
    if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
        cli()->warn('Swoole 확장이 설치되지 않았습니다.');
    }
    if (\Cat\Swoole::reload()) {
        cli()->success('워커 리로드 신호를 보냈습니다.');
    } else {
        cli()->error('실행 중인 Swoole 서버를 찾을 수 없습니다.');
    }
});

cli()->command('swoole:status', 'Swoole 서버 상태 확인', function () {
    if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
        cli()->warn('Swoole 확장이 설치되지 않았습니다.');
    }
    $status = \Cat\Swoole::status();
    $running = $status['running'] ? 'YES' : 'NO';
    cli()->table(['항목', '값'], [
        ['실행 중', $running],
        ['PID', (string) $status['pid']],
        ['PID 파일', $status['pid_file']],
        ['호스트', $status['host']],
        ['포트', (string) $status['port']],
    ]);
});

// ── seed ──

cli()->command('db:seed',        'DB 시드 데이터',       function() {
    $seeder = cli()->arg(0);
    if (!$seeder) {
        cli()->error('사용법: php cli.php db:seed <SeederClass>');
        return;
    }
    $seederFile = __DIR__ . '/seeders/' . $seeder . '.php';
    if (!is_file($seederFile)) {
        cli()->error("시더 파일 없음: {$seederFile}");
        return;
    }
    require $seederFile;
    cli()->success("Seed 완료: {$seeder}");
});

cli()->run();
