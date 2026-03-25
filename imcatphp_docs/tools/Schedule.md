# Schedule — 크론 스케줄러

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Schedule` |
| 파일 | `catphp/Schedule.php` (364줄) |
| Shortcut | `schedule()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Log` (실패 로깅) |

---

## 설정

별도 config 없음. 시스템 크론탭에 1분 간격 등록 필요:

```bash
* * * * * php /path/to/cli.php schedule:run
```

---

## 메서드 레퍼런스

### 태스크 등록

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `command` | `command(string $command): self` | `self` | CLI 명령어 예약 |
| `call` | `call(callable $callback, string $description = ''): self` | `self` | 콜백 함수 예약 |

### 스케줄 표현식

| 메서드 | 시그니처 | 설명 |
| --- | --- | --- |
| `cron` | `cron(string $expression): self` | 원시 cron 표현식 |
| `everyMinute` | `everyMinute(): self` | 매분 |
| `everyMinutes` | `everyMinutes(int $n): self` | N분마다 |
| `everyFiveMinutes` | `everyFiveMinutes(): self` | 5분마다 |
| `everyFifteenMinutes` | `everyFifteenMinutes(): self` | 15분마다 |
| `everyThirtyMinutes` | `everyThirtyMinutes(): self` | 30분마다 |
| `hourly` | `hourly(): self` | 매시간 정각 |
| `hourlyAt` | `hourlyAt(int $minute): self` | 매시간 N분에 |
| `daily` | `daily(): self` | 매일 자정 |
| `dailyAt` | `dailyAt(string $time): self` | 매일 HH:MM |
| `weekly` | `weekly(): self` | 매주 일요일 자정 |
| `weeklyOn` | `weeklyOn(int $dayOfWeek, string $time = '00:00'): self` | 매주 지정 요일·시각 |
| `monthly` | `monthly(): self` | 매월 1일 자정 |
| `monthlyOn` | `monthlyOn(int $day, string $time = '00:00'): self` | 매월 지정일·시각 |

### 옵션

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `description` | `description(string $desc): self` | `self` | 설명 |
| `withoutOverlapping` | `withoutOverlapping(): self` | `self` | 중복 실행 방지 |

### 실행 / 조회

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `run` | `run(): int` | `int` | 실행 대상 실행 → 실행 수 |
| `list` | `list(): array` | `array` | 등록된 태스크 목록 |

---

## 사용 예제

### 기본 스케줄 등록

```php
// cli.php 또는 부팅 파일에서
schedule()->command('cache:clear')->daily();
schedule()->command('log:rotate')->weeklyOn(1, '03:00');  // 월요일 3시
schedule()->command('db:backup')->dailyAt('02:00')->withoutOverlapping();
```

### 콜백 스케줄

```php
schedule()->call(function () {
    logger()->info('heartbeat');
})->everyFiveMinutes()->description('하트비트');

schedule()->call(function () {
    $count = db()->table('sessions')->where('expires_at', date('Y-m-d H:i:s'), '<')->delete();
    logger()->info("만료 세션 {$count}개 삭제");
})->hourly()->description('세션 정리');
```

### 커스텀 cron

```php
schedule()->command('report:generate')
    ->cron('0 9 * * 1-5')  // 평일 오전 9시
    ->description('일일 보고서');
```

### 태스크 목록 조회

```php
$tasks = schedule()->list();
// [['expression' => '0 0 * * *', 'type' => 'command', 'description' => 'cache:clear'], ...]
```

---

## 내부 동작

### cron 표현식 매칭

```text
isDue('30 2 * * 1', $now)
├─ 분(30) == $now->format('i')? ✓
├─ 시(2) == $now->format('G')? ✓
├─ 일(*) == 모든 값 ✓
├─ 월(*) == 모든 값 ✓
└─ 요일(1) == $now->format('w')? ✓ → 실행!
```

지원 구문:

- `*` — 모든 값
- `5` — 정확 매칭
- `1,5,15` — 콤마 분리
- `1-5` — 범위
- `*/5` — 스텝
- `10-20/3` — 범위 기반 스텝

### withoutOverlapping 락

```text
acquireLock($name)
├─ storage/cache/schedule_{md5}.lock
├─ fopen('c') + flock(LOCK_EX | LOCK_NB) — 비블로킹
├─ 락 획득 실패 → 10분 초과 락 파일 자동 정리
└─ PID 기록 → 핸들 보관

releaseLock($name)
├─ flock(LOCK_UN) + fclose()
└─ unlink()
```

### command() 실행

```php
$parts = preg_split('/\s+/', $cmd) ?: [$cmd];
$escaped = implode(' ', array_map('escapeshellarg', $parts));
passthru("php " . escapeshellarg(__DIR__ . '/../cli.php') . " " . $escaped);
```

명령어를 공백 분리 후 각 인수를 개별 `escapeshellarg()` — 쉘 인젝션 방지 + 옵션 정상 전달.

---

## 주의사항

1. **크론탭 필수**: `schedule:run`을 1분마다 실행하는 시스템 크론탭 등록이 필요.
2. **withoutOverlapping**: 파일 락 기반. 프로세스가 비정상 종료되면 10분 후 자동 정리.
3. **에러 처리**: 태스크 실패 시 `logger()->error()` 로깅. 나머지 태스크는 계속 실행.
4. **요일 번호**: 0=일, 1=월, ..., 6=토.
5. **싱글턴 상태**: `command()`, `call()`은 싱글턴의 배열에 누적. 매 요청에서 동일한 스케줄 정의 필요.

---

## 연관 도구

- [Cli](Cli.md) — CLI 명령어 정의
- [Log](Log.md) — 실행 실패 로깅
- [Cache](Cache.md) — 락 파일 디렉토리
