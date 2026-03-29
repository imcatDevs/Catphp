<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Swoole — 고성능 비동기 HTTP/WebSocket 서버
 *
 * Swoole 확장 기반 이벤트 루프 서버.
 * CatPHP Router와 자동 통합, 코루틴·연결 풀·태스크 워커 지원.
 *
 * ⚡ 빠른 속도: 상주 프로세스 — require/config 1회 부팅, 요청마다 재로드 없음
 * 🔧 사용 편리: swoole()->http()->start() 한 줄로 서버 시작
 * 📖 쉬운 학습: http/websocket/task/co 4가지 핵심 메서드
 * 🔒 보안: 요청 격리(슈퍼글로벌 초기화), 연결 풀 격리, Graceful Shutdown
 *
 * 사용법:
 *   // HTTP 서버
 *   swoole()->http()->start();
 *
 *   // WebSocket 서버
 *   swoole()->websocket()
 *       ->onWsOpen(fn(int $fd) => null)
 *       ->onWsMessage(fn(int $fd, string $data) => swoole()->push($fd, 'echo: ' . $data))
 *       ->start();
 *
 *   // 비동기 태스크
 *   swoole()->task('email', ['to' => 'a@b.com']);
 *
 *   // 코루틴
 *   swoole()->co(function() {
 *       $ch = swoole()->pool('db')->get();
 *       // ... 쿼리 실행
 *       swoole()->pool('db')->put($ch);
 *   });
 *
 *   // 타이머
 *   swoole()->tick(1000, fn() => logger()->info('매 1초'));
 *   swoole()->after(5000, fn() => logger()->info('5초 후 1회'));
 *
 * @config array{
 *     host?: string,              // 바인드 호스트 (기본 '0.0.0.0')
 *     port?: int,                 // 바인드 포트 (기본 9501)
 *     mode?: string,              // 'process' | 'base' (기본 'process')
 *     worker_num?: int,           // 워커 프로세스 수 (기본 CPU 코어 수)
 *     task_worker_num?: int,      // 태스크 워커 수 (기본 4)
 *     max_request?: int,          // 워커당 최대 요청 수 (메모리 누수 방지, 기본 10000)
 *     max_conn?: int,             // 최대 동시 연결 수 (기본 10000)
 *     daemonize?: bool,           // 데몬 모드 (기본 false)
 *     log_file?: string,          // Swoole 로그 파일 경로
 *     log_level?: int,            // 로그 레벨 (0=DEBUG ~ 5=OFF)
 *     pid_file?: string,          // PID 파일 경로
 *     dispatch_mode?: int,        // 디스패치 모드 (1=라운드로빈, 2=FD, 3=큐경쟁)
 *     open_tcp_nodelay?: bool,    // TCP_NODELAY (기본 true)
 *     enable_coroutine?: bool,    // 코루틴 활성화 (기본 true)
 *     static_handler?: bool,      // 정적 파일 서빙 (기본 false)
 *     document_root?: string,     // 정적 파일 루트
 *     hot_reload?: bool,          // 파일 변경 자동 리로드 (기본 false, 개발 전용)
 *     hot_reload_paths?: array,   // 감시 디렉토리 목록
 *     heartbeat_idle?: int,       // 유휴 연결 타임아웃 (초, 기본 600)
 *     heartbeat_check?: int,      // 하트비트 검사 간격 (초, 기본 60)
 *     ssl_cert?: string,          // SSL 인증서 경로 (HTTPS/WSS)
 *     ssl_key?: string,           // SSL 개인키 경로
 *     buffer_output_size?: int,   // 출력 버퍼 크기 (기본 2MB)
 *     package_max_length?: int,   // 최대 패킷 크기 (기본 2MB)
 *     pool?: array{               // 연결 풀 설정
 *         db?: int,               //   DB 풀 크기 (기본 worker_num)
 *         redis?: int,            //   Redis 풀 크기 (기본 worker_num)
 *     },
 * } swoole  → config('swoole.host')
 */
final class Swoole
{
    private static ?self $instance = null;

    // ── 설정 ──
    private string $host;
    private int $port;
    private string $mode;
    private array $settings;

    // ── 서버 인스턴스 ──
    private ?\Swoole\Http\Server $httpServer = null;
    private ?\Swoole\WebSocket\Server $wsServer = null;

    /** @var \Swoole\Http\Server|\Swoole\WebSocket\Server|null 현재 활성 서버 */
    private mixed $server = null;

    // ── 서버 타입 ──
    private string $serverType = 'http'; // http | websocket

    // ── 이벤트 콜백 ──
    /** @var array<string, callable> */
    private array $events = [];

    // ── 태스크 핸들러 ──
    /** @var array<string, callable> */
    private array $taskHandlers = [];

    // ── WebSocket 룸 ──
    /** @var array<string, array<int, bool>> [room => [fd => true]] */
    private array $rooms = [];

