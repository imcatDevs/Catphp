# Cli — CLI 프레임워크

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Cli` |
| 파일 | `catphp/Cli.php` (248줄) |
| Shortcut | `cli()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | 없음 |

---

## 설정

별도 config 없음. `cli.php` 파일에서 명령어를 등록.

---

## 메서드 레퍼런스

### 명령어 등록/실행

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `command` | `command(string $name, string $description, callable $handler): self` | `self` | 명령어 등록 |
| `group` | `group(string $prefix, callable $callback): self` | `self` | 명령어 그룹 |
| `run` | `run(): void` | `void` | `$argv` 기반 자동 라우팅 |

### 인자/옵션 파싱

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `arg` | `arg(int $index): ?string` | `?string` | 위치 인자 (0-indexed) |
| `option` | `option(string $name, mixed $default = null): mixed` | `mixed` | `--key=value` 또는 `--flag` |

### 사용자 입력

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `confirm` | `confirm(string $question, ?bool $default = null): bool` | `bool` | Y/N 확인 |
| `prompt` | `prompt(string $question, ?string $default = null): string` | `string` | 텍스트 입력 |
| `choice` | `choice(string $question, array $options): ?string` | `?string` | 선택지 |

### 출력 헬퍼

| 메서드 | 시그니처 | 설명 |
| --- | --- | --- |
| `info` | `info(string $msg): void` | 파랑 텍스트 |
| `success` | `success(string $msg): void` | 초록 ✓ 텍스트 |
| `warn` | `warn(string $msg): void` | 노랑 ⚠ 텍스트 |
| `error` | `error(string $msg): void` | 빨강 ✗ 텍스트 |
| `table` | `table(array $headers, array $rows): void` | 테이블 출력 (한글 너비 반영) |
| `progress` | `progress(int $current, int $total, int $width = 40): void` | 프로그레스 바 |
| `newLine` | `newLine(int $count = 1): void` | 빈 줄 |
| `hr` | `hr(string $char = '─', int $width = 60): void` | 수평선 |

---

## 사용 예제

### 명령어 등록 (cli.php)

```php
cli()->command('greet', '인사 명령어', function () {
    $name = cli()->arg(0) ?? 'World';
    cli()->success("Hello, {$name}!");
});

cli()->command('cache:clear', '캐시 삭제', function () {
    if (cli()->confirm('캐시를 삭제하시겠습니까?', true)) {
        cache()->clear();
        cli()->success('캐시 삭제 완료');
    }
});

cli()->command('db:seed', 'DB 시딩', function () {
    $count = (int) cli()->option('count', 10);
    $users = faker()->make($count, fn($f) => [
        'name'  => $f->name(),
        'email' => $f->safeEmail(),
    ]);

    foreach ($users as $i => $user) {
        db()->table('users')->insert($user);
        cli()->progress($i + 1, $count);
    }

    cli()->success("{$count}명 시딩 완료");
});

cli()->run();
```

### 실행

```bash
php cli.php greet Alice
php cli.php cache:clear
php cli.php db:seed --count=100
php cli.php help              # 전체 명령어 목록
php cli.php help cache:clear  # 특정 명령어 도움말
```

### 테이블 출력

```php
cli()->table(
    ['ID', '이름', '이메일'],
    [
        [1, '홍길동', 'hong@example.com'],
        [2, '김철수', 'kim@example.com'],
    ]
);
```

출력:

```text
+----+--------+------------------+
| ID | 이름   | 이메일           |
+----+--------+------------------+
| 1  | 홍길동 | hong@example.com |
| 2  | 김철수 | kim@example.com  |
+----+--------+------------------+
```

### 프로그레스 바

```php
for ($i = 1; $i <= 100; $i++) {
    doWork($i);
    cli()->progress($i, 100);
}
// [████████████████████████████████████████] 100% (100/100)
```

### 사용자 입력 예시

```php
$name = cli()->prompt('이름', '홍길동');
$choice = cli()->choice('드라이버 선택', ['mysql', 'sqlite', 'pgsql']);
```

---

## 내부 동작

### $argv 파싱

```text
php cli.php cache:clear --verbose --count=10
├─ $argv[0] = 'cli.php'
├─ $argv[1] = 'cache:clear' → 명령어
├─ arg(0)   = $argv[2] (없음 → null)
├─ option('verbose') → true (플래그)
└─ option('count') → '10'
```

### 도움말 그룹핑

```text
help 출력:
├─ 명령어명에 ':' 포함 → 접두사로 그룹
│   cache:clear, cache:flush → 'cache' 그룹
│   db:seed, db:backup → 'db' 그룹
└─ 그룹별 정렬 출력
```

### 테이블 유니코드 너비

`mb_strwidth()` — 한글/일본어 등 동아시아 문자를 2칸 너비로 계산하여 정렬.

---

## 주의사항

1. **CLI 전용**: 웹 환경에서 사용 불가. `cli.php`에 `php_sapi_name() !== 'cli'` 가드 적용.
2. **arg() 인덱스**: 0부터 시작. `$argv[2]`부터 매핑 (script, command 제외).
3. **option() 타입**: 항상 `string` 또는 `true`(플래그) 반환. 숫자가 필요하면 `(int)` 캐스팅.
4. **ANSI 색상**: Windows CMD에서는 ANSI 이스케이프가 지원되지 않을 수 있음. Windows Terminal이나 Git Bash 권장.

---

## 연관 도구

- [Schedule](Schedule.md) — CLI 명령어 스케줄링
- [Queue](Queue.md) — `queue:work` CLI 워커
