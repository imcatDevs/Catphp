# Env — 환경변수 관리

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Env` |
| 파일 | `catphp/Env.php` (264줄) |
| Shortcut | `env()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | 없음 |

---

## 설정

별도 config 없음. `.env` 파일을 직접 로드한다.

---

## 메서드 레퍼런스

### 로드

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `load` | `load(string $path): self` | `self` | `.env` 파일 파싱 + putenv + $_ENV + $_SERVER |
| `isLoaded` | `isLoaded(): bool` | `bool` | 로드 여부 |

### 읽기/쓰기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `get` | `get(string $key, mixed $default = null): mixed` | `mixed` | 환경변수 읽기 (타입 캐스팅) |
| `set` | `set(string $key, string $value): self` | `self` | 런타임 설정 (putenv + $_ENV + $_SERVER) |
| `has` | `has(string $key): bool` | `bool` | 존재 확인 |
| `all` | `all(): array` | `array` | 로드된 모든 변수 |

### 검증

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `required` | `required(array $keys): self` | `self` | 필수 변수 확인 (누락 시 `RuntimeException`) |

### 파일 쓰기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `write` | `write(string $path, string $key, string $value): bool` | `bool` | `.env` 파일에 키=값 추가/수정 |

---

## 사용 예제

### .env 파일 로드

```php
// 부팅 시 (index.php 또는 catphp.php에서)
env()->load(__DIR__ . '/.env');
```

### 값 읽기

```php
$debug = env()->get('APP_DEBUG');           // true (자동 캐스팅)
$host  = env()->get('DB_HOST', '127.0.0.1'); // 기본값
$name  = env()->get('APP_NAME');             // 문자열
```

### 타입 캐스팅

```text
.env 파일:
APP_DEBUG=true      → bool true
APP_DEBUG=false     → bool false
DB_PORT=3306        → string '3306' (숫자 캐스팅 없음)
SECRET=null         → null
EMPTY=(empty)       → string ''
```

### 필수 변수 확인

```php
env()->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_KEY']);
// 누락 시: RuntimeException: 필수 환경변수 누락: DB_HOST, APP_KEY
```

### 변수 참조

```env
# .env 파일
APP_URL=https://example.com
API_URL=${APP_URL}/api
```

```php
env()->get('API_URL');  // 'https://example.com/api'
```

### .env 파일 수정

```php
// 키가 있으면 값 교체, 없으면 마지막에 추가
env()->write(__DIR__ . '/.env', 'APP_KEY', 'new-secret-key');

// export 접두사 보존
// export APP_KEY=old → export APP_KEY=new-secret-key
```

### 런타임 설정

```php
env()->set('APP_ENV', 'testing');
// putenv + $_ENV + $_SERVER 모두 설정
```

---

## .env 파일 형식

```env
# 주석 (# 또는 ;)
; 이것도 주석

# 기본 키=값
APP_NAME=CatPHP
APP_DEBUG=true
APP_URL=https://example.com

# 따옴표 (공백/특수문자 포함 시)
APP_DESCRIPTION="My awesome app"
DB_PASSWORD='p@ss#word'

# 큰따옴표: 이스케이프 지원
GREETING="Hello\nWorld"    # → "Hello" + 개행 + "World"

# 작은따옴표: 그대로
RAW_VALUE='Hello\nWorld'   # → "Hello\nWorld" (이스케이프 없음)

# export 접두사 (호환)
export DB_HOST=127.0.0.1

# 변수 참조
BASE_URL=https://example.com
API_URL=${BASE_URL}/api

# 인라인 주석
DB_PORT=3306 # 기본 포트
```

---

## 내부 동작

### 로드 흐름

```text
load($path)
├─ 파일 존재 확인
├─ 줄별 파싱:
│   ├─ #, ; 주석 건너뛰기
│   ├─ export 접두사 제거
│   ├─ = 기준 키/값 분리
│   ├─ parseValue(): 따옴표 제거 + 이스케이프 처리
│   └─ resolveReferences(): ${VAR} 치환
├─ putenv("KEY=VALUE")
├─ $_ENV[$key] = $value
└─ $_SERVER[$key] = $value
```

### 값 검색 순서

```text
get($key)
├─ 1. 내부 캐시 ($this->vars)
├─ 2. $_ENV[$key]
└─ 3. getenv($key)
```

### 타입 캐스팅 규칙

| 값 | 변환 |
| --- | --- |
| `true`, `(true)` | `bool true` |
| `false`, `(false)` | `bool false` |
| `null`, `(null)` | `null` |
| `empty`, `(empty)` | `string ''` |
| 그 외 | `string` 그대로 |

### write() 동작

```text
write($path, $key, $value)
├─ 값 이스케이프 (공백/#/따옴표 포함 시 큰따옴표로 감싸기)
├─ 파일 없으면 → 새 파일 생성
├─ 파일 있으면 → 줄별 검색
│   ├─ "KEY=" 또는 "export KEY=" 매칭 → 값 교체
│   └─ 미매칭 → 마지막에 추가
├─ set($key, $value) — 런타임 반영
└─ file_put_contents(LOCK_EX)
```

---

## 보안 고려사항

- **`.env` 파일 웹 접근 차단**: `.htaccess` 또는 웹 서버 설정으로 `.env` 파일 직접 접근을 반드시 차단
- **민감 정보**: DB 비밀번호, API 키 등 민감 정보는 `.env`에 저장하고 `config/app.php`에서 `env()`로 참조
- **파일 잠금**: `write()` 시 `LOCK_EX` — 동시 쓰기 방지

---

## 주의사항

1. **숫자 캐스팅 없음**: `DB_PORT=3306` → 문자열 `'3306'`. 정수 필요 시 `(int) env()->get('DB_PORT')`.

2. **required()는 raw 값 기준**: `'null'` 문자열도 유효한 값으로 인정. 빈 문자열 `''`만 누락으로 판정.

3. **변수 참조 순서**: `${VAR}` 참조는 `.env` 파일 내 선언 순서에 의존. 참조 대상이 먼저 선언되어야 한다.

4. **write()는 운영 주의**: `.env` 파일을 런타임에 수정하는 것은 권장되지 않는다. 설정 도구/CLI에서만 사용.

5. **load() 중복 호출**: 같은 파일을 여러 번 로드하면 마지막 값이 적용된다.

---

## 연관 도구

- 코어 `config()` — `.env` 값을 `config/app.php`에서 참조
- [Debug](Debug.md) — `APP_DEBUG` 환경변수 활용
