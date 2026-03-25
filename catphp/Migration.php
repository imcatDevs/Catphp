<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Migration — DB 스키마 버전 관리
 *
 * 마이그레이션 파일 기반 테이블 생성/수정/롤백.
 *
 * 사용법:
 *   php cli.php migrate              — 미실행 마이그레이션 실행
 *   php cli.php migrate:rollback     — 마지막 배치 롤백
 *   php cli.php migrate:status       — 상태 확인
 *   php cli.php migrate:create users — 마이그레이션 파일 생성
 *   php cli.php migrate:fresh        — 전체 롤백 후 재실행
 *
 * 마이그레이션 파일 형식 (migrations/20260322_120000_create_users.php):
 *   return [
 *       'up'   => "CREATE TABLE users (...)",
 *       'down' => "DROP TABLE IF EXISTS users",
 *   ];
 *
 *   // 또는 여러 SQL:
 *   return [
 *       'up'   => ["CREATE TABLE ...", "CREATE INDEX ..."],
 *       'down' => ["DROP INDEX ...", "DROP TABLE ..."],
 *   ];
 */
final class Migration
{
    private static ?self $instance = null;

    private string $migrationsPath;
    private string $table;

    private function __construct()
    {
        $this->migrationsPath = (string) \config('migration.path', dirname(__DIR__) . '/migrations');
        $this->table = (string) \config('migration.table', 'migrations');
        self::validateIdentifier($this->table);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 마이그레이션 테이블 생성 (없으면) */
    public function ensureTable(): self
    {
        $t = $this->table;
        $driver = (string) \config('db.driver', 'mysql');

        if ($driver === 'sqlite') {
            \db()->raw("CREATE TABLE IF NOT EXISTS {$t} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL DEFAULT 1,
                executed_at TEXT NOT NULL DEFAULT (datetime('now'))
            )");
        } else {
            \db()->raw("CREATE TABLE IF NOT EXISTS {$t} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL DEFAULT 1,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
        }

        return $this;
    }

    /**
     * 미실행 마이그레이션 실행
     *
     * @return list<string> 실행된 파일명
     */
    public function run(): array
    {
        $this->ensureTable();
        $ran = $this->getRan();
        $files = $this->getPendingFiles($ran);

        if ($files === []) {
            return [];
        }

        $batch = $this->getNextBatch();
        $executed = [];

        try {
            \db()->raw('BEGIN');

            foreach ($files as $file) {
                $migration = $this->loadFile($file);
                $up = $migration['up'] ?? null;

                if ($up === null) {
                    continue;
                }

                $this->executeSql($up);
                $this->recordMigration(basename($file, '.php'), $batch);
                $executed[] = basename($file, '.php');
            }

            \db()->raw('COMMIT');
        } catch (\Throwable $e) {
            \db()->raw('ROLLBACK');
            throw $e;
        }

        return $executed;
    }

    /**
     * 마지막 배치 롤백
     *
     * @return list<string> 롤백된 마이그레이션
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureTable();
        $batch = $this->getLastBatch();

        if ($batch === 0) {
            return [];
        }

        $rolled = [];

        try {
            \db()->raw('BEGIN');

            for ($i = 0; $i < $steps && $batch > 0; $i++, $batch--) {
                $migrations = $this->getMigrationsByBatch($batch);

                foreach (array_reverse($migrations) as $name) {
                    $file = $this->migrationsPath . '/' . $name . '.php';
                    if (!is_file($file)) {
                        continue;
                    }

                    $migration = $this->loadFile($file);
                    $down = $migration['down'] ?? null;

                    if ($down !== null) {
                        $this->executeSql($down);
                    }

                    $this->removeMigration($name);
                    $rolled[] = $name;
                }
            }

            \db()->raw('COMMIT');
        } catch (\Throwable $e) {
            \db()->raw('ROLLBACK');
            throw $e;
        }

        return $rolled;
    }

    /** 전체 롤백 후 재실행 */
    public function fresh(): array
    {
        $this->ensureTable();

        // 모든 마이그레이션을 역순으로 롤백 (트랜잭션 보호)
        $all = $this->getRan();

        try {
            \db()->raw('BEGIN');

            foreach (array_reverse($all) as $name) {
                $file = $this->migrationsPath . '/' . $name . '.php';
                if (is_file($file)) {
                    $migration = $this->loadFile($file);
                    $down = $migration['down'] ?? null;
                    if ($down !== null) {
                        $this->executeSql($down);
                    }
                }
            }

            // 마이그레이션 테이블 초기화
            \db()->raw("DELETE FROM {$this->table}");

            \db()->raw('COMMIT');
        } catch (\Throwable $e) {
            \db()->raw('ROLLBACK');
            throw $e;
        }

        // 전체 재실행
        return $this->run();
    }

    /**
     * 마이그레이션 상태 목록
     *
     * @return list<array{name:string, batch:int|null, status:string}>
     */
    public function status(): array
    {
        $this->ensureTable();
        $ran = $this->getRanWithBatch();
        $files = $this->getAllFiles();
        $result = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $batch = $ran[$name] ?? null;
            $result[] = [
                'name'   => $name,
                'batch'  => $batch,
                'status' => $batch !== null ? 'Ran' : 'Pending',
            ];
        }

        return $result;
    }

    /**
     * 마이그레이션 파일 생성
     *
     * @return string 생성된 파일 경로
     */
    public function create(string $name, string $table = '', string $type = 'create'): string
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
        $fileName = "{$timestamp}_{$slug}.php";
        $filePath = $this->migrationsPath . '/' . $fileName;

        $table = $table ?: $slug;
        self::validateIdentifier($table);

        $driver = (string) \config('db.driver', 'mysql');

        if ($type === 'create') {
            $up = $this->generateCreateTable($table, $driver);
            $down = "DROP TABLE IF EXISTS {$table}";
        } else {
            $up = "ALTER TABLE {$table} ADD COLUMN column_name VARCHAR(255) NULL";
            $down = "ALTER TABLE {$table} DROP COLUMN column_name";
        }

        $content = "<?php\ndeclare(strict_types=1);\n\n"
            . "// 마이그레이션: {$name}\n"
            . "// 생성일: " . date('Y-m-d H:i:s') . "\n\n"
            . "return [\n"
            . "    'up'   => " . var_export($up, true) . ",\n"
            . "    'down' => " . var_export($down, true) . ",\n"
            . "];\n";

        file_put_contents($filePath, $content, LOCK_EX);

        return $filePath;
    }

