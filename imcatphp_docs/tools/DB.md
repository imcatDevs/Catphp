# DB — 쿼리 빌더

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\DB` |
| 파일 | `catphp/DB.php` (552줄) |
| Shortcut | `db()` |
| 네임스페이스 | `Cat` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 확장 | `ext-pdo` |
| DB 지원 | MySQL, PostgreSQL, SQLite |

---

## 설정

```php
// config/app.php
'db' => [
    'driver'  => 'mysql',           // mysql | pgsql | sqlite
    'host'    => '127.0.0.1',
    'port'    => 3306,
    'dbname'  => 'my_database',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
    // SQLite 전용
    'path'           => 'storage/app.db',   // dbname 대신 사용 가능
    'journal_mode'   => 'WAL',              // 선택
    'foreign_keys'   => true,               // 선택
    'busy_timeout'   => 5000,               // 선택 (ms)
    'synchronous'    => 'NORMAL',           // 선택
],
```

---

## 메서드 레퍼런스

### 테이블 선택

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `table` | `table(string $table): self` | `self` | 대상 테이블 지정. 쿼리 상태 초기화. |
| `select` | `select(string ...$columns): self` | `self` | SELECT 컬럼 지정 (기본 `*`) |

### WHERE 조건

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `where` | `where(string $column, mixed $value, string $operator = '='): self` | `self` | AND 조건 추가 |
| `orWhere` | `orWhere(string $column, mixed $value, string $operator = '='): self` | `self` | OR 조건 추가 |
| `whereNull` | `whereNull(string $column): self` | `self` | IS NULL 조건 |
| `whereNotNull` | `whereNotNull(string $column): self` | `self` | IS NOT NULL 조건 |
| `whereIn` | `whereIn(string $column, array $values): self` | `self` | IN 조건 |
| `whereNotIn` | `whereNotIn(string $column, array $values): self` | `self` | NOT IN 조건 |
| `whereBetween` | `whereBetween(string $column, array $range): self` | `self` | BETWEEN 조건 (`[min, max]`) |

### 정렬·제한

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `orderBy` | `orderBy(string $column, string $direction = 'ASC'): self` | `self` | 정렬 |
| `orderByDesc` | `orderByDesc(string $column): self` | `self` | 내림차순 정렬 (shortcut) |
| `groupBy` | `groupBy(string $column): self` | `self` | 그룹핑 |
| `limit` | `limit(int $limit): self` | `self` | 행 수 제한 |
| `offset` | `offset(int $offset): self` | `self` | 오프셋 |

### 조회

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `first` | `first(): ?array` | `?array` | 단일 행 (없으면 `null`) |
| `all` | `all(): array` | `array` | 전체 행 |
| `count` | `count(): int` | `int` | 행 수 |
| `pluck` | `pluck(string $column): array` | `array` | 단일 컬럼 배열 |
| `value` | `value(string $column): mixed` | `mixed` | 단일 값 |
| `exists` | `exists(): bool` | `bool` | 존재 여부 |
| `doesntExist` | `doesntExist(): bool` | `bool` | 미존재 여부 |

### 집계

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `sum` | `sum(string $column): float` | `float` | 합계 |
| `avg` | `avg(string $column): float` | `float` | 평균 |
| `min` | `min(string $column): mixed` | `mixed` | 최솟값 |
| `max` | `max(string $column): mixed` | `mixed` | 최댓값 |

### 쓰기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `insert` | `insert(array $data): string\|false` | `string\|false` | 삽입 → 마지막 삽입 ID |
| `update` | `update(array $data): int` | `int` | 수정 → 영향 행 수 |
| `delete` | `delete(): int` | `int` | 삭제 → 영향 행 수 |
| `increment` | `increment(string $column, int $amount = 1): int` | `int` | 값 증가 |
| `decrement` | `decrement(string $column, int $amount = 1): int` | `int` | 값 감소 |

### 고급

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `raw` | `raw(string $sql, array $bindings = []): \PDOStatement` | `PDOStatement` | Raw SQL 실행 |
| `transaction` | `transaction(callable $callback): mixed` | `mixed` | 트랜잭션 (자동 commit/rollback) |
| `chunk` | `chunk(int $size, callable $callback): void` | `void` | 대용량 배치 처리 |

---

## 사용 예제

### 기본 CRUD

```php
// 삽입
$id = db()->table('posts')->insert([
    'title' => '첫 글',
    'body'  => '내용',
]);

// 조회
$post = db()->table('posts')->where('id', $id)->first();
$all  = db()->table('posts')->orderByDesc('created_at')->all();

// 수정
db()->table('posts')->where('id', $id)->update(['title' => '수정됨']);

// 삭제
db()->table('posts')->where('id', $id)->delete();
```

### 복합 조건

```php
$users = db()->table('users')
    ->select('id', 'name', 'email')
    ->where('status', 'active')
    ->where('age', 18, '>=')
    ->whereNotNull('email')
    ->orderBy('name')
    ->limit(20)
    ->all();
```

### WHERE IN / BETWEEN

```php
$posts = db()->table('posts')
    ->whereIn('category_id', [1, 3, 5])
    ->all();

$orders = db()->table('orders')
    ->whereBetween('total', [1000, 5000])
    ->all();
