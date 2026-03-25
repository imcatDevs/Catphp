# 데이터베이스 — DB · Migration · DbView

CatPHP의 데이터베이스 계층. 쿼리 빌더, 스키마 마이그레이션, 메타데이터 탐색기를 제공한다.

| 도구 | Shortcut | 클래스 | 줄 수 |
| --- | --- | --- | --- |
| DB | `db()` | `Cat\DB` | 552 |
| Migration | `migration()` | `Cat\Migration` | 419 |
| DbView | `dbview()` | `Cat\DbView` | 430 |

---

## 목차

1. [DB — 쿼리 빌더](#1-db--쿼리-빌더)
2. [Migration — 스키마 버전 관리](#2-migration--스키마-버전-관리)
3. [DbView — DB 구조 탐색기](#3-dbview--db-구조-탐색기)

---

## 1. DB — 쿼리 빌더

MySQL / PostgreSQL / SQLite 호환 쿼리 빌더. PDO 기반, prepared statement 전용.

### 설정 (config/app.php)

```php
'db' => [
    'driver'  => 'mysql',       // mysql | pgsql | sqlite
    'host'    => '127.0.0.1',
    'port'    => 3306,
    'dbname'  => 'mydb',
    'user'    => 'root',
    'pass'    => 'secret',
    'charset' => 'utf8mb4',
    // SQLite 전용
    // 'path'           => '/path/to/db.sqlite',
    // 'journal_mode'   => 'WAL',
    // 'foreign_keys'   => true,
    // 'busy_timeout'   => 5000,
    // 'synchronous'    => 'NORMAL',
],
```

### 연결 방식

**이중 지연 로딩**: `db()` 호출 시 인스턴스만 생성되고, 실제 PDO 연결은 첫 쿼리 실행 시점에 수립된다.

```text
db()           → Cat\DB 싱글턴 반환 (PDO 연결 없음)
db()->table()  → 쿼리 빌더 체인 시작 (PDO 연결 없음)
db()->...->first()  → PDO 연결 수립 + SQL 실행
```

PDO 옵션:

| 옵션 | 값 | 효과 |
| --- | --- | --- |
| `ERRMODE` | `EXCEPTION` | 에러 시 예외 throw |
| `FETCH_MODE` | `FETCH_ASSOC` | 결과를 연관 배열로 반환 |
| `EMULATE_PREPARES` | `false` | 네이티브 prepared statement 사용 |

### 이뮤터블 체이닝

모든 빌더 메서드는 `clone`을 반환한다. 원본 인스턴스를 오염시키지 않으므로 같은 기본 쿼리에서 분기 가능:

```php
$base = db()->table('users')->where('active', 1);

$admins = $base->where('role', 'admin')->all();  // active=1 AND role=admin
$users  = $base->limit(10)->all();                // active=1 LIMIT 10
// $base는 변경되지 않음
```

### DB 메서드 레퍼런스

#### 테이블 선택

```php
db()->table(string $table): self
```

새 쿼리 빌더 시작. 이전 조건 모두 초기화.

#### SELECT 컬럼

```php
db()->table('users')->select('id', 'name', 'email')->all();
// SELECT id, name, email FROM users
```

#### WHERE 조건

```php
// 기본 (AND)
->where('status', 'active')               // status = 'active'
->where('age', 18, '>=')                  // age >= 18
->where('name', '%김%', 'LIKE')           // name LIKE '%김%'

// OR
->orWhere('role', 'admin')                // OR role = 'admin'

// NULL
->whereNull('deleted_at')                 // deleted_at IS NULL
->whereNotNull('email')                   // email IS NOT NULL

// IN
->whereIn('status', ['active', 'pending'])     // status IN ('active', 'pending')
->whereNotIn('role', ['banned'])               // role NOT IN ('banned')

// BETWEEN
->whereBetween('age', [18, 65])           // age BETWEEN 18 AND 65
```

허용 연산자: `=`, `<`, `>`, `<=`, `>=`, `!=`, `<>`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS`, `IS NOT`

#### 정렬 · 그룹 · 제한

```php
->orderBy('created_at', 'DESC')  // ORDER BY created_at DESC
->orderByDesc('id')              // ORDER BY id DESC (shortcut)
->groupBy('category')            // GROUP BY category
->limit(10)                      // LIMIT 10
->offset(20)                     // OFFSET 20
```

#### 조회 메서드

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `first()` | `?array` | 단일 행 (자동 LIMIT 1) |
| `all()` | `array` | 전체 행 |
| `count()` | `int` | 행 수 |
| `pluck('col')` | `array` | 단일 컬럼 배열 |
| `value('col')` | `mixed` | 단일 값 |
| `exists()` | `bool` | 존재 여부 |
| `doesntExist()` | `bool` | 미존재 여부 |

```php
// 단일 행
$user = db()->table('users')->where('id', 1)->first();
// ['id' => 1, 'name' => '홍길동', ...]

// 전체
$users = db()->table('users')->where('active', 1)->orderByDesc('id')->all();

// 컬럼 배열
$emails = db()->table('users')->pluck('email');
// ['a@b.com', 'c@d.com', ...]

// 단일 값
$name = db()->table('users')->where('id', 1)->value('name');
// '홍길동'
```

#### 집계 메서드

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `sum('col')` | `float` | 합계 |
| `avg('col')` | `float` | 평균 |
| `min('col')` | `mixed` | 최솟값 |
| `max('col')` | `mixed` | 최댓값 |

```php
$total = db()->table('orders')->where('status', 'paid')->sum('amount');
$avg   = db()->table('products')->avg('price');
```

#### CUD (Create · Update · Delete)

```php
// INSERT — 마지막 삽입 ID 반환
$id = db()->table('users')->insert([
    'name'  => '홍길동',
    'email' => 'hong@example.com',
]);

// UPDATE — 영향받은 행 수 반환 (WHERE 필수)
$affected = db()->table('users')
    ->where('id', 1)
    ->update(['name' => '김철수']);

// DELETE — 영향받은 행 수 반환 (WHERE 필수)
$deleted = db()->table('users')
    ->where('active', 0)
    ->delete();
```

> **안전장치**: `update()`, `delete()`는 WHERE 조건 없이 호출하면 `RuntimeException`을 던진다. 전체 수정/삭제가 의도라면 `->where(1, 1)`을 명시해야 한다.

#### 증감

```php
// 조회수 1 증가 (WHERE 필수)
db()->table('posts')->where('id', 5)->increment('views');

// 재고 3 감소
db()->table('products')->where('id', 10)->decrement('stock', 3);
```

#### 대용량 배치 처리

```php
db()->table('users')->where('active', 1)->chunk(100, function (array $rows) {
    foreach ($rows as $user) {
        // 처리
    }
    // return false; → 중단
});
```

메모리 효율적. 100건씩 가져와서 처리하고, 콜백이 `false`를 반환하면 중단.

#### 트랜잭션

```php
db()->transaction(function ($db) {
    $db->table('accounts')->where('id', 1)->decrement('balance', 1000);
    $db->table('accounts')->where('id', 2)->increment('balance', 1000);
    $db->table('transfers')->insert([
        'from' => 1, 'to' => 2, 'amount' => 1000,
    ]);
    // 예외 발생 시 자동 rollback
});
```

#### Raw SQL

```php
$stmt = db()->raw('SELECT * FROM users WHERE id = ?', [1]);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// DDL
db()->raw('CREATE INDEX idx_email ON users(email)');
```

### DB 보안

- **SQL Injection 차단**: 모든 값은 prepared statement의 바인딩 파라미터(`?`)로 전달
- **식별자 검증**: `validateIdentifier()`로 테이블명/컬럼명에 정규식 검사 (`/^[a-zA-Z_][a-zA-Z0-9_.]*$/`)
- **연산자 화이트리스트**: 허용된 연산자만 사용 가능

---

## 2. Migration — 스키마 버전 관리

파일 기반 DB 스키마 버전 관리. 배치(batch) 단위로 실행/롤백.

### 설정 (config/app.php)

```php
'migration' => [
    'path'  => dirname(__DIR__) . '/migrations',  // 마이그레이션 파일 경로
    'table' => 'migrations',                       // 상태 추적 테이블명
],
```

### Migration CLI 명령어

```bash
php cli.php migrate              # 미실행 마이그레이션 실행
php cli.php migrate:rollback     # 마지막 배치 롤백
php cli.php migrate:status       # 상태 확인
php cli.php migrate:create users # 마이그레이션 파일 생성
php cli.php migrate:fresh        # 전체 롤백 후 재실행
```

### 마이그레이션 파일 형식

`migrations/20260322_120000_create_users.php`:

```php
<?php declare(strict_types=1);

return [
    'up'   => "CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'down' => "DROP TABLE IF EXISTS users",
];
```

여러 SQL이 필요한 경우 배열로 작성:

```php
return [
    'up'   => [
        "CREATE TABLE posts (...)",
        "CREATE INDEX idx_posts_user ON posts(user_id)",
    ],
    'down' => [
        "DROP INDEX idx_posts_user ON posts",
        "DROP TABLE IF EXISTS posts",
    ],
];
```

### Migration 메서드 레퍼런스

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `run()` | `list<string>` | 미실행 마이그레이션 실행, 실행된 파일명 반환 |
| `rollback(int $steps = 1)` | `list<string>` | 마지막 N배치 롤백, 롤백된 이름 반환 |
| `fresh()` | `list<string>` | 전체 롤백 후 재실행 |
| `status()` | `list<array>` | 전체 마이그레이션 상태 (Ran/Pending) |
| `create(string $name, string $table, string $type)` | `string` | 파일 생성, 경로 반환 |
| `ensureTable()` | `self` | 마이그레이션 추적 테이블 생성 |
| `getPath()` | `string` | 마이그레이션 경로 |

### PHP 코드 사용

```php
// 미실행 마이그레이션 실행
$executed = migration()->run();
// ['20260322_120000_create_users', '20260322_130000_create_posts']

// 마지막 2배치 롤백
$rolled = migration()->rollback(2);

// 상태 확인
$statuses = migration()->status();
// [
//     ['name' => '20260322_120000_create_users', 'batch' => 1, 'status' => 'Ran'],
//     ['name' => '20260322_130000_create_posts', 'batch' => null, 'status' => 'Pending'],
// ]

// 파일 생성
$path = migration()->create('create_comments', 'comments', 'create');

// 전체 초기화 후 재실행 (주의: 데이터 삭제됨)
migration()->fresh();
```

### 배치(Batch) 시스템

```text
run() 1회차 → batch 1: create_users, create_posts
run() 2회차 → batch 2: create_comments

rollback()   → batch 2 롤백 (create_comments만 삭제)
rollback(2)  → batch 2 + batch 1 롤백
```

- 같은 `run()` 호출에서 실행된 마이그레이션은 같은 배치 번호를 가짐
- `rollback()`은 가장 최근 배치부터 역순으로 롤백
- 모든 실행/롤백은 트랜잭션(`BEGIN`/`COMMIT`/`ROLLBACK`)으로 보호

### 파일 명명 규칙

```text
{YYYYMMDD}_{HHmmss}_{slug}.php
```

타임스탬프 기반 정렬로 실행 순서가 보장된다.

---

## 3. DbView — DB 구조 탐색기

MySQL / PostgreSQL / SQLite 호환 DB 메타데이터 조회 도구.

### DbView 메서드 레퍼런스

| 메서드 | 반환 타입 | 설명 |
| --- | --- | --- |
| `tables()` | `list<string>` | 테이블 목록 |
| `columns(string $table)` | `list<array>` | 컬럼 정보 |
| `describe(string $table)` | `array` | 상세 (컬럼+인덱스+행수+크기) |
| `preview(string $table, int $limit)` | `list<array>` | 데이터 미리보기 (최대 100행) |
| `indexes(string $table)` | `list<array>` | 인덱스 정보 |
| `rowCount(string $table)` | `int` | 행 수 |
| `size(string $table)` | `string` | 테이블 크기 (사람이 읽기 쉬운 형식) |
| `stats()` | `array` | DB 전체 통계 |

### 사용 예시

```php
// 테이블 목록
$tables = dbview()->tables();
// ['users', 'posts', 'comments']

// 컬럼 정보
$cols = dbview()->columns('users');
// [
//     ['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false, 'default' => null, 'key' => 'PRI'],
//     ['name' => 'email', 'type' => 'varchar(255)', 'nullable' => false, 'default' => null, 'key' => 'UNI'],
// ]

// 테이블 상세
$info = dbview()->describe('users');
// [
//     'table'     => 'users',
//     'columns'   => [...],
//     'indexes'   => [['name' => 'PRIMARY', 'columns' => 'id', 'unique' => true]],
//     'row_count' => 1500,
//     'size'      => '2.3 MB',
// ]

// 데이터 미리보기
$rows = dbview()->preview('users', 5);

// 인덱스 정보
$indexes = dbview()->indexes('posts');
// [['name' => 'idx_user', 'columns' => 'user_id', 'unique' => false]]

// DB 전체 통계
$stats = dbview()->stats();
// [
//     'driver'     => 'mysql',
//     'database'   => 'mydb',
//     'tables'     => 12,
//     'total_rows' => 50000,
//     'total_size' => '45.2 MB',
// ]
```

### DbView CLI 명령어

```bash
php cli.php db:tables              # 테이블 목록
php cli.php db:columns users       # 컬럼 정보
php cli.php db:describe users      # 상세 정보
php cli.php db:preview users       # 데이터 미리보기
php cli.php db:stats               # 전체 통계
```

### 드라이버별 내부 쿼리

| 작업 | MySQL | PostgreSQL | SQLite |
| --- | --- | --- | --- |
| 테이블 목록 | `SHOW TABLES` | `pg_tables` | `sqlite_master` |
| 컬럼 정보 | `SHOW FULL COLUMNS` | `information_schema.columns` | `PRAGMA table_info` |
| 인덱스 | `SHOW INDEX` | `pg_indexes` | `PRAGMA index_list` |
| 크기 | `information_schema.tables` | `pg_total_relation_size()` | `dbstat` (폴백: 추정) |

### DbView 보안

- `validateIdentifier()`로 테이블명 SQL Injection 차단
- `preview()` limit 값은 1~100으로 클램핑

---

## 도구 간 연동

```text
DB ← Migration (run/rollback에서 db()->raw() 사용)
DB ← DbView (모든 메타 쿼리에서 db()->raw() 사용)
DB ← Queue, Search, Tag, User, Paginate 등 (데이터 접근)
```
