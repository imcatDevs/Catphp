<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\DbView — DB 구조 조회/탐색기
 *
 * MySQL / PostgreSQL / SQLite 호환 DB 메타데이터 조회.
 *
 * 사용법:
 *   dbview()->tables();                  // 테이블 목록
 *   dbview()->columns('users');          // 컬럼 정보
 *   dbview()->describe('users');         // 상세 (컬럼+인덱스+크기)
 *   dbview()->preview('users', 10);     // 데이터 미리보기
 *   dbview()->stats();                   // DB 전체 통계
 *   dbview()->size('users');             // 테이블 크기
 */
final class DbView
{
    private static ?self $instance = null;

    private readonly string $driver;

    private function __construct()
    {
        $this->driver = (string) \config('db.driver', 'mysql');
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * 테이블 목록
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return match ($this->driver) {
            'sqlite' => $this->sqliteTables(),
            'pgsql'  => $this->pgsqlTables(),
            default  => $this->mysqlTables(),
        };
    }

    /**
     * 컬럼 정보
     *
     * @return list<array{name: string, type: string, nullable: bool, default: mixed, key: string}>
     */
    public function columns(string $table): array
    {
        self::validateIdentifier($table);
        return $this->columnsInternal($table);
    }

    /** @internal describe()에서 중복 validateIdentifier 방지용 */
    private function columnsInternal(string $table): array
    {
        return match ($this->driver) {
            'sqlite' => $this->sqliteColumns($table),
            'pgsql'  => $this->pgsqlColumns($table),
            default  => $this->mysqlColumns($table),
        };
    }

    /**
     * 테이블 상세 정보 (컬럼 + 인덱스 + 행 수 + 크기)
     *
     * @return array{table: string, columns: list<array>, indexes: list<array>, row_count: int, size: string}
     */
    public function describe(string $table): array
    {
        self::validateIdentifier($table);

        return [
            'table'     => $table,
            'columns'   => $this->columnsInternal($table),
            'indexes'   => $this->indexesInternal($table),
            'row_count' => $this->rowCountInternal($table),
            'size'      => $this->sizeInternal($table),
        ];
    }

