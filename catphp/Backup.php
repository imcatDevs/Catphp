<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Backup — DB 백업/복원
 *
 * MySQL(mysqldump), PostgreSQL(pg_dump), SQLite(파일 복사) 지원.
 *
 * ⚠ Windows 환경: mysqldump/pg_dump가 시스템 PATH에 등록되어 있어야 합니다.
 *   XAMPP: C:\xampp\mysql\bin, PostgreSQL: C:\Program Files\PostgreSQL\16\bin 등.
 *
 * 사용법:
 *   backup()->database();                     // 자동 경로에 저장
 *   backup()->database('custom/path.sql');    // 지정 경로
 *   backup()->restore('backup_2024.sql');     // 복원
 *   backup()->list();                         // 백업 목록
 *   backup()->clean(30);                      // 30일 이전 삭제
 *   backup()->latest();                       // 최신 백업 경로
 *
 * @config array{
 *     path?: string,       // 백업 저장 디렉토리 (기본: storage/backup)
 *     keep_days?: int,     // 자동 정리 보관 일수 (기본: 30)
 *     compress?: bool,     // gzip 압축 여부 (기본: false)
 * } backup  → config('backup.path')
 */
final class Backup
{
    private static ?self $instance = null;

    private readonly string $backupPath;
    private readonly int $keepDays;
    private readonly bool $compress;
    private readonly string $driver;

