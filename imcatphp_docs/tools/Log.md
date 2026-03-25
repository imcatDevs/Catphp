# Log — 로거

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Log` |
| 파일 | `catphp/Log.php` (189줄) |
| Shortcut | `logger()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | 없음 |
| 추가 정의 | `Cat\LogLevel` enum (DEBUG/INFO/WARN/ERROR) |

---

## 설정

```php
// config/app.php
'log' => [
    'path'  => __DIR__ . '/../storage/logs',   // 로그 디렉토리
    'level' => 'debug',                          // 최소 로그 레벨: debug|info|warn|error
],
```

---

## 메서드 레퍼런스

### 로그 기록

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `debug` | `debug(string $message, array $context = []): void` | `void` | DEBUG 레벨 |
| `info` | `info(string $message, array $context = []): void` | `void` | INFO 레벨 |
| `warn` | `warn(string $message, array $context = []): void` | `void` | WARN 레벨 |
| `error` | `error(string $message, array $context = []): void` | `void` | ERROR 레벨 |

### 로그 관리

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `tail` | `tail(int $lines = 20): string` | `string` | 오늘 로그 마지막 N줄 읽기 |
| `clean` | `clean(int $days = 30): int` | `int` | N일 이전 로그 삭제 → 삭제된 파일 수 |
| `clear` | `clear(): bool` | `bool` | 오늘 로그 파일 삭제 |

---

## CLI 명령어

```bash
php cli.php log:tail         # 마지막 20줄
php cli.php log:tail 50      # 마지막 50줄
php cli.php log:clear        # 오늘 로그 삭제
php cli.php log:clean        # 30일 이전 로그 삭제
```

---

## 사용 예제

### 기본 로깅

```php
logger()->debug('디버그 메시지');
logger()->info('사용자 로그인', ['user_id' => 123]);
logger()->warn('느린 쿼리', ['query' => 'SELECT ...', 'time' => 2.5]);
logger()->error('결제 실패', ['order_id' => 456, 'reason' => 'timeout']);
```

### 로그 파일 확인

```php
$lastLines = logger()->tail(50);
echo $lastLines;
```

### 로그 로테이션

```php
// 크론잡에서 실행: 30일 이전 로그 삭제
$deleted = logger()->clean(30);
echo "{$deleted}개 파일 삭제";
```

### 조건부 로깅

```php
// config: log.level = 'warn' → debug/info는 무시됨
logger()->debug('이 메시지는 기록되지 않음');  // 무시
logger()->warn('이 메시지는 기록됨');            // 기록
logger()->error('이 메시지도 기록됨');           // 기록
```

---

## 내부 동작

### 로그 파일 구조

```text
storage/logs/2026-03-22.log

[2026-03-22 14:30:15] [INFO] 사용자 로그인 {"user_id":123}
[2026-03-22 14:30:16] [ERROR] 결제 실패 {"order_id":456,"reason":"timeout"}
```

- **일별 파일**: `YYYY-MM-DD.log` 형식
- **형식**: `[timestamp] [LEVEL] message {context_json}`

### 레벨 필터링

| 레벨 | 값 | 포함 범위 |
| --- | --- | --- |
| `DEBUG` | 0 | DEBUG, INFO, WARN, ERROR |
| `INFO` | 1 | INFO, WARN, ERROR |
| `WARN` | 2 | WARN, ERROR |
| `ERROR` | 3 | ERROR만 |

설정된 `minLevel` 미만의 로그는 `write()` 내부에서 즉시 반환 (파일 I/O 없음).

### tail() 구현 — 역방향 읽기

```text
tail(20)
├─ fseek(0, SEEK_END) — 파일 끝으로
├─ 8KB 버퍼 단위로 역방향 읽기
├─ 개행 기준 분할
├─ 20줄 수집될 때까지 반복
└─ 결과 반환
```

대용량 로그 파일에서도 전체를 읽지 않고 끝부분만 효율적으로 읽는다.

### 파일 잠금

```php
file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
```

`LOCK_EX` — 동시 쓰기 시 줄이 겹치지 않도록 보호.

---

## 보안 고려사항

### 로그 인젝션 방어

메시지에서 `\r`, `\n`, `\0`을 공백으로 치환:

```php
$message = str_replace(["\r", "\n", "\0"], ' ', $message);
```

공격자가 개행을 삽입하여 가짜 로그 줄을 만드는 것을 방지.

### context는 JSON 인코딩

`$context` 배열은 `json_encode()`로 직렬화되어 줄 끝에 추가된다. 사용자 입력이 포함되어도 JSON 이스케이프로 안전.

---

## 주의사항

1. **일별 파일 로테이션**: 자동 로테이션 없음. `clean()` 또는 크론잡으로 오래된 파일 수동 삭제 필요.

2. **디스크 용량**: 고트래픽 환경에서 DEBUG 레벨은 디스크를 빠르게 소모. 운영 환경에서는 `warn` 이상 권장.

3. **동시성**: `FILE_APPEND | LOCK_EX`로 안전하지만, 매우 높은 동시성에서는 성능 저하 가능. 이 경우 syslog나 외부 로깅 서비스 권장.

4. **CLI 환경**: CLI에서도 정상 동작. 같은 로그 파일에 웹/CLI 로그가 혼합될 수 있다.

5. **타임존**: `date()` 함수 기반이므로 `date_default_timezone_set()` 또는 `php.ini`의 `date.timezone` 설정에 의존.

---

## 연관 도구

- [Guard](Guard.md) — 공격 감지 로깅 (`logger()->warn()`)
- [Debug](Debug.md) — 개발용 디버깅
- [Schedule](Schedule.md) — 크론잡에서 `logger()->clean()` 호출