    /**
     * 데이터 미리보기
     *
     * @return list<array<string, mixed>>
     */
    public function preview(string $table, int $limit = 10): array
    {
        self::validateIdentifier($table);
        $limit = max(1, min($limit, 100));

        $stmt = \db()->raw("SELECT * FROM {$table} LIMIT ?", [$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 인덱스 정보
     *
     * @return list<array{name: string, columns: string, unique: bool}>
     */
    public function indexes(string $table): array
    {
        self::validateIdentifier($table);
        return $this->indexesInternal($table);
    }

    /** @internal */
    private function indexesInternal(string $table): array
    {
        return match ($this->driver) {
            'sqlite' => $this->sqliteIndexes($table),
            'pgsql'  => $this->pgsqlIndexes($table),
            default  => $this->mysqlIndexes($table),
        };
    }

    /** 테이블 행 수 */
    public function rowCount(string $table): int
    {
        self::validateIdentifier($table);
        return $this->rowCountInternal($table);
    }

    /** @internal */
    private function rowCountInternal(string $table): int
    {
        $stmt = \db()->raw("SELECT COUNT(*) FROM {$table}");
        return (int) $stmt->fetchColumn();
    }

    /** 테이블 크기 (사람이 읽기 쉬운 문자열) */
    public function size(string $table): string
    {
        self::validateIdentifier($table);
        return $this->sizeInternal($table);
    }

    /** @internal */
    private function sizeInternal(string $table): string
    {
        $bytes = match ($this->driver) {
            'sqlite' => $this->sqliteTableSize($table),
            'pgsql'  => $this->pgsqlTableSize($table),
            default  => $this->mysqlTableSize($table),
        };

        return $this->formatBytes($bytes);
    }

    /**
     * DB 전체 통계
     *
     * @return array{driver: string, database: string, tables: int, total_rows: int, total_size: string}
     */
    public function stats(): array
    {
        $tables = $this->tables();
        $totalRows = 0;
        $totalBytes = 0;

        foreach ($tables as $table) {
            $totalRows += $this->rowCount($table);
            $totalBytes += match ($this->driver) {
                'sqlite' => $this->sqliteTableSize($table),
                'pgsql'  => $this->pgsqlTableSize($table),
                default  => $this->mysqlTableSize($table),
            };
        }

        return [
            'driver'     => $this->driver,
            'database'   => (string) \config('db.dbname', ''),
            'tables'     => count($tables),
            'total_rows' => $totalRows,
            'total_size' => $this->formatBytes($totalBytes),
        ];
    }

    // ── MySQL ──

    /** @return list<string> */
    private function mysqlTables(): array
    {
        $rows = \db()->raw('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array{name: string, type: string, nullable: bool, default: mixed, key: string}> */
    private function mysqlColumns(string $table): array
    {
        $rows = \db()->raw("SHOW FULL COLUMNS FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'name'     => $row['Field'],
                'type'     => $row['Type'],
                'nullable' => $row['Null'] === 'YES',
                'default'  => $row['Default'],
                'key'      => $row['Key'],
            ];
        }

        return $result;
    }

    /** @return list<array{name: string, columns: string, unique: bool}> */
    private function mysqlIndexes(string $table): array
    {
        $rows = \db()->raw("SHOW INDEX FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        $grouped = [];

        foreach ($rows as $row) {
            $name = $row['Key_name'];
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'name'    => $name,
                    'columns' => [],
                    'unique'  => (int) $row['Non_unique'] === 0,
                ];
            }
            $grouped[$name]['columns'][] = $row['Column_name'];
        }

        $result = [];
        foreach ($grouped as $idx) {
            $idx['columns'] = implode(', ', $idx['columns']);
            $result[] = $idx;
        }

        return $result;
    }

    private function mysqlTableSize(string $table): int
    {
        $dbname = (string) \config('db.dbname', '');
        $stmt = \db()->raw(
            "SELECT (data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$dbname, $table]
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['size'] ?? 0);
    }

    // ── PostgreSQL ──

    /** @return list<string> */
    private function pgsqlTables(): array
    {
        $rows = \db()->raw(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        )->fetchAll(\PDO::FETCH_COLUMN);
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array{name: string, type: string, nullable: bool, default: mixed, key: string}> */
    private function pgsqlColumns(string $table): array
    {
        $rows = \db()->raw(
            "SELECT column_name, data_type, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ?
             ORDER BY ordinal_position",
            [$table]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // PK 정보 조회
        $pkRows = \db()->raw(
            "SELECT a.attname
             FROM pg_index i
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
             WHERE i.indrelid = ?::regclass AND i.indisprimary",
            [$table]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $pks = is_array($pkRows) ? $pkRows : [];

        $result = [];
        foreach ($rows as $row) {
            $name = $row['column_name'];
            $result[] = [
                'name'     => $name,
                'type'     => $row['data_type'],
                'nullable' => $row['is_nullable'] === 'YES',
                'default'  => $row['column_default'],
                'key'      => in_array($name, $pks, true) ? 'PRI' : '',
            ];
        }

        return $result;
    }

    /** @return list<array{name: string, columns: string, unique: bool}> */
    private function pgsqlIndexes(string $table): array
    {
        $rows = \db()->raw(
            "SELECT indexname, indexdef
             FROM pg_indexes
             WHERE schemaname = 'public' AND tablename = ?",
            [$table]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $unique = str_contains(strtoupper($row['indexdef']), 'UNIQUE');
            // 인덱스 컬럼 추출: (...) 안의 내용
            $cols = '';
            if (preg_match('/\((.+)\)/', $row['indexdef'], $m)) {
                $cols = $m[1];
            }
            $result[] = [
                'name'    => $row['indexname'],
                'columns' => $cols,
                'unique'  => $unique,
            ];
        }

        return $result;
    }

    private function pgsqlTableSize(string $table): int
    {
        $stmt = \db()->raw("SELECT pg_total_relation_size(?::regclass) AS size", [$table]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['size'] ?? 0);
    }

    // ── SQLite ──

    /** @return list<string> */
    private function sqliteTables(): array
    {
        $rows = \db()->raw(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(\PDO::FETCH_COLUMN);
        return is_array($rows) ? $rows : [];
    }

    /** @return list<array{name: string, type: string, nullable: bool, default: mixed, key: string}> */
    private function sqliteColumns(string $table): array
    {
        $rows = \db()->raw("PRAGMA table_info({$table})")->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $result[] = [
                'name'     => $row['name'],
                'type'     => $row['type'],
                'nullable' => (int) $row['notnull'] === 0,
                'default'  => $row['dflt_value'],
                'key'      => (int) $row['pk'] > 0 ? 'PRI' : '',
            ];
        }

        return $result;
    }

    /** @return list<array{name: string, columns: string, unique: bool}> */
    private function sqliteIndexes(string $table): array
    {
        $rows = \db()->raw("PRAGMA index_list({$table})")->fetchAll(\PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $idxName = $row['name'];
            $colRows = \db()->raw("PRAGMA index_info({$idxName})")->fetchAll(\PDO::FETCH_ASSOC);
            $cols = array_column($colRows, 'name');

            $result[] = [
                'name'    => $idxName,
                'columns' => implode(', ', $cols),
                'unique'  => (int) $row['unique'] === 1,
            ];
        }

        return $result;
    }

    private function sqliteTableSize(string $table): int
    {
        // dbstat 가상테이블 사용 시도 (SQLITE_ENABLE_DBSTAT_VTAB 필요)
        try {
            $stmt = \db()->raw(
                "SELECT SUM(pgsize) AS size FROM dbstat WHERE name = ?",
                [$table]
            );
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row !== false && $row['size'] !== null) {
                return (int) $row['size'];
            }
        } catch (\PDOException) {
            // dbstat 가상테이블 미지원 → 폴백
        }

        // 폴백: 행 수 기반 추정
        $rowCount = $this->rowCountInternal($table);
        return $rowCount * 100;
    }

    // ── 내부 ──

    /** SQL 식별자 검증 */
    private static function validateIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $name)) {
            throw new \InvalidArgumentException("유효하지 않은 SQL 식별자: {$name}");
        }
    }

    /** 바이트 → 사람이 읽기 쉬운 문자열 */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