    private function __construct()
    {
        $this->backupPath = (string) \config('backup.path', dirname(__DIR__) . '/storage/backup');
        $this->keepDays = (int) \config('backup.keep_days', 30);
        $this->compress = (bool) \config('backup.compress', false);
        $this->driver = (string) \config('db.driver', 'mysql');
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * DB 백업 실행
     *
     * @param string|null $path 저장 경로 (null이면 자동 생성)
     * @return string 생성된 백업 파일 경로
     */
    public function database(?string $path = null): string
    {
        $this->ensureDir();

        if ($path === null) {
            $ext = $this->driver === 'sqlite' ? 'db' : 'sql';
            if ($this->compress && $this->driver !== 'sqlite') {
                $ext .= '.gz';
            }
            $path = $this->backupPath . '/' . date('Ymd_His') . '_' . $this->driver . '.' . $ext;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return match ($this->driver) {
            'sqlite' => $this->backupSqlite($path),
            'pgsql'  => $this->backupPgsql($path),
            default  => $this->backupMysql($path),
        };
    }

    /**
     * DB 복원
     *
     * @param string $path 백업 파일 경로
     */
    public function restore(string $path): bool
    {
        // 경로 트래버설 방어: backupPath 내부 또는 절대 경로의 실제 파일만 허용
        $real = realpath($path);
        $baseReal = realpath($this->backupPath);
        if ($real === false || !is_file($real)) {
            throw new \RuntimeException("백업 파일 없음: {$path}");
        }
        if ($baseReal !== false && !str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR) && $real !== $baseReal) {
            if (class_exists('Cat\\Log', false)) {
                \logger()->warn('Backup restore: backupPath 외부 파일 접근 차단', ['path' => $path, 'real' => $real]);
            }
            throw new \RuntimeException("보안: backupPath 외부 파일 복원 차단 — {$path}");
        }
        $path = $real;

        return match ($this->driver) {
            'sqlite' => $this->restoreSqlite($path),
            'pgsql'  => $this->restorePgsql($path),
            default  => $this->restoreMysql($path),
        };
    }

    /**
     * 백업 파일 목록 (최신순)
     *
     * @return array<int, array{name: string, path: string, size: int, date: string}>
     */
    public function list(): array
    {
        if (!is_dir($this->backupPath)) {
            return [];
        }

        $files = glob($this->backupPath . '/*') ?: [];
        $result = [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $result[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => (int) filesize($file),
                'date' => date('Y-m-d H:i:s', (int) filemtime($file)),
            ];
        }

        // 최신순 정렬
        usort($result, fn(array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $result;
    }

    /** 최신 백업 파일 경로 반환 */
    public function latest(): ?string
    {
        $list = $this->list();
        return $list[0]['path'] ?? null;
    }

    /**
     * N일 이전 백업 파일 삭제
     *
     * @return int 삭제된 파일 수
     */
    public function clean(int $days = 0): int
    {
        $days = $days > 0 ? $days : $this->keepDays;

        if (!is_dir($this->backupPath)) {
            return 0;
        }

        $threshold = time() - ($days * 86400);
        $files = glob($this->backupPath . '/*') ?: [];
        $deleted = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** 백업 디렉토리 경로 */
    public function getPath(): string
    {
        return $this->backupPath;
    }

    // ── MySQL 백업/복원 ──

    private function backupMysql(string $path): string
    {
        $cfg = (array) \config('db');
        $host = escapeshellarg($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 3306);
        $user = escapeshellarg($cfg['user'] ?? 'root');
        $dbname = escapeshellarg($cfg['dbname'] ?? '');
        $pass = $cfg['pass'] ?? '';

        $cmd = "mysqldump --host={$host} --port={$port} --user={$user}";
        if ($pass !== '') {
            $cmd .= ' --password=' . escapeshellarg($pass);
        }
        $cmd .= " --single-transaction --routines --triggers {$dbname}";

        if ($this->compress) {
            $cmd .= ' | gzip';
        }

        $cmd .= ' > ' . escapeshellarg($path);

        $this->execCommand($cmd);

        clearstatcache(true, $path);
        if (!is_file($path) || filesize($path) === 0) {
            throw new \RuntimeException('MySQL 백업 실패: 파일이 비어 있습니다.');
        }

        return $path;
    }

    private function restoreMysql(string $path): bool
    {
        $cfg = (array) \config('db');
        $host = escapeshellarg($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 3306);
        $user = escapeshellarg($cfg['user'] ?? 'root');
        $dbname = escapeshellarg($cfg['dbname'] ?? '');
        $pass = $cfg['pass'] ?? '';

        $cmd = "mysql --host={$host} --port={$port} --user={$user}";
        if ($pass !== '') {
            $cmd .= ' --password=' . escapeshellarg($pass);
        }
        $cmd .= " {$dbname}";

        $isGzip = str_ends_with($path, '.gz');
        if ($isGzip) {
            $cmd = 'gunzip -c ' . escapeshellarg($path) . ' | ' . $cmd;
        } else {
            $cmd .= ' < ' . escapeshellarg($path);
        }

        $this->execCommand($cmd);
        return true;
    }

    // ── PostgreSQL 백업/복원 ──

    private function backupPgsql(string $path): string
    {
        $cfg = (array) \config('db');
        $host = escapeshellarg($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 5432);
        $user = escapeshellarg($cfg['user'] ?? 'postgres');
        $dbname = escapeshellarg($cfg['dbname'] ?? '');

        $env = '';
        $pass = $cfg['pass'] ?? '';
        if ($pass !== '') {
            // PGPASSWORD 환경변수로 전달 (보안: 프로세스 환경만 노출)
            $env = 'PGPASSWORD=' . escapeshellarg($pass) . ' ';
        }

        $cmd = "{$env}pg_dump --host={$host} --port={$port} --username={$user} --format=plain --no-owner {$dbname}";

        if ($this->compress) {
            $cmd .= ' | gzip';
        }

        $cmd .= ' > ' . escapeshellarg($path);

        $this->execCommand($cmd);

        clearstatcache(true, $path);
        if (!is_file($path) || filesize($path) === 0) {
            throw new \RuntimeException('PostgreSQL 백업 실패: 파일이 비어 있습니다.');
        }

        return $path;
    }

    private function restorePgsql(string $path): bool
    {
        $cfg = (array) \config('db');
        $host = escapeshellarg($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 5432);
        $user = escapeshellarg($cfg['user'] ?? 'postgres');
        $dbname = escapeshellarg($cfg['dbname'] ?? '');

        $env = '';
        $pass = $cfg['pass'] ?? '';
        if ($pass !== '') {
            $env = 'PGPASSWORD=' . escapeshellarg($pass) . ' ';
        }

        $isGzip = str_ends_with($path, '.gz');
        if ($isGzip) {
            $cmd = 'gunzip -c ' . escapeshellarg($path) . " | {$env}psql --host={$host} --port={$port} --username={$user} {$dbname}";
        } else {
            $cmd = "{$env}psql --host={$host} --port={$port} --username={$user} {$dbname} < " . escapeshellarg($path);
        }

        $this->execCommand($cmd);
        return true;
    }

    // ── SQLite 백업/복원 ──

    private function backupSqlite(string $path): string
    {
        $cfg = (array) \config('db');
        $dbPath = $cfg['dbname'] ?? $cfg['path'] ?? '';

        if ($dbPath === '' || !is_file($dbPath)) {
            throw new \RuntimeException("SQLite DB 파일 없음: {$dbPath}");
        }

        // WAL 모드: 체크포인트로 WAL→DB 플러시 시도 (실패해도 계속 진행)
        try {
            \db()->raw('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable) {
            // WAL 모드가 아니거나 체크포인트 실패 — 무시
        }

        if (!copy($dbPath, $path)) {
            throw new \RuntimeException('SQLite 백업 실패: 파일 복사 오류');
        }

        // WAL/SHM 보조 파일도 복사 (존재 시)
        foreach (['-wal', '-shm'] as $suffix) {
            $auxFile = $dbPath . $suffix;
            if (is_file($auxFile)) {
                copy($auxFile, $path . $suffix);
            }
        }

        return $path;
    }

    private function restoreSqlite(string $path): bool
    {
        $cfg = (array) \config('db');
        $dbPath = $cfg['dbname'] ?? $cfg['path'] ?? '';

        if ($dbPath === '') {
            throw new \RuntimeException('SQLite DB 경로가 설정되지 않았습니다.');
        }

        // 복원 전 현재 DB 백업
        if (is_file($dbPath)) {
            $safePath = $dbPath . '.before_restore.' . date('Ymd_His');
            copy($dbPath, $safePath);
        }

        if (!copy($path, $dbPath)) {
            throw new \RuntimeException('SQLite 복원 실패: 파일 복사 오류');
        }

        return true;
    }

    // ── 내부 ──

    /** 백업 디렉토리 생성 보장 */
    private function ensureDir(): void
    {
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /** 셸 명령 실행 + 에러 처리 */
    private function execCommand(string $cmd): void
    {
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);

        if ($code !== 0) {
            $msg = implode("\n", $output);
            throw new \RuntimeException("백업/복원 명령 실패 (code={$code}): {$msg}");
        }
    }
}
