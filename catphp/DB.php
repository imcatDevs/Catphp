<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\DB — DB ORM (MySQL / PostgreSQL / SQLite)
 *
 * @config array{
 *     driver: string,    // mysql | pgsql | sqlite
 *     host?: string,     // DB 호스트 (mysql/pgsql)
 *     port?: int,        // DB 포트
 *     dbname?: string,   // 데이터베이스명
 *     user?: string,     // 사용자
 *     pass?: string,     // 비밀번호
 *     path?: string,     // SQLite 파일 경로
 *     charset?: string,  // 문자셋 (기본 utf8mb4)
 * } db  → config('db.driver')
 */
final class DB
{
    private static ?self $instance = null;
    private ?\PDO $pdo = null;

    private string $table = '';
    /** @var array<string> SELECT 컬럼 (빈 배열 = *) */
    private array $selectColumns = [];
    /** @var array<int, array{0: string, 1: string, 2: mixed, 3: string}> [col, op, val, logic] */
    private array $wheres = [];
    private string $orderBy = '';
    private string $groupByCol = '';
    private ?int $limitVal = null;
    private ?int $offsetVal = null;

    /** @var array<string> 허용 연산자 화이트리스트 */
    private const ALLOWED_OPERATORS = ['=', '<', '>', '<=', '>=', '!=', '<>', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];

