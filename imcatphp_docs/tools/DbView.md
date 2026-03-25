# DbView — DB 구조 조회/탐색기

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\DbView` |
| 파일 | `catphp/DbView.php` (430줄) |
| Shortcut | `dbview()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\DB` (`db()->raw()`) |
| DB 지원 | MySQL, PostgreSQL, SQLite |

---

## 설정

별도 config 없음. `config('db.driver')`를 읽어 드라이버를 자동 감지한다.

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `tables` | `tables(): array` | `list<string>` | 테이블 목록 |
| `columns` | `columns(string $table): array` | `list<array>` | 컬럼 정보 (name, type, nullable, default, key) |
| `describe` | `describe(string $table): array` | `array` | 상세 (컬럼 + 인덱스 + 행 수 + 크기) |
| `preview` | `preview(string $table, int $limit = 10): array` | `list<array>` | 데이터 미리보기 (최대 100행) |
| `indexes` | `indexes(string $table): array` | `list<array>` | 인덱스 정보 (name, columns, unique) |
| `rowCount` | `rowCount(string $table): int` | `int` | 테이블 행 수 |
| `size` | `size(string $table): string` | `string` | 테이블 크기 (사람 읽기 형식: KB/MB/GB) |
| `stats` | `stats(): array` | `array` | DB 전체 통계 (driver, database, tables, total_rows, total_size) |

---

## CLI 명령어

```bash
php cli.php db:tables            # 테이블 목록
php cli.php db:columns TABLE     # 컬럼 목록
php cli.php db:describe TABLE    # 테이블 상세
php cli.php db:preview TABLE     # 데이터 미리보기
php cli.php db:stats             # DB 전체 통계
```

---

## 사용 예제

### 테이블 목록

```php
$tables = dbview()->tables();
// ['migrations', 'posts', 'users']
```

### 컬럼 정보

```php
$columns = dbview()->columns('users');
// [
//   ['name' => 'id',    'type' => 'bigint unsigned', 'nullable' => false, 'default' => null,  'key' => 'PRI'],
//   ['name' => 'name',  'type' => 'varchar(100)',    'nullable' => false, 'default' => null,  'key' => ''],
//   ['name' => 'email', 'type' => 'varchar(255)',    'nullable' => false, 'default' => null,  'key' => 'UNI'],
// ]
```

### 테이블 상세

```php
$info = dbview()->describe('users');
// [
//   'table'     => 'users',
//   'columns'   => [...],
//   'indexes'   => [['name' => 'PRIMARY', 'columns' => 'id', 'unique' => true], ...],
//   'row_count' => 150,
//   'size'      => '24.5 KB',
// ]
```

### 데이터 미리보기

```php
$rows = dbview()->preview('users', 5);
// [['id' => 1, 'name' => '홍길동', ...], ...]
```

### DB 전체 통계

```php
$stats = dbview()->stats();
// [
//   'driver'     => 'mysql',
//   'database'   => 'catphp',
//   'tables'     => 12,
//   'total_rows' => 45230,
//   'total_size' => '8.3 MB',
// ]
```

---

## 내부 동작

### 드라이버별 메타데이터 쿼리

| 기능 | MySQL | PostgreSQL | SQLite |
| --- | --- | --- | --- |
| 테이블 목록 | `SHOW TABLES` | `pg_tables WHERE schemaname='public'` | `sqlite_master WHERE type='table'` |
| 컬럼 정보 | `SHOW FULL COLUMNS FROM` | `information_schema.columns` | `PRAGMA table_info()` |
| 인덱스 | `SHOW INDEX FROM` | `pg_indexes` | `PRAGMA index_list()` + `index_info()` |
| 테이블 크기 | `information_schema.tables` (data+index) | `pg_total_relation_size()` | `dbstat` 가상테이블 |
| PK 정보 | `Key` 컬럼 | `pg_index + pg_attribute` | `pk` 컬럼 |

### SQLite 크기 측정 폴백

SQLite의 `dbstat` 가상테이블은 `SQLITE_ENABLE_DBSTAT_VTAB` 컴파일 옵션이 필요하다. 미지원 시 `PDOException`을 catch하고 **행 수 × 100바이트**로 추정한다.

### describe() 최적화

`describe()`는 내부적으로 `columnsInternal()`, `indexesInternal()`, `rowCountInternal()`, `sizeInternal()` 을 호출한다. 이들은 `validateIdentifier()`를 생략하는 Internal 버전으로, `describe()`에서 이미 검증을 완료한 뒤 호출하여 중복 검증을 방지한다.

---

## 보안 고려사항

- **식별자 검증**: 모든 public 메서드에서 `validateIdentifier()` — `/^[a-zA-Z_][a-zA-Z0-9_.]*$/` 정규식
- **preview() 행 수 제한**: `max(1, min($limit, 100))` — 최대 100행으로 자동 클램핑

---

## 주의사항

1. **읽기 전용**: 이 도구는 DB 구조를 **조회만** 한다. 스키마 변경은 [Migration](Migration.md) 사용.

2. **성능 — stats()**: 모든 테이블을 순회하므로 테이블 수가 많으면 느릴 수 있다. 캐시 사용 권장.

3. **MySQL 권한**: `information_schema` 접근 권한이 필요하다. 제한된 DB 유저에서는 크기 정보가 `0 B`로 반환될 수 있다.

4. **PostgreSQL schema**: `public` 스키마만 조회한다. 커스텀 스키마는 `raw()` 쿼리로 직접 조회 필요.

5. **SQLite 시스템 테이블**: `sqlite_`로 시작하는 내부 테이블은 자동 제외된다.

---

## 연관 도구

- [DB](DB.md) — 쿼리 빌더 (내부적으로 `db()->raw()` 사용)
- [Migration](Migration.md) — 스키마 변경
- [Backup](../ops.md) — DB 백업/복원