```

### 집계 함수 사용

```php
$total    = db()->table('orders')->where('status', 'paid')->sum('total');
$avgPrice = db()->table('products')->avg('price');
$maxId    = db()->table('users')->max('id');
```

### 트랜잭션

```php
db()->transaction(function ($db) {
    $db->table('accounts')->where('id', 1)->decrement('balance', 100);
    $db->table('accounts')->where('id', 2)->increment('balance', 100);
    $db->table('transfers')->insert([
        'from_id' => 1, 'to_id' => 2, 'amount' => 100,
    ]);
});
// 예외 발생 시 자동 rollback
```

### 대용량 배치 (chunk)

```php
db()->table('logs')->orderBy('id')->chunk(1000, function (array $rows) {
    foreach ($rows as $row) {
        // 처리...
    }
    // return false; → 중단
});
```

### Raw SQL

```php
$stmt = db()->raw('SELECT * FROM users WHERE email = ?', ['a@b.com']);
$rows = $stmt->fetchAll();

db()->raw('TRUNCATE TABLE logs');
```

---

## 내부 동작

### 이중 지연 로딩

```text
db()          → Cat\DB 싱글턴 반환 (PDO 미연결)
  →table()    → clone + 테이블명 설정 (PDO 미연결)
  →where()    → clone + 조건 추가 (PDO 미연결)
  →first()    → PDO 최초 연결 (이 시점에서만 DB 접속)
```

- `db()` 호출 시점에는 DB 연결이 생성되지 않음
- 실제 쿼리 실행(`first()`, `all()`, `insert()` 등) 시점에 PDO 지연 연결

### 이뮤터블 체이닝

모든 빌더 메서드(`table`, `where`, `orderBy` 등)는 **`clone`** 을 사용하여 새 인스턴스를 반환한다. 싱글턴 인스턴스의 상태가 오염되지 않는다.

```php
$base = db()->table('users')->where('active', true);
$admins = $base->where('role', 'admin')->all();   // active + admin
$editors = $base->where('role', 'editor')->all();  // active + editor (admin 아님!)
```

### PDO 설정

| 옵션 | 값 | 이유 |
| --- | --- | --- |
| `ERRMODE` | `EXCEPTION` | 에러를 예외로 전환 |
| `DEFAULT_FETCH_MODE` | `FETCH_ASSOC` | 연관 배열 반환 |
| `EMULATE_PREPARES` | `false` | 진짜 prepared statement 사용 (보안) |

### SQLite PRAGMA

config에 해당 키가 있으면 PDO 연결 후 자동 적용:

| config 키 | PRAGMA | 권장값 |
| --- | --- | --- |
| `journal_mode` | `PRAGMA journal_mode = WAL` | `WAL` (동시 읽기 성능) |
| `foreign_keys` | `PRAGMA foreign_keys = ON` | `true` |
| `busy_timeout` | `PRAGMA busy_timeout = 5000` | `5000` (ms) |
| `synchronous` | `PRAGMA synchronous = NORMAL` | `NORMAL` |

---

## 보안 고려사항

### SQL Injection 방어

- **모든 값은 PDO prepared statement** (`?` 바인딩)로 처리
- `EMULATE_PREPARES = false` → 드라이버 레벨 진짜 바인딩

### 식별자 검증 (validateIdentifier)

테이블명·컬럼명에 정규식 검증 적용:

```php
preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $name)
```

- 영문·숫자·언더스코어·점(`.`)만 허용
- SQL injection 시도 차단 (예: `'; DROP TABLE --`)

### 연산자 화이트리스트

`where()` 연산자를 화이트리스트로 제한:

```php
['=', '<', '>', '<=', '>=', '!=', '<>', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT']
```

### 안전 장치

- **WHERE 없는 UPDATE**: `RuntimeException` 발생
- **WHERE 없는 DELETE**: `RuntimeException` 발생
- **WHERE 없는 INCREMENT**: `RuntimeException` 발생

> 전체 수정/삭제 의도 시 `where(1, 1)` 명시.

---

## 주의사항

1. **`table()` 호출 필수**: 쿼리 빌더 메서드를 사용하기 전에 반드시 `table()`을 호출해야 한다. 호출하지 않으면 빈 테이블명으로 SQL 에러 발생.

2. **`insert()` 반환값**: `string|false` — SQLite의 `lastInsertId()`는 항상 문자열 반환. `(int)` 캐스팅 필요.

3. **`count()` + `groupBy()`**: `GROUP BY`가 있으면 `COUNT(*)`가 그룹별 카운트를 반환하므로 `fetchColumn()`은 첫 그룹의 카운트만 반환한다.

4. **다중 `orderBy()`**: 마지막 호출만 적용된다. 복수 컬럼 정렬이 필요하면 `raw()` 사용.

5. **`chunk()` 주의**: `orderBy()`가 없으면 DB가 일관된 순서를 보장하지 않아 행이 누락되거나 중복될 수 있다. 반드시 안정적인 정렬 기준(예: `id`)을 지정.

6. **SQLite 동시 쓰기**: SQLite는 동시 쓰기를 지원하지 않는다. `busy_timeout` 설정 권장.

---

## 연관 도구

- [Migration](Migration.md) — 스키마 마이그레이션 (`db()->raw()` 사용)
- [DbView](DbView.md) — DB 구조 조회 (`db()->raw()` 사용)
- [Paginate](../data.md) — 페이지네이션 (`db()` 쿼리 결과)
- [Search](../web.md) — 전문 검색 (`db()` 기반)
- [Queue](../infra.md) — DB 드라이버 큐 (`db()` 기반)
