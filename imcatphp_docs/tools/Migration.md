# Migration — DB 스키마 버전 관리

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Migration` |
| 파일 | `catphp/Migration.php` (419줄) |
| Shortcut | `migration()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\DB` (`db()->raw()`, `db()->table()`) |
| DB 지원 | MySQL, PostgreSQL, SQLite |

---

## 설정

```php
// config/app.php
'migration' => [
    'path'  => __DIR__ . '/../migrations',   // 마이그레이션 파일 디렉토리
    'table' => 'migrations',                   // 마이그레이션 기록 테이블명
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `ensureTable` | `ensureTable(): self` | `self` | 마이그레이션 기록 테이블 생성 (없으면) |
| `run` | `run(): array` | `list<string>` | 미실행 마이그레이션 실행. 실행된 파일명 배열 반환 |
| `rollback` | `rollback(int $steps = 1): array` | `list<string>` | 마지막 N개 배치 롤백. 롤백된 이름 배열 반환 |
| `fresh` | `fresh(): array` | `list<string>` | 전체 롤백 후 재실행 |
| `status` | `status(): array` | `list<array>` | 마이그레이션 상태 목록 (`name`, `batch`, `status`) |
| `create` | `create(string $name, string $table = '', string $type = 'create'): string` | `string` | 마이그레이션 파일 생성. 파일 경로 반환 |
| `getPath` | `getPath(): string` | `string` | 마이그레이션 디렉토리 경로 |

---

## CLI 명령어

```bash
php cli.php migrate              # 미실행 마이그레이션 실행
php cli.php migrate:rollback     # 마지막 배치 롤백
php cli.php migrate:status       # 상태 확인
php cli.php migrate:create NAME  # 마이그레이션 파일 생성
php cli.php migrate:fresh        # 전체 롤백 후 재실행
```

---

## 마이그레이션 파일 형식

### 배열 반환 방식 (기본)

```php
// migrations/20260322_120000_create_users.php
<?php declare(strict_types=1);

return [
    'up'   => "CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'down' => "DROP TABLE IF EXISTS users",
];
```

### 복수 SQL 배열

```php
return [
    'up' => [
        "CREATE TABLE posts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL
        )",
        "CREATE INDEX idx_posts_title ON posts(title)",
    ],
    'down' => [
        "DROP INDEX idx_posts_title ON posts",
        "DROP TABLE IF EXISTS posts",
    ],
];
```

### 파일명 규칙

```text
{YYYYMMDD_HHmmss}_{설명}.php
예: 20260322_120000_create_users.php
```

- 파일명 순서 = 실행 순서 (정렬 기반)
- `create()` 메서드가 자동으로 타임스탬프 접두사 생성

---

## 사용 예제

### 코드에서 직접 실행

```php
// 미실행 마이그레이션 실행
$executed = migration()->run();
// ['20260322_120000_create_users', '20260322_120001_create_posts']

// 롤백
$rolled = migration()->rollback();        // 마지막 1배치
$rolled = migration()->rollback(3);       // 마지막 3배치

// 전체 리셋
$executed = migration()->fresh();

// 상태 확인
$list = migration()->status();
// [
//   ['name' => '20260322_120000_create_users', 'batch' => 1, 'status' => 'Ran'],
//   ['name' => '20260322_120001_create_posts', 'batch' => null, 'status' => 'Pending'],
// ]
```

### 마이그레이션 파일 생성

```php
// CREATE TABLE 템플릿
$path = migration()->create('create_users');
// → migrations/20260325_120000_create_users.php

// ALTER TABLE 템플릿
$path = migration()->create('add_email_to_users', 'users', 'alter');
// → migrations/20260325_120001_add_email_to_users.php
```

---

## 내부 동작

### 배치 시스템

```text
run() 실행 흐름:
1. ensureTable() — 마이그레이션 테이블 없으면 생성
2. getRan() — 이미 실행된 마이그레이션 목록 조회
3. getPendingFiles() — 파일 목록에서 실행된 것 제외
4. getNextBatch() — 다음 배치 번호 계산
5. BEGIN 트랜잭션
6. 각 파일의 'up' SQL 실행
7. recordMigration() — 실행 기록 INSERT
8. COMMIT
```

- **배치(batch)**: 한 번의 `run()` 호출에서 실행된 마이그레이션 그룹
- **롤백 단위**: 배치 단위로 역순 롤백 (`down` SQL 실행 후 기록 DELETE)

### 트랜잭션 보호

- `run()`, `rollback()`, `fresh()` 모두 `BEGIN`/`COMMIT`으로 감싸져 있음
- 중간에 SQL 에러 발생 시 `ROLLBACK`으로 원자적 복원

### 자동 생성 테이블 (ensureTable)

| 드라이버 | id 타입 | executed_at 타입 |
| --- | --- | --- |
| SQLite | `INTEGER PRIMARY KEY AUTOINCREMENT` | `TEXT DEFAULT datetime('now')` |
| MySQL/PostgreSQL | `INT AUTO_INCREMENT PRIMARY KEY` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` |

### CREATE TABLE 템플릿 (create)

| 드라이버 | id 타입 | 엔진 |
| --- | --- | --- |
| SQLite | `INTEGER PRIMARY KEY AUTOINCREMENT` | — |
| MySQL | `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | `InnoDB, utf8mb4_unicode_ci` |

---

## 보안 고려사항

- **식별자 검증**: `validateIdentifier()` — 테이블명·마이그레이션명에 정규식 검증 (`/^[a-zA-Z_][a-zA-Z0-9_]*$/`)
- **파일 로드**: `require`로 PHP 파일 실행 — 마이그레이션 디렉토리에 신뢰할 수 없는 파일이 없어야 함

---

## 주의사항

1. **트랜잭션 제한**: MySQL에서 `CREATE TABLE`, `ALTER TABLE` 같은 DDL 문은 암묵적으로 커밋되어 트랜잭션 롤백이 불가능하다. DDL 실패 시 수동 복구 필요.

2. **SQLite DDL 제한**: SQLite는 `ALTER TABLE DROP COLUMN`을 지원하지 않는다 (SQLite 3.35.0+ 에서만 지원). 이전 버전에서는 테이블 재생성 필요.

3. **롤백 파일 의존**: `rollback()`은 마이그레이션 파일의 `down` 키에 의존한다. 파일이 삭제되면 롤백 불가.

4. **fresh()는 위험**: 모든 테이블을 삭제하고 재생성한다. **운영 환경에서 절대 사용 금지**.

5. **파일 순서**: 파일명 정렬 순서가 실행 순서이므로, 타임스탬프 접두사를 유지해야 올바른 순서가 보장된다.

6. **복수 DB**: 싱글턴 기반이므로 현재 `config('db')` 설정의 DB에만 적용된다.

---

## 연관 도구

- [DB](DB.md) — 쿼리 빌더 (`db()->raw()` 사용)
- [DbView](DbView.md) — 마이그레이션 결과 구조 확인
- [Cli](Cli.md) — CLI 명령어 등록
