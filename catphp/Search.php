<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Search — 전문 검색
 *
 * @config array{
 *     driver?: string,     // 'fulltext' | 'like' (기본 fulltext)
 *     cache_ttl?: int,     // 검색 캐시 TTL (기본 300)
 * } search  → config('search.driver')
 */
final class Search
{
    private static ?self $instance = null;

    private ?string $queryStr = null;
    private ?string $tableName = null;
    private array $columns = [];
    private ?int $limitVal = null;
    private ?int $offsetVal = null;

    private function __construct(
        private readonly string $driver,
        private readonly int $cacheTtl,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            driver: \config('search.driver') ?? 'fulltext',
            cacheTtl: (int) (\config('search.cache_ttl') ?? 300),
        );
    }

    /** 검색어 설정 */
    public function query(string $query): self
    {
        $c = clone $this;
        $c->queryStr = \guard()->clean($query);
        return $c;
    }

    /** 검색 결과 제한 */
    public function limit(int $limit): self
    {
        $c = clone $this;
        $c->limitVal = $limit;
        return $c;
    }

    /** 검색 결과 오프셋 */
    public function offset(int $offset): self
    {
        $c = clone $this;
        $c->offsetVal = $offset;
        return $c;
    }

    /** 검색 대상 테이블/컬럼 설정 */
    public function in(string $table, array $columns): self
    {
        self::validateIdentifier($table);
        foreach ($columns as $col) {
            self::validateIdentifier($col);
        }
        $c = clone $this;
        $c->tableName = $table;
        $c->columns = $columns;
        return $c;
    }

    /** SQL 식별자 검증 */
    private static function validateIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $name)) {
            throw new \InvalidArgumentException("유효하지 않은 SQL 식별자: {$name}");
        }
    }

    /** 검색 결과 조회 */
    public function results(): array
    {
        if ($this->queryStr === null || $this->tableName === null || empty($this->columns)) {
            return [];
        }

        // 캐시 확인
        $cacheKey = "search:" . md5("{$this->tableName}:{$this->queryStr}:" . implode(',', $this->columns) . ":{$this->limitVal}:{$this->offsetVal}");
        if (class_exists('Cat\\Cache', false)) {
            $cached = \cache()->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $results = $this->executeSearch();

        // 캐시 저장
        if (class_exists('Cat\\Cache', false)) {
            \cache()->set($cacheKey, $results, $this->cacheTtl);
        }

        return $results;
    }

    /** 검색 결과 수 (SQL COUNT — 메모리 효율적) */
    public function count(): int
    {
        if ($this->queryStr === null || $this->tableName === null || empty($this->columns)) {
            return 0;
        }

        $dbDriver = \config('db.driver') ?? 'mysql';

        if ($this->driver === 'fulltext' && $dbDriver === 'mysql') {
            $cols = implode(', ', $this->columns);
            $sql = "SELECT COUNT(*) FROM {$this->tableName} WHERE MATCH({$cols}) AGAINST(? IN BOOLEAN MODE)";
            $stmt = \db()->raw($sql, [$this->queryStr]);
        } elseif ($this->driver === 'fulltext' && $dbDriver === 'pgsql') {
            $tsvector = implode(" || ' ' || ", $this->columns);
            $sql = "SELECT COUNT(*) FROM {$this->tableName} WHERE to_tsvector('simple', {$tsvector}) @@ plainto_tsquery('simple', ?)";
            $stmt = \db()->raw($sql, [$this->queryStr]);
        } elseif ($this->driver === 'fulltext' && $dbDriver === 'sqlite') {
            // SQLite: FTS5 테이블 있으면 사용, 없으면 LIKE
            $ftsTable = $this->tableName . '_fts';
            if ($this->hasFtsTable($ftsTable)) {
                $stmt = \db()->raw("SELECT COUNT(*) FROM {$ftsTable} WHERE {$ftsTable} MATCH ?", [$this->queryStr]);
            } else {
                $stmt = $this->likeCountStmt();
            }
        } else {
            $conditions = [];
            $bindings = [];
            $escaped = addcslashes($this->queryStr ?? '', '%_\\');
            foreach ($this->columns as $col) {
                $conditions[] = "{$col} LIKE ?";
                $bindings[] = "%{$escaped}%";
            }
            $sql = "SELECT COUNT(*) FROM {$this->tableName} WHERE " . implode(' OR ', $conditions);
            $stmt = \db()->raw($sql, $bindings);
        }

        return (int) $stmt->fetchColumn();
    }

    /** LIKE COUNT 문장 생성 */
    private function likeCountStmt(): \PDOStatement
    {
        $conditions = [];
        $bindings = [];
        $escaped = addcslashes($this->queryStr ?? '', '%_\\');
        foreach ($this->columns as $col) {
            $conditions[] = "{$col} LIKE ?";
            $bindings[] = "%{$escaped}%";
        }
        $sql = "SELECT COUNT(*) FROM {$this->tableName} WHERE " . implode(' OR ', $conditions);
        return \db()->raw($sql, $bindings);
    }

    /** 검색어 하이라이트 */
    public function highlight(string $text, string $tag = 'mark'): string
    {
        if ($this->queryStr === null || $this->queryStr === '') {
            return $text;
        }

        // 태그명 살균 (영숫자만 허용)
        $safeTag = preg_replace('/[^a-zA-Z0-9]/', '', $tag) ?: 'mark';

        $escaped = htmlspecialchars($this->queryStr, ENT_QUOTES, 'UTF-8');
        return preg_replace(
            '/(' . preg_quote($escaped, '/') . ')/iu',
            "<{$safeTag}>$1</{$safeTag}>",
            htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        ) ?? $text;
    }

    /**
     * FTS5 인덱스 생성 (SQLite 전용)
     *
     * SQLite에서 전문 검색을 위한 FTS5 가상 테이블 생성.
     * MySQL FULLTEXT, PostgreSQL tsvector와 동일한 기능 제공.
     *
     * @param string $table 원본 테이블명
     * @param array $columns 인덱싱할 컬럼
     * @param string|null $ftsName FTS 테이블명 (기본: {table}_fts)
     * @return bool 생성 성공 여부
     */
    public function createFtsIndex(string $table, array $columns, ?string $ftsName = null): bool
    {
        $dbDriver = \config('db.driver') ?? 'mysql';
        if ($dbDriver !== 'sqlite') {
            // SQLite만 지원, 다른 DB는 네이티브 기능 사용
            return false;
        }

        self::validateIdentifier($table);
        foreach ($columns as $col) {
            self::validateIdentifier($col);
        }

        $ftsTable = $ftsName ?? $table . '_fts';
        self::validateIdentifier($ftsTable);

        $cols = implode(', ', $columns);

        try {
            // FTS5 가상 테이블 생성
            \db()->raw("CREATE VIRTUAL TABLE IF NOT EXISTS {$ftsTable} USING fts5({$cols}, content='{$table}')");

            // 기존 데이터 동기화
            $colList = implode(', ', $columns);
            \db()->raw("INSERT INTO {$ftsTable}({$colList}) SELECT {$colList} FROM {$table}");

            return true;
        } catch (\Throwable $e) {
            if (class_exists('Cat\\Log', false)) {
                \logger()->error('FTS5 인덱스 생성 실패', ['table' => $table, 'error' => $e->getMessage()]);
            }
            return false;
        }
    }

    /**
     * FTS5 인덱스 삭제
     *
     * @param string $ftsName FTS 테이블명
     * @return bool 삭제 성공 여부
     */
    public function dropFtsIndex(string $ftsName): bool
    {
        self::validateIdentifier($ftsName);

        try {
            \db()->raw("DROP TABLE IF EXISTS {$ftsName}");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * FTS5 인덱스 동기화 (데이터 변경 후 호출)
     *
     * @param string $table 원본 테이블명
     * @param array $columns 컬럼
     * @param string|null $ftsName FTS 테이블명
     * @return bool 동기화 성공 여부
     */
    public function syncFtsIndex(string $table, array $columns, ?string $ftsName = null): bool
    {
        $ftsTable = $ftsName ?? $table . '_fts';
        self::validateIdentifier($table);
        self::validateIdentifier($ftsTable);

        try {
            // 기존 FTS 데이터 삭제 후 재동기화
            \db()->raw("DELETE FROM {$ftsTable}");
            $colList = implode(', ', $columns);
            \db()->raw("INSERT INTO {$ftsTable}({$colList}) SELECT {$colList} FROM {$table}");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** 검색 실행 (드라이버별 분기) */
    private function executeSearch(): array
    {
        $dbDriver = \config('db.driver') ?? 'mysql';

        return match ($this->driver) {
            'fulltext' => match ($dbDriver) {
                'mysql'  => $this->mysqlFulltext(),
                'pgsql'  => $this->pgsqlTsvector(),
                'sqlite' => $this->sqliteFts(),
                default  => $this->likeFallback(),
            },
            default => $this->likeFallback(),
        };
    }

    /** MySQL FULLTEXT (MATCH ... AGAINST) */
    private function mysqlFulltext(): array
    {
        $cols = implode(', ', $this->columns);
        $limit = $this->limitVal ?? 100;
        $bindings = [$this->queryStr, $this->queryStr, $limit];
        $sql = "SELECT *, MATCH({$cols}) AGAINST(? IN BOOLEAN MODE) AS _score FROM {$this->tableName} WHERE MATCH({$cols}) AGAINST(? IN BOOLEAN MODE) ORDER BY _score DESC LIMIT ?";
        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offsetVal;
        }
        $stmt = \db()->raw($sql, $bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** PostgreSQL tsvector (to_tsvector ... plainto_tsquery) */
    private function pgsqlTsvector(): array
    {
        $tsvector = implode(" || ' ' || ", $this->columns);
        $limit = $this->limitVal ?? 100;
        $bindings = [$this->queryStr, $this->queryStr, $limit];
        $sql = "SELECT *, ts_rank(to_tsvector('simple', {$tsvector}), plainto_tsquery('simple', ?)) AS _score FROM {$this->tableName} WHERE to_tsvector('simple', {$tsvector}) @@ plainto_tsquery('simple', ?) ORDER BY _score DESC LIMIT ?";
        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offsetVal;
        }
        $stmt = \db()->raw($sql, $bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** SQLite FTS5 (자동 감지 + 폴백) */
    private function sqliteFts(): array
    {
        $ftsTable = $this->tableName . '_fts';

        // FTS5 테이블 존재 확인
        if ($this->hasFtsTable($ftsTable)) {
            return $this->sqliteFts5($ftsTable);
        }

        return $this->likeFallback();
    }

    /** FTS5 테이블 존재 확인 */
    private function hasFtsTable(string $ftsTable): bool
    {
        try {
            $result = \db()->raw(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
                [$ftsTable]
            )->fetch();
            return $result !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** SQLite FTS5 검색 실행 */
    private function sqliteFts5(string $ftsTable): array
    {
        $cols = implode(', ', $this->columns);
        $limit = $this->limitVal ?? 100;
        $bindings = [$this->queryStr, $limit];

        $sql = "SELECT *, bm25({$ftsTable}) AS _score FROM {$ftsTable} WHERE {$ftsTable} MATCH ? ORDER BY _score DESC LIMIT ?";
        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offsetVal;
        }

        $stmt = \db()->raw($sql, $bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** LIKE 폴백 (모든 DB 호환, wildcard 이스케이프 적용) */
    private function likeFallback(): array
    {
        $conditions = [];
        $bindings = [];
        $escaped = addcslashes($this->queryStr ?? '', '%_\\');

        foreach ($this->columns as $col) {
            $conditions[] = "{$col} LIKE ?";
            $bindings[] = "%{$escaped}%";
        }

        $whereSql = implode(' OR ', $conditions);
        $limit = $this->limitVal ?? 100;
        $bindings[] = $limit;
        $sql = "SELECT * FROM {$this->tableName} WHERE {$whereSql} LIMIT ?";
        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offsetVal;
        }

        $stmt = \db()->raw($sql, $bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