    private function __construct(
        private readonly array $config,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            config: \config('db') ?? [],
        );
    }

    /** PDO 지연 연결 (이중 지연 로딩) */
    private function pdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $cfg = $this->config;
        $driver = $cfg['driver'] ?? 'mysql';

        $dsn = match ($driver) {
            'sqlite' => 'sqlite:' . ($cfg['dbname'] ?? $cfg['path'] ?? ':memory:'),
            'pgsql'  => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $cfg['host'] ?? '127.0.0.1',
                $cfg['port'] ?? 5432,
                $cfg['dbname'] ?? '',
            ),
            default  => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1',
                $cfg['port'] ?? 3306,
                $cfg['dbname'] ?? '',
                $cfg['charset'] ?? 'utf8mb4',
            ),
        };

        $this->pdo = new \PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // SQLite PRAGMA 적용 (config 설정이 있는 경우, 화이트리스트 검증)
        if ($driver === 'sqlite') {
            if (isset($cfg['journal_mode'])) {
                $allowed = ['DELETE', 'WAL', 'TRUNCATE', 'PERSIST', 'MEMORY', 'OFF'];
                $mode = strtoupper((string) $cfg['journal_mode']);
                if (in_array($mode, $allowed, true)) {
                    $this->pdo->exec('PRAGMA journal_mode = ' . $mode);
                }
            }
            if (isset($cfg['foreign_keys'])) {
                $this->pdo->exec('PRAGMA foreign_keys = ' . ($cfg['foreign_keys'] ? 'ON' : 'OFF'));
            }
            if (isset($cfg['busy_timeout'])) {
                $this->pdo->exec('PRAGMA busy_timeout = ' . (int) $cfg['busy_timeout']);
            }
            if (isset($cfg['synchronous'])) {
                $allowed = ['OFF', 'NORMAL', 'FULL', 'EXTRA'];
                $sync = strtoupper((string) $cfg['synchronous']);
                if (in_array($sync, $allowed, true)) {
                    $this->pdo->exec('PRAGMA synchronous = ' . $sync);
                }
            }
        }

        return $this->pdo;
    }

    /** 이뮤터블 체이닝을 위한 클론 */
    private function clone(): self
    {
        return clone $this;
    }

    public function table(string $table): self
    {
        self::validateIdentifier($table);
        $q = $this->clone();
        $q->table = $table;
        $q->selectColumns = [];
        $q->wheres = [];
        $q->orderBy = '';
        $q->groupByCol = '';
        $q->limitVal = null;
        $q->offsetVal = null;
        return $q;
    }

    /** SELECT 컬럼 지정 */
    public function select(string ...$columns): self
    {
        foreach ($columns as $col) {
            self::validateIdentifier($col);
        }
        $q = $this->clone();
        $q->selectColumns = $columns;
        return $q;
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $op = strtoupper(trim($operator));
        if (!in_array($op, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException("허용되지 않는 연산자: {$operator}");
        }
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->wheres[] = [$column, $op, $value, 'AND'];
        return $q;
    }

    /** OR WHERE 조건 */
    public function orWhere(string $column, mixed $value, string $operator = '='): self
    {
        $op = strtoupper(trim($operator));
        if (!in_array($op, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException("허용되지 않는 연산자: {$operator}");
        }
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->wheres[] = [$column, $op, $value, 'OR'];
        return $q;
    }

    /** WHERE IS NULL 조건 */
    public function whereNull(string $column): self
    {
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->wheres[] = [$column, 'IS NULL', null, 'AND'];
        return $q;
    }

    /** WHERE IS NOT NULL 조건 */
    public function whereNotNull(string $column): self
    {
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->wheres[] = [$column, 'IS NOT NULL', null, 'AND'];
        return $q;
    }

    /** WHERE IN 조건 */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('whereIn은 비어 있지 않은 배열이 필요합니다.');
        }
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->wheres[] = [$column, 'IN', $values, 'AND'];
        return $q;
    }

    /** WHERE NOT IN 조건 */
    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('whereNotIn은 비어 있지 않은 배열이 필요합니다.');
        }
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->wheres[] = [$column, 'NOT IN', $values, 'AND'];
        return $q;
    }

    /** WHERE BETWEEN 조건 */
    public function whereBetween(string $column, array $range): self
    {
        if (count($range) !== 2) {
            throw new \InvalidArgumentException('whereBetween은 [min, max] 배열이 필요합니다.');
        }
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->wheres[] = [$column, 'BETWEEN', $range, 'AND'];
        return $q;
    }

    /** GROUP BY */
    public function groupBy(string $column): self
    {
        self::validateIdentifier($column);
        $q = $this->clone();
        $q->groupByCol = $column;
        return $q;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        self::validateIdentifier($column);
        $q = $this->clone();
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $q->orderBy = "{$column} {$dir}";
        return $q;
    }

    /** ORDER BY ... DESC (shortcut) */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $limit): self
    {
        $q = $this->clone();
        $q->limitVal = $limit;
        return $q;
    }

    public function offset(int $offset): self
    {
        $q = $this->clone();
        $q->offsetVal = $offset;
        return $q;
    }

    /**
     * WHERE 절 + 바인딩 빌드
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildWhere(): array
    {
        if (empty($this->wheres)) {
            return ['', []];
        }
        $sql = ' WHERE ';
        $bindings = [];
        foreach ($this->wheres as $i => [$col, $op, $val, $logic]) {
            if ($i > 0) {
                $sql .= " {$logic} ";
            }
            if ($op === 'BETWEEN') {
                $sql .= "{$col} BETWEEN ? AND ?";
                $bindings[] = $val[0];
                $bindings[] = $val[1];
            } elseif ($op === 'IN' || $op === 'NOT IN') {
                $placeholders = implode(', ', array_fill(0, count((array) $val), '?'));
                $sql .= "{$col} {$op} ({$placeholders})";
                foreach ((array) $val as $v) {
                    $bindings[] = $v;
                }
            } elseif ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                $sql .= "{$col} {$op}";
            } else {
                $sql .= "{$col} {$op} ?";
                $bindings[] = $val;
            }
        }
        return [$sql, $bindings];
    }

    /** 단일 행 조회 */
    public function first(): ?array
    {
        [$whereSql, $bindings] = $this->buildWhere();
        $cols = !empty($this->selectColumns) ? implode(', ', $this->selectColumns) : '*';
        $sql = "SELECT {$cols} FROM {$this->table}{$whereSql}";
        if ($this->groupByCol !== '') {
            $sql .= " GROUP BY {$this->groupByCol}";
        }
        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        $sql .= ' LIMIT ?';
        $bindings[] = 1;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /** 전체 행 조회 */
    public function all(): array
    {
        [$whereSql, $bindings] = $this->buildWhere();
        $cols = !empty($this->selectColumns) ? implode(', ', $this->selectColumns) : '*';
        $sql = "SELECT {$cols} FROM {$this->table}{$whereSql}";
        if ($this->groupByCol !== '') {
            $sql .= " GROUP BY {$this->groupByCol}";
        }
        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        if ($this->limitVal !== null) {
            $sql .= ' LIMIT ?';
            $bindings[] = $this->limitVal;
        }
        if ($this->offsetVal !== null) {
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offsetVal;
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    /** 행 수 조회 */
    public function count(): int
    {
        [$whereSql, $bindings] = $this->buildWhere();

        // GROUP BY가 있으면 서브쿼리로 감싸서 정확한 행 수 반환
        if ($this->groupByCol !== '') {
            $inner = "SELECT 1 FROM {$this->table}{$whereSql} GROUP BY {$this->groupByCol}";
            $sql = "SELECT COUNT(*) FROM ({$inner}) AS _grouped";
        } else {
            $sql = "SELECT COUNT(*) FROM {$this->table}{$whereSql}";
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return (int) $stmt->fetchColumn();
    }

    /** 단일 컬럼 배열 추출 */
    public function pluck(string $column): array
    {
        self::validateIdentifier($column);
        [$whereSql, $bindings] = $this->buildWhere();
        $sql = "SELECT {$column} FROM {$this->table}{$whereSql}";
        if ($this->groupByCol !== '') {
            $sql .= " GROUP BY {$this->groupByCol}";
        }
        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        if ($this->limitVal !== null) {
            $sql .= ' LIMIT ?';
            $bindings[] = $this->limitVal;
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** 단일 값 추출 */
    public function value(string $column): mixed
    {
        self::validateIdentifier($column);
        [$whereSql, $bindings] = $this->buildWhere();
        $sql = "SELECT {$column} FROM {$this->table}{$whereSql} LIMIT 1";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    /** 존재 여부 확인 */
    public function exists(): bool
    {
        [$whereSql, $bindings] = $this->buildWhere();
        $sql = "SELECT EXISTS(SELECT 1 FROM {$this->table}{$whereSql})";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return (bool) $stmt->fetchColumn();
    }

    /** 미존재 여부 확인 */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /** 집계: SUM */
    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    /** 집계: AVG */
    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    /** 집계: MIN */
    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    /** 집계: MAX */
    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    /** 집계 함수 내부 구현 */
    private function aggregate(string $func, string $column): mixed
    {
        self::validateIdentifier($column);
        [$whereSql, $bindings] = $this->buildWhere();
        $sql = "SELECT {$func}({$column}) FROM {$this->table}{$whereSql}";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    /** 값 증가 */
    public function increment(string $column, int $amount = 1): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('WHERE 조건 없이 INCREMENT를 실행할 수 없습니다.');
        }
        self::validateIdentifier($column);
        [$whereSql, $bindings] = $this->buildWhere();
        $sql = "UPDATE {$this->table} SET {$column} = {$column} + ? {$whereSql}";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$amount], $bindings));
        return $stmt->rowCount();
    }

    /** 값 감소 */
    public function decrement(string $column, int $amount = 1): int
    {
        return $this->increment($column, -$amount);
    }

    /** 대용량 배치 처리 (메모리 효율적) */
    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        while (true) {
            $rows = $this->limit($size)->offset($offset)->all();
            if (empty($rows)) {
                break;
            }

            if ($callback($rows) === false) {
                break;
            }

            $offset += $size;
            if (count($rows) < $size) {
                break;
            }
        }
    }

    /** 삽입 (마지막 삽입 ID 반환) */
    public function insert(array $data): string|false
    {
        foreach (array_keys($data) as $col) {
            self::validateIdentifier($col);
        }
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->pdo()->lastInsertId();
    }

    /** 수정 (영향받은 행 수 반환) — WHERE 없이 실행 시 예외 */
    public function update(array $data): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('WHERE 조건 없이 UPDATE를 실행할 수 없습니다. 전체 수정이 의도라면 where(1, 1) 을 사용하세요.');
        }

        $sets = [];
        $bindings = [];
        foreach ($data as $col => $val) {
            self::validateIdentifier($col);
            $sets[] = "{$col} = ?";
            $bindings[] = $val;
        }
        [$whereSql, $whereBindings] = $this->buildWhere();
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . $whereSql;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge($bindings, $whereBindings));
        return $stmt->rowCount();
    }

    /** 삭제 (영향받은 행 수 반환) — WHERE 없이 실행 시 예외 */
    public function delete(): int
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('WHERE 조건 없이 DELETE를 실행할 수 없습니다. 전체 삭제가 의도라면 where(1, 1) 을 사용하세요.');
        }

        [$whereSql, $bindings] = $this->buildWhere();
        $sql = "DELETE FROM {$this->table}{$whereSql}";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /** Raw SQL 실행 */
    public function raw(string $sql, array $bindings = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    /** SQL 식별자(테이블/컬럼명) 검증 */
    private static function validateIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $name)) {
            throw new \InvalidArgumentException("유효하지 않은 SQL 식별자: {$name}");
        }
    }

    /** 트랜잭션 */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
