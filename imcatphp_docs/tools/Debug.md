# Debug — 디버그 유틸

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Debug` |
| 파일 | `catphp/Debug.php` (346줄) |
| Shortcut | `debug()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | 없음 |

---

## 설정

```php
// config/app.php
'app' => [
    'debug' => true,  // false이면 bar() 비활성화
],
```

---

## 메서드 레퍼런스

### 변수 덤프

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `dump` | `dump(mixed ...$vars): self` | `self` | 변수 출력 (계속 실행) |
| `dd` | `dd(mixed ...$vars): never` | `never` | 변수 출력 후 종료 |

### 타이머

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `timer` | `timer(string $label = 'default'): self` | `self` | 타이머 시작 |
| `timerEnd` | `timerEnd(string $label = 'default'): float` | `float` | 타이머 종료 → 경과 ms |
| `elapsed` | `elapsed(): float` | `float` | 앱 시작부터 경과 ms |
| `measure` | `measure(string $label, callable $callback): mixed` | `mixed` | 콜백 실행 시간 측정 |

### 메모리

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `memory` | `memory(): string` | `string` | 현재 메모리 (사람 읽기) |
| `peakMemory` | `peakMemory(): string` | `string` | 피크 메모리 |
| `memoryUsage` | `memoryUsage(): int` | `int` | 현재 메모리 (바이트) |

### 로그

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `log` | `log(string $type, string $message, float $duration = 0.0): self` | `self` | 디버그 로그 기록 |
| `getLogs` | `getLogs(): array` | `array` | 전체 로그 |
| `logCount` | `logCount(): int` | `int` | 로그 수 |
| `clearLogs` | `clearLogs(): self` | `self` | 로그 초기화 |

### 유틸

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `trace` | `trace(int $limit = 10): self` | `self` | 호출 스택 출력 |
| `bar` | `bar(): string` | `string` | HTML 디버그 바 |

---

## 사용 예제

### dump / dd

```php
debug()->dump($user, $query);   // 변수 출력 후 계속 실행
debug()->dd($result);           // 출력 후 종료 (호출 위치 표시)
```

### 타이머 사용

```php
debug()->timer('db');
$users = db()->table('users')->all();
$ms = debug()->timerEnd('db');  // 예: 12.34 (ms)
```

### measure

```php
$result = debug()->measure('heavy_task', function () {
    return expensiveCalculation();
});
// 자동으로 timer + timerEnd + 로그 기록
```

### 메모리 확인

```php
echo debug()->memory();       // '4.5 MB'
echo debug()->peakMemory();   // '8.2 MB'
```

### 디버그 바

```php
// 레이아웃 하단에 삽입
echo debug()->bar();
```

### 호출 스택

```php
debug()->trace();  // 현재 위치의 스택 트레이스 출력
```

### 커스텀 로그

```php
debug()->log('query', 'SELECT * FROM users WHERE id = 1', 5.2);
debug()->log('cache', 'HIT: user:1');

$logs = debug()->getLogs();
```

---

## 내부 동작

### prettyPrint (웹)

Catppuccin Mocha 테마 기반 컬러 출력:

| 타입 | 색상 |
| --- | --- |
| `null` | 빨강 (#f38ba8) |
| `bool` | 주황 (#fab387) |
| `int/float` | 파랑 (#89b4fa) |
| `string` | 초록 (#a6e3a1) + 길이 표시 |
| `array` | 재귀 (최대 깊이 4) |
| `object` | 보라 (#cba6f7) + 클래스명 |

### dd() 호출 위치

```text
dd()
├─ dump(...$vars)
├─ debug_backtrace(IGNORE_ARGS, 2)
├─ frame[0] = dd()를 호출한 지점 (실제 호출 위치)
└─ 파일:줄번호 표시
```

### bar() 프로덕션 차단

```php
if (!(bool) config('app.debug', false)) {
    return '';  // 프로덕션에서 정보 노출 방지
}
```

### 디버그 바 로그 타입별 색상

| 타입 | 색상 |
| --- | --- |
| `query` | 파랑 (#89b4fa) |
| `timer` | 초록 (#a6e3a1) |
| `error` | 빨강 (#f38ba8) |
| `warning` | 주황 (#fab387) |
| 기타 | 회색 (#cdd6f4) |

---

## 주의사항

1. **프로덕션 비활성화**: `app.debug = false` 설정 시 `bar()`는 빈 문자열 반환. `dump()`/`dd()`는 항상 동작하므로 코드에서 제거 필요.
2. **dd()는 never**: `exit(1)` 호출. 이후 코드 실행 불가.
3. **CLI 지원**: `dump()`, `dd()`, `trace()`, `bar()` 모두 CLI 환경에서 텍스트 출력.
4. **메모리 true 플래그**: `memory_get_usage(true)` — 실제 할당된 시스템 메모리 반환.

---

## 연관 도구

- [Log](Log.md) — 영구 로그 저장 (파일/DB)
