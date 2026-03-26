# Guard — 입력 살균 + 공격 차단

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Guard` |
| 파일 | `catphp/Guard.php` (521줄) |
| Shortcut | `guard()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Ip` (공격 IP 로깅), `Cat\Log` (로깅), `Cat\Firewall` (자동 차단) |
| 코어 헬퍼 | `parse_size()` (요청 크기 파싱) |

---

## 설정

```php
// config/app.php
'guard' => [
    'auto_ban'      => false,   // 공격 감지 시 자동 IP 차단 (기본 false)
    'max_body_size' => '10M',   // 최대 요청 바디 크기 (기본 10MB)
],
```

---

## 메서드 레퍼런스

### 살균 메서드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `path` | `path(string $input): string` | `string` | 경로 트래버설 차단 (`../`, null 바이트, 유니코드 오버롱 등) |
| `xss` | `xss(string $input): string` | `string` | XSS 방지 (스크립트/이벤트핸들러/위험 프로토콜 제거) |
| `sql` | `sql(string $input): string` | `string` | SQL 인젝션 탐지 (**로깅만**, 실제 방어는 PDO) |
| `clean` | `clean(string $input): string` | `string` | 종합 살균 (제어문자 + CRLF + XSS + SQL 탐지) |
| `cleanArray` | `cleanArray(array $data, array $except = []): array` | `array` | 배열 재귀 살균 (특정 키 제외 가능) |
| `header` | `header(string $input): string` | `string` | 헤더 인젝션 차단 (`\r`, `\n`, `\0` 제거) |
| `filename` | `filename(string $name): string` | `string` | 파일명 살균 (위험 확장자 차단, 특수문자 제거) |

### 검증 메서드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `contentType` | `contentType(array $allowed = [...]): bool` | `bool` | Content-Type 허용 확인 |
| `maxBodySize` | `maxBodySize(?string $size = null): bool` | `bool` | 요청 크기 제한 확인 |

### 전체 입력

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `all` | `all(): array` | `array` | 모든 입력 키/값 살균 (키 정규화 포함) |

### 미들웨어 / 콜백

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `middleware` | `middleware(): callable` | `callable` | 전체 요청 자동 검사 미들웨어 |
| `onAttack` | `onAttack(callable $callback): self` | `self` | 공격 감지 콜백 설정 |

---

## 사용 예제

### 미들웨어 (권장)

```php
// 전역 미들웨어로 등록 — 모든 요청 자동 살균
router()->use(guard()->middleware());
// 1. 요청 크기 확인 (초과 시 413)
// 2. 전체 입력 살균 → input() 캐시에 반영
```

### 개별 살균

```php
// XSS
$safe = guard()->xss($userInput);

// 경로 트래버설
$safePath = guard()->path($filename);

// 종합 살균
$clean = guard()->clean($input);

// 배열 살균 (password 키 제외)
$data = guard()->cleanArray($_POST, except: ['password']);
```

### 파일명 살균

```php
$safe = guard()->filename('my file.php.jpg');
// → 'my_file.php.jpg.blocked' (php 확장자 감지)

$safe = guard()->filename('report 2024.pdf');
// → 'report_2024.pdf'

