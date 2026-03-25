# Backup — DB 백업/복원

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Backup` |
| 파일 | `catphp/Backup.php` (380줄) |
| Shortcut | `backup()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\DB` (드라이버 감지), `Cat\Log` (보안 로깅) |
| 외부 의존 | `mysqldump`/`mysql` (MySQL), `pg_dump`/`psql` (PostgreSQL) |

---

## 설정

```php
// config/app.php
'backup' => [
    'path'      => __DIR__ . '/../storage/backup',  // 백업 저장 디렉토리
    'keep_days' => 30,                               // 자동 정리 보관 일수
    'compress'  => false,                            // gzip 압축 여부
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `database` | `database(?string $path = null): string` | `string` | DB 백업 → 파일 경로 |
| `restore` | `restore(string $path): bool` | `bool` | 백업 복원 |
| `list` | `list(): array` | `array` | 백업 파일 목록 (최신순) |
| `latest` | `latest(): ?string` | `?string` | 최신 백업 경로 |
| `clean` | `clean(int $days = 0): int` | `int` | N일 이전 삭제 → 삭제 수 |
| `getPath` | `getPath(): string` | `string` | 백업 디렉토리 경로 |

---

## 사용 예제

### 백업

```php
// 자동 경로 (storage/backup/20250101_120000_mysql.sql)
$path = backup()->database();

// 지정 경로
$path = backup()->database('storage/custom/my_backup.sql');
```

### 복원

```php
backup()->restore('storage/backup/20250101_120000_mysql.sql');

// 최신 백업으로 복원
$latest = backup()->latest();
if ($latest !== null) {
    backup()->restore($latest);
}
```

### 백업 목록

```php
$list = backup()->list();
// [['name' => '20250101_120000_mysql.sql', 'path' => '...', 'size' => 1234, 'date' => '2025-01-01 12:00:00'], ...]
```

### 오래된 백업 정리

```php
$deleted = backup()->clean();      // config keep_days 기준
$deleted = backup()->clean(7);     // 7일 이전 삭제
```

### CLI 명령어

```bash
php cli.php db:backup
php cli.php db:restore
php cli.php db:backup:list
php cli.php db:backup:clean
```

---

## 내부 동작

### 드라이버별 백업

| 드라이버 | 방식 | 압축 |
| --- | --- | --- |
| MySQL | `mysqldump --single-transaction --routines --triggers` | gzip 파이프 |
| PostgreSQL | `pg_dump --format=plain --no-owner` | gzip 파이프 |
| SQLite | `copy()` 파일 복사 + WAL/SHM 보조 파일 | 미지원 |

### MySQL 백업 흐름

```text
backupMysql($path)
├─ escapeshellarg()로 호스트/유저/DB명 이스케이프
├─ mysqldump 명령 조립
├─ compress=true → '| gzip' 파이프
├─ exec($cmd . ' 2>&1') → $output, $code
├─ clearstatcache(true, $path) — filesize 캐시 방지
└─ 파일 크기 0 → RuntimeException
```

### SQLite 백업 흐름

```text
backupSqlite($path)
├─ PRAGMA wal_checkpoint(TRUNCATE) — WAL 플러시
├─ copy($dbPath, $path)
└─ -wal, -shm 보조 파일도 복사 (존재 시)
```

### SQLite 복원 안전장치

복원 전 현재 DB를 `.before_restore.{timestamp}` 이름으로 자동 백업.

---

## 보안 고려사항

- **경로 트래버설 방어**: `restore()`에서 `realpath()` + `backupPath` 내부 제한. 외부 경로 접근 시 `RuntimeException` + 경고 로그.
- **셸 인젝션 방어**: 모든 인자에 `escapeshellarg()` 적용.
- **PostgreSQL 비밀번호**: `PGPASSWORD` 환경변수로 전달 (프로세스 환경만 노출).

---

## 주의사항

1. **외부 명령 의존**: MySQL/PostgreSQL은 `mysqldump`/`pg_dump`가 시스템 PATH에 있어야 함.
2. **Windows PATH**: XAMPP(`C:\xampp\mysql\bin`), PostgreSQL(`C:\Program Files\PostgreSQL\16\bin`) 등 PATH 등록 필요.
3. **gzip 압축**: `compress=true` 시 `.sql.gz` 파일 생성. 복원 시 자동 `gunzip` 처리.
4. **빈 파일 검증**: MySQL/PostgreSQL 백업 후 파일 크기 0이면 `RuntimeException`.
5. **filemtime false 방어**: `clean()`에서 `filemtime()` 반환값 `false` 체크.

---

## 연관 도구

- [DB](DB.md) — 드라이버 설정
- [Schedule](Schedule.md) — 정기 백업 스케줄링
- [Hash](Hash.md) — 백업 파일 무결성 검증
- [Storage](Storage.md) — 백업 파일 원격 저장