    // ── 연결 풀 ──
    /** @var array<string, \Swoole\Coroutine\Channel> */
    private array $pools = [];

    // ── 타이머 ID ──
    /** @var array<int, int> */
    private array $timerIds = [];

    // ── 부트스트랩 콜백 ──
    private ?\Closure $bootstrap = null;

    // ── 미들웨어 ──
    /** @var list<callable> */
    private array $middlewares = [];

    private function __construct()
    {
        $this->host = (string) \config('swoole.host', '0.0.0.0');
        $this->port = (int) \config('swoole.port', 9501);
        $this->mode = (string) \config('swoole.mode', 'process');

        $this->settings = $this->buildSettings();
    }

    public static function getInstance(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            throw new \RuntimeException(
                'Swoole 확장이 필요합니다. pecl install swoole 또는 pecl install openswoole'
            );
        }

        return self::$instance = new self();
    }

    // ════════════════════════════════════════════════
    // 서버 타입 선택
    // ════════════════════════════════════════════════

    /** HTTP 서버 모드 */
    public function http(): self
    {
        $this->serverType = 'http';
        return $this;
    }

    /** WebSocket 서버 모드 (HTTP 겸용) */
    public function websocket(): self
    {
        $this->serverType = 'websocket';
        return $this;
    }

    // ════════════════════════════════════════════════
    // 설정 체이닝
    // ════════════════════════════════════════════════

    /** 바인드 주소 설정 */
    public function listen(string $host, int $port): self
    {
        $this->host = $host;
        $this->port = $port;
        return $this;
    }

    /** 워커 수 설정 */
    public function workers(int $num): self
    {
        $this->settings['worker_num'] = max(1, $num);
        return $this;
    }

    /** 태스크 워커 수 설정 */
    public function taskWorkers(int $num): self
    {
        $this->settings['task_worker_num'] = max(0, $num);
        return $this;
    }

    /** 데몬 모드 */
    public function daemonize(bool $enable = true): self
    {
        $this->settings['daemonize'] = $enable;
        return $this;
    }

    /** SSL 설정 (HTTPS/WSS) */
    public function ssl(string $certFile, string $keyFile): self
    {
        $this->settings['ssl_cert_file'] = $certFile;
        $this->settings['ssl_key_file'] = $keyFile;
        return $this;
    }

    /** 정적 파일 서빙 활성화 */
    public function staticFiles(string $documentRoot): self
    {
        $this->settings['enable_static_handler'] = true;
        $this->settings['document_root'] = $documentRoot;
        return $this;
    }

    /** Swoole 서버 설정 직접 지정 */
    public function set(array $settings): self
    {
        $this->settings = array_merge($this->settings, $settings);
        return $this;
    }

    /** 워커 시작 시 부트스트랩 콜백 (라우트 정의 등) */
    public function onBoot(\Closure $callback): self
    {
        $this->bootstrap = $callback;
        return $this;
    }

    /** 미들웨어 등록 (요청마다 실행) */
    public function use(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    // ════════════════════════════════════════════════
    // WebSocket 이벤트
    // ════════════════════════════════════════════════

    /** WebSocket 연결 열림 콜백 */
    public function onWsOpen(callable $callback): self
    {
        $this->events['ws.open'] = $callback;
        return $this;
    }

    /** WebSocket 메시지 수신 콜백 */
    public function onWsMessage(callable $callback): self
    {
        $this->events['ws.message'] = $callback;
        return $this;
    }

    /** WebSocket 연결 닫힘 콜백 */
    public function onWsClose(callable $callback): self
    {
        $this->events['ws.close'] = $callback;
        return $this;
    }

    // ════════════════════════════════════════════════
    // 서버 생명주기 이벤트
    // ════════════════════════════════════════════════

    /** 서버 시작 시 콜백 (마스터 프로세스) */
    public function onStart(callable $callback): self
    {
        $this->events['start'] = $callback;
        return $this;
    }

    /** 워커 시작 시 콜백 */
    public function onWorkerStart(callable $callback): self
    {
        $this->events['workerStart'] = $callback;
        return $this;
    }

    /** 워커 종료 시 콜백 */
    public function onWorkerStop(callable $callback): self
    {
        $this->events['workerStop'] = $callback;
        return $this;
    }

    /** 서버 종료 시 콜백 */
    public function onShutdown(callable $callback): self
    {
        $this->events['shutdown'] = $callback;
        return $this;
    }

    // ════════════════════════════════════════════════
    // 서버 시작/중지
    // ════════════════════════════════════════════════

    /** 서버 시작 (블로킹) */
    public function start(): void
    {
        $swooleMode = $this->mode === 'base'
            ? SWOOLE_BASE
            : SWOOLE_PROCESS;

        $sslFlag = isset($this->settings['ssl_cert_file']) ? SWOOLE_SSL : 0;

        if ($this->serverType === 'websocket') {
            $this->wsServer = new \Swoole\WebSocket\Server(
                $this->host,
                $this->port,
                $swooleMode,
                SWOOLE_SOCK_TCP | $sslFlag
            );
            $this->server = $this->wsServer;
        } else {
            $this->httpServer = new \Swoole\Http\Server(
                $this->host,
                $this->port,
                $swooleMode,
                SWOOLE_SOCK_TCP | $sslFlag
            );
            $this->server = $this->httpServer;
        }

        $this->server->set($this->settings);
        $this->registerCallbacks();

        echo "🐱 CatPHP Swoole Server\n";
        echo "   Type: " . strtoupper($this->serverType) . "\n";
        echo "   Listen: {$this->host}:{$this->port}\n";
        echo "   Workers: " . ($this->settings['worker_num'] ?? swoole_cpu_num()) . "\n";
        if (($this->settings['task_worker_num'] ?? 0) > 0) {
            echo "   Task Workers: " . $this->settings['task_worker_num'] . "\n";
        }
        echo "   PID File: " . ($this->settings['pid_file'] ?? 'none') . "\n";
        echo "   Press Ctrl+C to stop\n\n";

        $this->server->start();
    }

    /** 서버 중지 (PID 파일 기반) */
    public static function stop(): bool
    {
        $pidFile = \config('swoole.pid_file', '');
        if ($pidFile === '') {
            $pidFile = dirname(__DIR__) . '/storage/swoole.pid';
        }

        if (!is_file($pidFile)) {
            return false;
        }

        $pid = (int) file_get_contents($pidFile);
        if ($pid <= 0) {
            return false;
        }

        // SIGTERM으로 Graceful Shutdown
        if (function_exists('posix_kill')) {
            $result = posix_kill($pid, SIGTERM);
        } else {
            // Windows 호환
            $result = stripos(PHP_OS, 'WIN') === 0
                ? (bool) exec("taskkill /PID {$pid} /F 2>&1")
                : (bool) exec("kill -TERM {$pid} 2>&1");
        }

        // PID 파일 제거
        if ($result) {
            @unlink($pidFile);
        }

        return $result;
    }

    /** 워커 리로드 (PID 파일 기반) */
    public static function reload(): bool
    {
        $pidFile = \config('swoole.pid_file', '');
        if ($pidFile === '') {
            $pidFile = dirname(__DIR__) . '/storage/swoole.pid';
        }

        if (!is_file($pidFile)) {
            return false;
        }

        $pid = (int) file_get_contents($pidFile);
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, SIGUSR1);
        }

        return stripos(PHP_OS, 'WIN') !== 0
            && (bool) exec("kill -USR1 {$pid} 2>&1");
    }

    /** 서버 실행 상태 확인 */
    public static function status(): array
    {
        $pidFile = \config('swoole.pid_file', '');
        if ($pidFile === '') {
            $pidFile = dirname(__DIR__) . '/storage/swoole.pid';
        }

        $pid = 0;
        $running = false;

        if (is_file($pidFile)) {
            $pid = (int) file_get_contents($pidFile);

            if ($pid > 0) {
                if (function_exists('posix_kill')) {
                    $running = posix_kill($pid, 0);
                } else {
                    $running = stripos(PHP_OS, 'WIN') === 0
                        ? (bool) exec("tasklist /FI \"PID eq {$pid}\" 2>NUL | find \"{$pid}\"")
                        : (bool) exec("kill -0 {$pid} 2>/dev/null && echo 1");
                }
            }
        }

        return [
            'running'  => $running,
            'pid'      => $pid,
            'pid_file' => $pidFile,
            'host'     => \config('swoole.host', '0.0.0.0'),
            'port'     => (int) \config('swoole.port', 9501),
        ];
    }

    // ════════════════════════════════════════════════
    // WebSocket 메시지 전송
    // ════════════════════════════════════════════════

    /** 특정 FD에 메시지 전송 */
    public function push(int $fd, string|array $data, int $opcode = WEBSOCKET_OPCODE_TEXT): bool
    {
        $server = $this->wsServer ?? $this->server;
        if ($server === null || !($server instanceof \Swoole\WebSocket\Server)) {
            return false;
        }
        if (!$server->isEstablished($fd)) {
            return false;
        }
        $payload = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        return $server->push($fd, $payload, $opcode);
    }

    /** 브로드캐스트 (전체 연결) */
    public function broadcast(string|array $data, ?int $excludeFd = null): int
    {
        $server = $this->wsServer ?? $this->server;
        if ($server === null || !($server instanceof \Swoole\WebSocket\Server)) {
            return 0;
        }

        $payload = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        $count = 0;

        foreach ($server->connections as $fd) {
            if ($fd === $excludeFd) {
                continue;
            }
            if ($server->isEstablished($fd)) {
                $server->push($fd, $payload);
                $count++;
            }
        }

        return $count;
    }

    // ── 룸 관리 ──

    /** FD를 룸에 참가 */
    public function join(int $fd, string $room): self
    {
        $this->rooms[$room][$fd] = true;
        return $this;
    }

    /** FD를 룸에서 퇴장 */
    public function leave(int $fd, string $room): self
    {
        unset($this->rooms[$room][$fd]);
        if (empty($this->rooms[$room])) {
            unset($this->rooms[$room]);
        }
        return $this;
    }

    /** FD를 모든 룸에서 퇴장 */
    public function leaveAll(int $fd): self
    {
        foreach ($this->rooms as $room => $_) {
            unset($this->rooms[$room][$fd]);
            if (empty($this->rooms[$room])) {
                unset($this->rooms[$room]);
            }
        }
        return $this;
    }

    /** 룸 내 브로드캐스트 */
    public function toRoom(string $room, string|array $data, ?int $excludeFd = null): int
    {
        $members = $this->rooms[$room] ?? [];
        $payload = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        $count = 0;

        foreach (array_keys($members) as $fd) {
            if ($fd === $excludeFd) {
                continue;
            }
            if ($this->push($fd, $payload)) {
                $count++;
            }
        }

        return $count;
    }

    /** 룸 멤버 목록 */
    public function roomMembers(string $room): array
    {
        return array_keys($this->rooms[$room] ?? []);
    }

    /** 전체 룸 목록 */
    public function roomList(): array
    {
        $list = [];
        foreach ($this->rooms as $room => $members) {
            $list[] = ['room' => $room, 'count' => count($members)];
        }
        return $list;
    }

    // ════════════════════════════════════════════════
    // 태스크 워커 (비동기 백그라운드 작업)
    // ════════════════════════════════════════════════

    /** 태스크 핸들러 등록 */
    public function handle(string $name, callable $handler): self
    {
        $this->taskHandlers[$name] = $handler;
        return $this;
    }

    /** 비동기 태스크 전송 */
    public function task(string $name, array $payload = []): int|false
    {
        if ($this->server === null) {
            throw new \RuntimeException('서버가 시작되지 않았습니다. start() 후 사용하세요.');
        }
        return $this->server->task([
            'name'    => $name,
            'payload' => $payload,
        ]);
    }

    /** 동기 태스크 (결과 대기, 코루틴 환경) */
    public function taskWait(string $name, array $payload = [], float $timeout = 5.0): mixed
    {
        if ($this->server === null) {
            throw new \RuntimeException('서버가 시작되지 않았습니다.');
        }
        return $this->server->taskwait([
            'name'    => $name,
            'payload' => $payload,
        ], $timeout);
    }

    /** 병렬 태스크 (여러 태스크 동시 실행, 결과 배열 반환) */
    public function taskCo(array $tasks, float $timeout = 5.0): array
    {
        if ($this->server === null) {
            throw new \RuntimeException('서버가 시작되지 않았습니다.');
        }
        $list = [];
        foreach ($tasks as $t) {
            $list[] = [
                'name'    => $t['name'] ?? $t[0] ?? '',
                'payload' => $t['payload'] ?? $t[1] ?? [],
            ];
        }
        return $this->server->taskCo($list, $timeout);
    }

    // ════════════════════════════════════════════════
    // 코루틴
    // ════════════════════════════════════════════════

    /** 코루틴 생성 */
    public function co(callable $callback): int
    {
        return \Swoole\Coroutine::create($callback);
    }

    /** 코루틴 sleep (비블로킹) */
    public function sleep(float $seconds): void
    {
        \Swoole\Coroutine::sleep($seconds);
    }

    /** 코루틴 채널 생성 (프로듀서-컨슈머) */
    public function channel(int $capacity = 1): \Swoole\Coroutine\Channel
    {
        return new \Swoole\Coroutine\Channel($capacity);
    }

    /** WaitGroup — 여러 코루틴 완료 대기 */
    public function waitGroup(): \Swoole\Coroutine\WaitGroup
    {
        return new \Swoole\Coroutine\WaitGroup();
    }

    /** 코루틴 병렬 실행 + 결과 수집 */
    public function parallel(array $callables, float $timeout = -1): array
    {
        $results = [];
        $wg = new \Swoole\Coroutine\WaitGroup();
        $ch = new \Swoole\Coroutine\Channel(count($callables));

        foreach ($callables as $key => $fn) {
            $wg->add();
            \Swoole\Coroutine::create(function () use ($key, $fn, $wg, $ch): void {
                try {
                    $ch->push(['key' => $key, 'value' => $fn(), 'error' => null]);
                } catch (\Throwable $e) {
                    $ch->push(['key' => $key, 'value' => null, 'error' => $e->getMessage()]);
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait($timeout > 0 ? $timeout : -1);

        while (!$ch->isEmpty()) {
            $item = $ch->pop();
            if (is_array($item)) {
                $results[$item['key']] = $item;
            }
        }

        return $results;
    }

    // ════════════════════════════════════════════════
    // 연결 풀
    // ════════════════════════════════════════════════

    /**
     * 연결 풀 초기화
     *
     * @param string   $name    풀 이름 ('db', 'redis', 또는 커스텀)
     * @param callable $factory 연결 생성 팩토리 (PDO/Redis 등 반환)
     * @param int      $size    풀 크기
     */
    public function createPool(string $name, callable $factory, int $size = 0): self
    {
        if ($size <= 0) {
            $size = (int) ($this->settings['worker_num'] ?? swoole_cpu_num());
        }

        $channel = new \Swoole\Coroutine\Channel($size);

        for ($i = 0; $i < $size; $i++) {
            try {
                $conn = $factory();
                $channel->push($conn);
            } catch (\Throwable $e) {
                if (class_exists('Cat\\Log', false)) {
                    \logger()->error("연결 풀 [{$name}] 생성 실패: " . $e->getMessage());
                }
            }
        }

        $this->pools[$name] = $channel;
        return $this;
    }

    /** DB 연결 풀 자동 초기화 (config 기반) */
    public function createDbPool(?int $size = null): self
    {
        $poolSize = $size ?? (int) \config('swoole.pool.db', $this->settings['worker_num'] ?? swoole_cpu_num());

        return $this->createPool('db', function (): \PDO {
            $cfg = (array) \config('db');
            $driver = $cfg['driver'] ?? 'mysql';

            $dsn = match ($driver) {
                'sqlite' => 'sqlite:' . ($cfg['dbname'] ?? $cfg['path'] ?? ':memory:'),
                'pgsql'  => sprintf('pgsql:host=%s;port=%d;dbname=%s',
                    $cfg['host'] ?? '127.0.0.1', $cfg['port'] ?? 5432, $cfg['dbname'] ?? ''),
                default  => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $cfg['host'] ?? '127.0.0.1', $cfg['port'] ?? 3306, $cfg['dbname'] ?? '', $cfg['charset'] ?? 'utf8mb4'),
            };

            return new \PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }, $poolSize);
    }

    /** Redis 연결 풀 자동 초기화 (config 기반) */
    public function createRedisPool(?int $size = null): self
    {
        $poolSize = $size ?? (int) \config('swoole.pool.redis', $this->settings['worker_num'] ?? swoole_cpu_num());

        return $this->createPool('redis', function (): \Redis {
            $redis = new \Redis();
            $redis->connect(
                (string) \config('redis.host', '127.0.0.1'),
                (int) \config('redis.port', 6379),
                (float) \config('redis.timeout', 2.0),
            );
            $pass = \config('redis.password');
            if ($pass !== null && $pass !== '') {
                $redis->auth($pass);
            }
            $db = (int) \config('redis.database', 0);
            if ($db !== 0) {
                $redis->select($db);
            }
            return $redis;
        }, $poolSize);
    }

    /**
     * 풀에서 연결 가져오기 (코루틴 안전)
     *
     * @return mixed PDO, Redis, 또는 커스텀 연결 객체
     */
    public function poolGet(string $name, float $timeout = 3.0): mixed
    {
        $ch = $this->pools[$name] ?? null;
        if ($ch === null) {
            throw new \RuntimeException("연결 풀 [{$name}]이 존재하지 않습니다. createPool() 또는 createDbPool()로 초기화하세요.");
        }
        $conn = $ch->pop($timeout);
        if ($conn === false) {
            throw new \RuntimeException("연결 풀 [{$name}] 가용 연결 없음 (timeout={$timeout}s)");
        }
        return $conn;
    }

    /** 풀에 연결 반환 */
    public function poolPut(string $name, mixed $connection): void
    {
        $ch = $this->pools[$name] ?? null;
        if ($ch !== null) {
            $ch->push($connection);
        }
    }

    /** 풀 상태 조회 */
    public function poolStats(string $name): array
    {
        $ch = $this->pools[$name] ?? null;
        if ($ch === null) {
            return ['name' => $name, 'exists' => false];
        }
        return [
            'name'      => $name,
            'exists'    => true,
            'capacity'  => $ch->capacity,
            'available' => $ch->length(),
            'in_use'    => $ch->capacity - $ch->length(),
        ];
    }

    // ════════════════════════════════════════════════
    // 타이머
    // ════════════════════════════════════════════════

    /** 반복 타이머 (밀리초) */
    public function tick(int $ms, callable $callback): int
    {
        $id = \Swoole\Timer::tick($ms, $callback);
        $this->timerIds[] = $id;
        return $id;
    }

    /** 1회 타이머 (밀리초) */
    public function after(int $ms, callable $callback): int
    {
        return \Swoole\Timer::after($ms, $callback);
    }

    /** 타이머 해제 */
    public function clearTimer(int $timerId): bool
    {
        return \Swoole\Timer::clear($timerId);
    }

    /** 전체 타이머 해제 */
    public function clearAllTimers(): void
    {
        foreach ($this->timerIds as $id) {
            \Swoole\Timer::clear($id);
        }
        $this->timerIds = [];
    }

    // ════════════════════════════════════════════════
    // 서버 정보
    // ════════════════════════════════════════════════

    /** 현재 서버 인스턴스 반환 (고급 사용) */
    public function raw(): \Swoole\Http\Server|\Swoole\WebSocket\Server|null
    {
        return $this->server;
    }

    /** 서버 통계 */
    public function stats(): array
    {
        if ($this->server === null) {
            return [];
        }
        return $this->server->stats();
    }

    /** 활성 연결 수 */
    public function connectionCount(): int
    {
        if ($this->server === null) {
            return 0;
        }
        $stats = $this->server->stats();
        return (int) ($stats['connection_num'] ?? 0);
    }

    /** FD 연결 정보 */
    public function getClientInfo(int $fd): array|false
    {
        if ($this->server === null) {
            return false;
        }
        return $this->server->getClientInfo($fd);
    }

    // ════════════════════════════════════════════════
    // 내부: 설정 빌드
    // ════════════════════════════════════════════════

    private function buildSettings(): array
    {
        $workerNum = (int) \config('swoole.worker_num', swoole_cpu_num());

        $settings = [
            'worker_num'            => max(1, $workerNum),
            'task_worker_num'       => (int) \config('swoole.task_worker_num', 4),
            'max_request'           => (int) \config('swoole.max_request', 10000),
            'max_conn'              => (int) \config('swoole.max_conn', 10000),
            'daemonize'             => (bool) \config('swoole.daemonize', false),
            'dispatch_mode'         => (int) \config('swoole.dispatch_mode', 2),
            'open_tcp_nodelay'      => (bool) \config('swoole.open_tcp_nodelay', true),
            'enable_coroutine'      => (bool) \config('swoole.enable_coroutine', true),
            'buffer_output_size'    => (int) \config('swoole.buffer_output_size', 2 * 1024 * 1024),
            'package_max_length'    => (int) \config('swoole.package_max_length', 2 * 1024 * 1024),
        ];

        // PID 파일
        $pidFile = \config('swoole.pid_file', '');
        if ($pidFile === '') {
            $pidFile = dirname(__DIR__) . '/storage/swoole.pid';
        }
        $settings['pid_file'] = $pidFile;

        // 로그 파일
        $logFile = \config('swoole.log_file', '');
        if ($logFile !== '') {
            $settings['log_file'] = $logFile;
        }
        $logLevel = \config('swoole.log_level');
        if ($logLevel !== null) {
            $settings['log_level'] = (int) $logLevel;
        }

        // 하트비트
        $heartbeatIdle = (int) \config('swoole.heartbeat_idle', 600);
        $heartbeatCheck = (int) \config('swoole.heartbeat_check', 60);
        if ($heartbeatIdle > 0) {
            $settings['heartbeat_idle_time'] = $heartbeatIdle;
            $settings['heartbeat_check_interval'] = $heartbeatCheck;
        }

        // 정적 파일 서빙
        if ((bool) \config('swoole.static_handler', false)) {
            $settings['enable_static_handler'] = true;
            $docRoot = \config('swoole.document_root', '');
            if ($docRoot !== '') {
                $settings['document_root'] = $docRoot;
            }
        }

        // SSL
        $sslCert = \config('swoole.ssl_cert', '');
        $sslKey = \config('swoole.ssl_key', '');
        if ($sslCert !== '' && $sslKey !== '') {
            $settings['ssl_cert_file'] = $sslCert;
            $settings['ssl_key_file'] = $sslKey;
        }

        return $settings;
    }

    // ════════════════════════════════════════════════
    // 내부: 콜백 등록
    // ════════════════════════════════════════════════

    private function registerCallbacks(): void
    {
        $server = $this->server;

        // ── 서버 시작 ──
        $server->on('Start', function ($svr) {
            // PID 파일 기록
            $dir = dirname($this->settings['pid_file']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($this->settings['pid_file'], (string) $svr->master_pid);

            if (isset($this->events['start'])) {
                ($this->events['start'])($svr);
            }
        });

        // ── 워커 시작 ──
        $server->on('WorkerStart', function ($svr, int $workerId) {
            // 부트스트랩 콜백 실행 (라우트 정의 등)
            if ($this->bootstrap !== null) {
                ($this->bootstrap)($svr, $workerId);
            }

            // Hot Reload — inotify 기반 파일 감시 (워커 0에서만)
            if ($workerId === 0 && (bool) \config('swoole.hot_reload', false)) {
                $this->startHotReload($svr);
            }

            // 연결 풀 초기화 (워커별)
            $dbPoolSize = (int) \config('swoole.pool.db', 0);
            if ($dbPoolSize > 0) {
                $this->createDbPool($dbPoolSize);
            }
            $redisPoolSize = (int) \config('swoole.pool.redis', 0);
            if ($redisPoolSize > 0) {
                $this->createRedisPool($redisPoolSize);
            }

            if (isset($this->events['workerStart'])) {
                ($this->events['workerStart'])($svr, $workerId);
            }
        });

        // ── 워커 종료 ──
        $server->on('WorkerStop', function ($svr, int $workerId) {
            $this->clearAllTimers();
            $this->destroyPools();

            if (isset($this->events['workerStop'])) {
                ($this->events['workerStop'])($svr, $workerId);
            }
        });

        // ── 서버 종료 ──
        $server->on('Shutdown', function ($svr) {
            @unlink($this->settings['pid_file']);

            if (isset($this->events['shutdown'])) {
                ($this->events['shutdown'])($svr);
            }
        });

        // ── HTTP 요청 처리 ──
        $server->on('Request', function (\Swoole\Http\Request $req, \Swoole\Http\Response $res) {
            $this->handleRequest($req, $res);
        });

        // ── 태스크 워커 ──
        if (($this->settings['task_worker_num'] ?? 0) > 0) {
            $server->on('Task', function ($svr, int $taskId, int $reactorId, mixed $data) {
                if (!is_array($data)) {
                    return null;
                }
                $name = $data['name'] ?? '';
                $handler = $this->taskHandlers[$name] ?? null;
                if ($handler === null) {
                    if (class_exists('Cat\\Log', false)) {
                        \logger()->warn("Swoole 태스크 핸들러 미등록: {$name}");
                    }
                    return null;
                }
                try {
                    return $handler($data['payload'] ?? []);
                } catch (\Throwable $e) {
                    if (class_exists('Cat\\Log', false)) {
                        \logger()->error("Swoole 태스크 실패: {$name} — " . $e->getMessage());
                    }
                    return null;
                }
            });

            $server->on('Finish', function ($svr, int $taskId, mixed $data) {
                // 태스크 완료 후 처리 (필요 시 이벤트로 확장)
            });
        }

        // ── WebSocket 이벤트 ──
        if ($this->serverType === 'websocket') {
            $server->on('Open', function (\Swoole\WebSocket\Server $svr, \Swoole\Http\Request $req) {
                if (isset($this->events['ws.open'])) {
                    ($this->events['ws.open'])($req->fd, $req);
                }
            });

            $server->on('Message', function (\Swoole\WebSocket\Server $svr, \Swoole\WebSocket\Frame $frame) {
                if (isset($this->events['ws.message'])) {
                    ($this->events['ws.message'])($frame->fd, $frame->data, $frame);
                }
            });

            $server->on('Close', function ($svr, int $fd) {
                // 룸에서 자동 퇴장
                $this->leaveAll($fd);

                if (isset($this->events['ws.close'])) {
                    ($this->events['ws.close'])($fd);
                }
            });
        }
    }

    // ════════════════════════════════════════════════
    // 내부: HTTP 요청 → CatPHP Router 브릿지
    // ════════════════════════════════════════════════

    private function handleRequest(\Swoole\Http\Request $req, \Swoole\Http\Response $res): void
    {
        try {
            // ── 1. 슈퍼글로벌 초기화 (요청 격리 — 보안) ──
            $_GET    = $req->get ?? [];
            $_POST   = $req->post ?? [];
            $_COOKIE = $req->cookie ?? [];
            $_FILES  = $req->files ?? [];
            $_SERVER = $this->buildServerVars($req);

            // input() 캐시 초기화
            $inputData = array_merge($_GET, $_POST);
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $rawBody = $req->rawContent();
                if ($rawBody !== '' && $rawBody !== false) {
                    $json = json_decode($rawBody, true, 64);
                    if (is_array($json)) {
                        $inputData = array_merge($inputData, $json);
                    }
                }
            }
            \input(data: $inputData);

            // ── 2. 미들웨어 실행 ──
            foreach ($this->middlewares as $mw) {
                $result = $mw($req, $res);
                if ($result === false) {
                    return;
                }
            }

            // ── 3. 출력 캡처 + Router 디스패치 ──
            ob_start();
            $statusCode = http_response_code() ?: 200;

            // Router 디스패치
            \router()->dispatch();

            $body = ob_get_clean() ?: '';

            // ── 4. 응답 전송 ──
            $res->status(http_response_code() ?: $statusCode);

            // headers_list()로 PHP가 설정한 헤더 가져오기
            foreach (headers_list() as $header) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $res->header(trim($parts[0]), trim($parts[1]));
                }
            }

            $res->end($body);

        } catch (\Throwable $e) {
            // ── 에러 응답 ──
            $debug = (bool) \config('app.debug', false);

            if (class_exists('Cat\\Log', false)) {
                try {
                    \logger()->error('Swoole Request Error: ' . $e->getMessage(), [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                } catch (\Throwable) {
                    // 로거 실패 시 무시
                }
            }

            $res->status(500);

            // API 라우트는 CatUI JSON 포맷으로 응답
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (str_starts_with($uri, '/api/')) {
                $msg = $debug ? $e->getMessage() : '서버 오류가 발생했습니다';
                $res->header('Content-Type', 'application/json; charset=utf-8');
                $res->end(json_encode([
                    'success'    => false,
                    'statusCode' => 500,
                    'data'       => null,
                    'message'    => $msg,
                    'error'      => [
                        'message' => $msg,
                        'name'    => 'InternalServerError',
                        'type'    => 'server',
                    ],
                    'timestamp'  => time(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $res->header('Content-Type', 'text/html; charset=utf-8');
                if ($debug) {
                    $res->end(
                        '<h1>CatPHP Swoole Error</h1>' .
                        '<p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>' .
                        '<p>' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>' .
                        '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>'
                    );
                } else {
                    $res->end('<h1>서버 오류</h1><p>잠시 후 다시 시도해 주세요.</p>');
                }
            }
        } finally {
            // 출력 버퍼가 남아 있으면 정리
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    /** Swoole Request → $_SERVER 변환 */
    private function buildServerVars(\Swoole\Http\Request $req): array
    {
        $server = [];

        // Swoole 서버 변수 매핑
        foreach (($req->server ?? []) as $key => $value) {
            $server[strtoupper($key)] = $value;
        }

        // HTTP 헤더 매핑 (HTTP_* 형식)
        foreach (($req->header ?? []) as $key => $value) {
            $phpKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $server[$phpKey] = $value;
        }

        // 필수 서버 변수 보정
        $server['REQUEST_METHOD'] = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $server['REQUEST_URI'] = $server['REQUEST_URI'] ?? '/';
        $server['SERVER_PROTOCOL'] = $server['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        $server['CONTENT_TYPE'] = $server['HTTP_CONTENT_TYPE'] ?? ($req->header['content-type'] ?? '');
        $server['CONTENT_LENGTH'] = $server['HTTP_CONTENT_LENGTH'] ?? ($req->header['content-length'] ?? '');
        $server['REMOTE_ADDR'] = $server['REMOTE_ADDR'] ?? '127.0.0.1';
        $server['SERVER_NAME'] = $server['HTTP_HOST'] ?? ($this->host . ':' . $this->port);
        $server['HTTP_HOST'] = $server['HTTP_HOST'] ?? ($this->host . ':' . $this->port);

        return $server;
    }

    // ════════════════════════════════════════════════
    // 내부: Hot Reload
    // ════════════════════════════════════════════════

    private function startHotReload(mixed $svr): void
    {
        if (!extension_loaded('inotify')) {
            // inotify 미설치 시 폴링 방식 폴백
            $this->startPollingReload($svr);
            return;
        }

        $paths = (array) \config('swoole.hot_reload_paths', [
            dirname(__DIR__) . '/catphp',
            dirname(__DIR__) . '/Public',
            dirname(__DIR__) . '/config',
        ]);

        $inotify = inotify_init();
        foreach ($paths as $path) {
            if (is_dir($path)) {
                inotify_add_watch($inotify, $path, IN_MODIFY | IN_CREATE | IN_DELETE);
            }
        }

        \Swoole\Event::add($inotify, function () use ($inotify, $svr): void {
            $events = inotify_read($inotify);
            if ($events !== false) {
                $svr->reload();
            }
        });
    }

    /** inotify 미설치 시 2초 간격 폴링 리로드 */
    private function startPollingReload(mixed $svr): void
    {
        $paths = (array) \config('swoole.hot_reload_paths', [
            dirname(__DIR__) . '/catphp',
            dirname(__DIR__) . '/Public',
            dirname(__DIR__) . '/config',
        ]);

        $lastMtime = time();

        \Swoole\Timer::tick(2000, function () use (&$lastMtime, $svr, $paths): void {
            foreach ($paths as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                // 서브디렉토리 포함 재귀 탐색
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }
                    $mtime = $file->getMTime();
                    if ($mtime > $lastMtime) {
                        $lastMtime = time();
                        $svr->reload();
                        return;
                    }
                }
            }
        });
    }

    // ════════════════════════════════════════════════
    // 내부: 연결 풀 정리
    // ════════════════════════════════════════════════

    private function destroyPools(): void
    {
        foreach ($this->pools as $name => $ch) {
            while (!$ch->isEmpty()) {
                $conn = $ch->pop(0.1);
                if ($conn instanceof \Redis) {
                    try { $conn->close(); } catch (\Throwable) {}
                }
                // PDO는 참조 해제만으로 닫힘
            }
            $ch->close();
        }
        $this->pools = [];
    }
}