$safe = guard()->filename('../../../etc/passwd');
// → 'etcpasswd'
```

### 공격 감지 콜백

```php
guard()->onAttack(function (string $type, string $ip) {
    // Telegram 알림, Slack 통보 등
    telegram()->text("⚠️ 공격 감지: {$type} from {$ip}")->send();
});
```

### Content-Type 검증

```php
if (!guard()->contentType(['application/json'])) {
    json()->fail('JSON만 허용', code: 415);
}
```

---

## 내부 동작

### XSS 살균 순서

```text
xss($input)
├─ 0. null 바이트 + 제로폭 유니코드 제거
├─ 1. HTML 엔티티 디코딩 후 재검사 (&#106;avascript: 우회 방어)
└─ 2. xssClean()
    ├─ <script> 태그 제거
    ├─ 위험 태그 제거 (iframe, object, embed, svg, math, base, ...)
    ├─ 자체 닫힘 태그 제거 (img, video, audio, input, ...)
    ├─ 이벤트 핸들러 제거 (on\w+=..., <svg/onload= 대응)
    ├─ style 속성 제거
    ├─ 위험 프로토콜 제거 (javascript:, vbscript:, data:)
    └─ CSS expression(), -moz-binding 제거
```

### 경로 트래버설 방어

40개 이상의 위험 패턴을 탐지·제거:

- **기본**: `../`, `..\\`
- **URL 인코딩**: `%2e%2e`, `%252e%252e` (이중 인코딩)
- **유니코드 오버롱**: `%c0%ae`, `%e0%80%ae`, `%f0%80%80%ae`
- **Null 바이트**: `%00`, `\0`
- **Tomcat 스타일**: `..;/`
- **반복 패턴**: 치환 후 새로운 `../` 생성 방어 (while 루프)
- **세그먼트 탐지**: `test/..`, 단독 `..` 등 슬래시 없는 `..` 세그먼트 분할 제거

### 파일명 위험 확장자

차단 대상 (이중 확장자도 탐지):

- **PHP**: `php`, `phtml`, `php3`~`php8`, `phar`, `phps`
- **실행**: `exe`, `sh`, `bat`, `cmd`, `ps1`, `vbs`
- **서버사이드**: `jsp`, `asp`, `aspx`, `cgi`
- **서버 설정**: `htaccess`, `htpasswd`, `shtml`
- **XSS 벡터**: `svg`

### SQL 인젝션 탐지 패턴

로깅 전용 (PDO prepared statement가 실제 방어):

- `UNION SELECT`, `DROP TABLE`, `DELETE FROM`
- `SLEEP()`, `BENCHMARK()`, `WAITFOR DELAY`
- `LOAD_FILE`, `INTO OUTFILE`, `INFORMATION_SCHEMA`
- SQL 주석 (`--`, `#`, `/* */`)

### 미들웨어 흐름

```text
middleware()
├─ maxBodySize() 확인 → 초과 시 413 + exit
├─ all() — 전체 입력 살균
│   ├─ 키 살균: 개행 제거 + 128자 제한 + 영숫자/밑줄/하이픈만
│   └─ 값 살균: path() + clean()
└─ input(data: $sanitized) — 살균 결과를 input() 캐시에 반영
```

### 공격 보고 (reportAttack)

```text
reportAttack($type, $input)
├─ Log: logger()->warn("공격 감지: [{type}] IP={ip}")
├─ 콜백: ($attackCallback)($type, $ip)
└─ 자동 차단: auto_ban=true → firewall()->ban($ip)
```

---

## 보안 고려사항

- **다층 방어**: XSS 살균은 PDO prepared statement, CSP 헤더 등과 함께 사용해야 함
- **SQL 탐지는 보조적**: `sql()` 메서드는 로깅만 수행. 실제 SQL 인젝션 방어는 PDO prepared statement가 담당
- **제로폭 유니코드**: `\x{200B}`, `\x{200C}`, `\x{200D}`, `\x{FEFF}`, `\x{00AD}` 사전 제거
- **자동 차단 주의**: `auto_ban = true` 시 오탐(false positive)으로 정상 사용자가 차단될 수 있음

---

## 주의사항

1. **미들웨어 순서**: Guard 미들웨어는 다른 미들웨어보다 먼저 등록해야 살균된 입력이 전달된다.

2. **`password` 제외**: `cleanArray($data, except: ['password'])` — 비밀번호 필드는 살균하면 안 된다 (해시 전 원본 필요).

3. **HTML 에디터**: WYSIWYG 에디터의 HTML 콘텐츠를 `xss()`로 살균하면 정상 태그도 제거될 수 있다. 허용 태그 기반 살균(HTMLPurifier 등)을 별도 사용 권장.

4. **`all()` 키 정규화**: 키에서 특수문자가 제거되므로, `my-field[0]` 같은 배열 폼 필드명이 `my-field0`으로 변환될 수 있다.

5. **`filename()` 반환값**: 모든 확장자가 위험하면 `unnamed` 반환. 빈 문자열은 반환되지 않음.

---

## 연관 도구

- [Firewall](Firewall.md) — 자동 IP 차단 (`auto_ban`)
- [Ip](Ip.md) — 공격 IP 감지
- [Log](Log.md) — 공격 로깅
- [Upload](Upload.md) — 파일 업로드 시 `filename()` 활용
- [Router](Router.md) — 미들웨어 등록