    /** 마이그레이션 경로 */
    public function getPath(): string
    {
        return $this->migrationsPath;
    }

    // ── 내부 ──

    /** SQL 실행 (문자열 또는 배열) */
    private function executeSql(string|array $sql): void
    {
        $queries = is_array($sql) ? $sql : [$sql];
        foreach ($queries as $query) {
            $query = trim((string) $query);
            if ($query !== '') {
                \db()->raw($query);
            }
        }
    }

    /**
     * 실행된 마이그레이션 목록
     *
     * @return list<string>
     */
    private function getRan(): array
    {
        $rows = \db()->raw("SELECT migration FROM {$this->table} ORDER BY batch, id")
            ->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($rows, 'migration');
    }

    /**
     * 실행된 마이그레이션 + 배치
     *
     * @return array<string, int>
     */
    private function getRanWithBatch(): array
    {
        $rows = \db()->raw("SELECT migration, batch FROM {$this->table}")
            ->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['migration']] = (int) $row['batch'];
        }
        return $result;
    }

    /**
     * 배치별 마이그레이션
     *
     * @return list<string>
     */
    private function getMigrationsByBatch(int $batch): array
    {
        $rows = \db()->raw(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY id",
            [$batch]
        )->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($rows, 'migration');
    }

    /** 다음 배치 번호 */
    private function getNextBatch(): int
    {
        return $this->getLastBatch() + 1;
    }

    /** 마지막 배치 번호 */
    private function getLastBatch(): int
    {
        $rows = \db()->raw("SELECT MAX(batch) as max_batch FROM {$this->table}")
            ->fetchAll(\PDO::FETCH_ASSOC);
        return (int) ($rows[0]['max_batch'] ?? 0);
    }

    /** 마이그레이션 기록 추가 */
    private function recordMigration(string $name, int $batch): void
    {
        \db()->table($this->table)->insert([
            'migration' => $name,
            'batch'     => $batch,
        ]);
    }

    /** 마이그레이션 기록 삭제 */
    private function removeMigration(string $name): void
    {
        \db()->table($this->table)->where('migration', $name)->delete();
    }

    /**
     * 미실행 파일 목록
     *
     * @param list<string> $ran
     * @return list<string>
     */
    private function getPendingFiles(array $ran): array
    {
        $files = $this->getAllFiles();
        return array_filter($files, fn($f) => !in_array(basename($f, '.php'), $ran, true));
    }

    /**
     * 모든 마이그레이션 파일 (정렬됨)
     *
     * @return list<string>
     */
    private function getAllFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    /** 마이그레이션 파일 로드 */
    private function loadFile(string $file): array
    {
        $result = require $file;
        return is_array($result) ? $result : [];
    }

    /** SQL 식별자(테이블/마이그레이션명) 검증 */
    private static function validateIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("유효하지 않은 SQL 식별자: {$name}");
        }
    }

    /** CREATE TABLE 템플릿 생성 */
    private function generateCreateTable(string $table, string $driver): string
    {
        if ($driver === 'sqlite') {
            return "CREATE TABLE IF NOT EXISTS {$table} (\n"
                 . "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n"
                 . "    created_at TEXT NOT NULL DEFAULT (datetime('now')),\n"
                 . "    updated_at TEXT\n"
                 . ")";
        }

        return "CREATE TABLE IF NOT EXISTS {$table} (\n"
             . "    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n"
             . "    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
             . "    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP\n"
             . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }
}
