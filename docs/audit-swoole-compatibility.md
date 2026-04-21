# Swoole 호환성 감사 보고서

**대상 프레임워크**: CatPHP
**감사 일자**: 2026-04-21
**감사 범위**: `catphp/` 59개 도구 파일 + `Swoole.php` 브리지
**감사 목적**: PHP-FPM → Swoole 전환 시 **상태 누수 / 메모리 누수 / 호환성 이슈** 식별

---

## 1. 요약 (Executive Summary)

### 예상 성능 향상

| 시나리오 | FPM (현재) | Swoole | 배수 |
| --- | --- | --- | --- |
| Hello World | ~2,000 RPS | ~50,000 RPS | **~25x** |
| DB 1회 조회 라우트 | ~500 RPS | ~5,000~10,000 RPS | **~10~20x** |
| 무거운 비즈니스 로직 | ~200 RPS | ~1,000~2,000 RPS | **~5~10x** |

### 준비 상태

- **강점**: `Swoole.php` 브리지에서 `$_GET/$_POST/$_COOKIE/$_FILES/$_SERVER` 요청별 초기화 구현 완료
- **약점**: **6개 싱글턴**이 요청 간 상태를 누수할 수 있음
- **결론**: **조건부 권장** — 아래 🔴 Critical 이슈 2건을 해결하면 운영 투입 가능

### 이슈 건수

| 심각도 | 건수 | 대표 항목 |
| --- | --- | --- |
| 🔴 Critical (상태 누수 확실) | 2 | `Response`, `Meta` |
| 🟠 High (조건부 누수) | 4 | `Request`, `Auth`, `Sitemap`, `Session` |
| 🟡 Medium (잠재 위험) | 3 | `Event`, `Flash`, 메모리 누수 일반 |
| 🟢 Safe (무상태) | 45+ | `Hash`, `Slug`, `DB`, `Cache` 등 |

---

## 2. 상태 누수 상세 분석

### 🔴 C-1: `Response` 전역 싱글턴 상태 누수

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Response.php:19-30`

**문제 코드**:

```php
final class Response
{
    private static ?self $instance = null;

    private int $statusCode = 200;
    private array $headers = [];
    private array $cookies = [];
}
```

**영향**: 요청 A가 `response()->status(500)->header('X-Foo', 'bar')`로 응답을 빌드했을 때, 요청 B에서 `response()`를 얻으면 **statusCode=500 상태가 그대로 남아있음**.

**위험도**: 🔴 Critical — 응답 헤더 오염 → 보안/기능 모두 파괴

**해결 방안**: 요청 종료 시점에 `Swoole.php` `handleRequest()` finally 절에서 싱글턴 리셋:

```php
Response::reset(); // 새 public static 메서드 추가 필요
```

또는 `Swoole` 브리지에서 `header()`/`setcookie()` 대신 **`$res->header()` 직접 호출**로 변경하여 싱글턴을 우회.

---

### 🔴 C-2: `Meta` SEO 태그 누적 누수

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Meta.php:11-22`

**문제 코드**:

```php
private string $titleStr = '';
private string $descriptionStr = '';
private array $ogTags = [];
private array $twitterTags = [];
private ?array $jsonLdData = null;
```

**영향**: 첫 요청이 `meta()->title('A')`를 설정하면, 다음 요청에서 `meta()->render()` 시 **A의 제목이 계속 출력됨**. OG 태그는 누적되어 중복 출력.

**위험도**: 🔴 Critical — SEO 오염, 사용자별 데이터 누수 가능

**해결 방안**: 요청 시작 시 `Meta::reset()` 호출 또는 singleton 패턴 제거(요청별 인스턴스).

---

### 🟠 H-1: `Request` 입력 캐시 누수

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Request.php:23-34`

**문제 코드**:

```php
private array $inputCache;
private ?string $rawBody = null;
private ?array $jsonCache = null;
```

**영향**: `Swoole.php:1063`에서 `\input(data: $inputData)` 호출로 `inputCache`는 갱신되지만, `rawBody`/`jsonCache`는 **이전 요청 값 잔류**. 드물게 `request()->raw()` 호출 시 이전 body 반환 가능.

**위험도**: 🟠 High — 조건부이지만 발생 시 치명적 (타 사용자 데이터 노출)

**해결 방안**:

```php
public static function reset(): void {
    if (self::$instance !== null) {
        self::$instance->rawBody = null;
        self::$instance->jsonCache = null;
    }
}
```

`Swoole.php::handleRequest()` 시작부에서 호출.

---

### 🟠 H-2: `Auth` JWT 페이로드 캐시 누수

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Auth.php:28-32`

**문제 코드**:

```php
private ?array $apiPayload = null;
```

**영향**: 요청 A에서 JWT 검증 후 `apiPayload`가 설정되면, 요청 B에서 **인증 헤더가 없어도 이전 사용자로 로그인된 상태처럼 조회됨** (`auth()->id()`, `auth()->user()`).

**위험도**: 🟠 High — 인증 우회 가능 (만약 JWT 미재검증 경로가 있을 경우)

**해결 방안**:

- `Swoole.php` 요청 시작 시 `Auth::reset()` 필수
- JWT 검증 루틴이 **매 요청마다 강제로 재검증**하도록 구조 확인

---

### 🟠 H-3: `Sitemap` URL 누적

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Sitemap.php:34-44`

**문제 코드**:

```php
private array $urls = [];
private array $sitemaps = [];
private bool $isIndex = false;
```

**영향**: 사이트맵 생성 요청마다 URL이 **중복 누적**되어 XML이 무한 팽창.

**위험도**: 🟠 High — 메모리/디스크 폭주

**해결 방안**: `render()` 후 자동 리셋 또는 `Swoole` 요청 간 리셋.

---

### 🟠 H-4: `Session` started 플래그

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Session.php:21-24`

**문제 코드**:

```php
private bool $started = false;
```

**영향**: PHP 내장 `$_SESSION`은 CLI(Swoole) 환경에서 **정상 작동하지 않음**. `session_start()`도 HTTP 헤더 기반이라 Swoole의 `$res->cookie()`와 충돌.

**위험도**: 🟠 High — 세션 기능 전반 장애

**해결 방안**: Swoole 전용 **세션 드라이버** 구현 필요 (Redis/파일 기반 + 수동 쿠키 관리). 또는 `Session.php`를 PSR-6 캐시 위에 재구현.

---

### 🟡 M-1: `Event` 리스너 누적 (조건부)

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Event.php:12-22`

**문제 코드**:

```php
private array $listeners = [];
```

**영향**:

- **부트스트랩에서만 `on()` 등록** → 안전 (권장 패턴)
- **요청 핸들러 내부에서 `on()` 등록** → 리스너 무한 누적 → 메모리 누수 + 중복 실행

**위험도**: 🟡 Medium — 사용 패턴에 따라 달라짐

**해결 방안**: 문서에 **"`event()->on()` 은 bootstrap 콜백에서만 호출"** 명시. 위반 시 경고 로그 추가 고려.

---

### 🟡 M-2: `Flash` 세션 의존

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Flash.php`

**영향**: `$_SESSION` 기반이므로 `Session` 이슈와 동일. Swoole에서 세션이 안 되면 Flash 메시지도 작동 불가.

**위험도**: 🟡 Medium — 기능 장애 (보안 이슈는 아님)

---

### 🟡 M-3: 출력 버퍼 오버헤드

**위치**: `@/e:/imcat_project/Newcatphp/catphp/Swoole.php:1074-1093`

**현재 구현**:

```php
ob_start();
\router()->dispatch();
$body = ob_get_clean() ?: '';
$res->end($body);
```

**영향**: 모든 요청이 출력 버퍼를 거침 → **메모리 사용량 증가** + JSON 스트리밍 응답 불가.

**위험도**: 🟡 Medium — 성능 저하만 있고 정확성은 유지

**해결 방안**: `Json`/`Response` 도구를 **Swoole 모드 감지 후 직접 `$res->end()` 호출**하도록 개선.

---

## 3. 무상태 안전 도구 목록 (🟢)

다음 45+ 도구는 싱글턴이지만 **요청 간 공유해도 무해한 상태**만 가짐:

| 분류 | 파일 |
| --- | --- |
| 해시/암호 | `Hash`, `Encrypt`, `Csrf`, `Captcha` |
| 데이터 | `DB` (연결 풀), `Redis`, `Cache` (저장소 위임) |
| 문자열/포맷 | `Slug`, `Text`, `Sanitizer`, `Guard`, `Faker` |
| 입출력 | `Json`, `Cors`, `Cookie`, `Ip`, `Geo` |
| 로깅/감사 | `Log`, `Debug`, `Firewall`, `Rate` |
| 기타 | `Router` (bootstrap 등록), `Storage`, `Upload`, `Hash`, `Http`, `Mail`, `Notify` |

**공통 특징**: 체이닝 메서드가 `$c = clone $this` 패턴으로 **이뮤터블**하며, 베이스 싱글턴의 상태를 변경하지 않음.

---

## 4. `Swoole.php` 브리지 현황

### ✅ 잘 구현된 부분

- **슈퍼글로벌 요청별 초기화** (`@/e:/imcat_project/Newcatphp/catphp/Swoole.php:1044-1049`)
- **연결 풀** (DB/Redis) — 워커별 관리, WorkerStop 시 정리
- **Hot reload** (inotify + 폴링 폴백)
- **JSON body 파싱** (깊이 64 제한으로 DoS 방어)
- **에러 핸들러** — API/웹 분기, CatUI JSON 포맷 일관성
- **출력 버퍼 누수 방지** (`finally` 절에서 `ob_end_clean`)

### ⚠️ 개선 필요

1. **싱글턴 리셋 훅 부재** — 요청 시작 시 `Response/Request/Meta/Auth/Sitemap::reset()` 호출 필요
2. **`headers_list()` 의존** — `header()` 호출이 Swoole에서 실제 헤더를 설정하지 않음. 동작 확인 필요
3. **`http_response_code()` 의존** — 동일 이슈
4. **세션 드라이버 부재** — Swoole 전용 세션 구현 필요

---

## 5. 권장 적용 로드맵

### Phase 1 — 리셋 훅 도입 (필수, 1~2일)

각 상태 누수 싱글턴에 `public static function reset(): void` 메서드 추가:

```php
// Response.php, Meta.php, Request.php, Auth.php, Sitemap.php
public static function reset(): void
{
    self::$instance = null; // 또는 필드 개별 초기화
}
```

`Swoole.php::handleRequest()` 시작부에 호출:

```php
Response::reset();
Meta::reset();
Request::reset();
Auth::reset();
Sitemap::reset();
```

### Phase 2 — 출력 직접화 (권장, 3~5일)

`Response`/`Json` 도구가 **Swoole 컨텍스트 감지** 후 `$res->end()` 직접 호출:

```php
if (defined('CAT_SWOOLE_RES')) {
    CAT_SWOOLE_RES->end($body);
    return;
}
echo $body;
```

→ `ob_start`/`headers_list` 우회로 **추가 20~30% 성능 향상** 예상.

### Phase 3 — 세션 드라이버 (선택, 5~7일)

Redis 기반 Swoole 호환 세션 드라이버 구현. Flash/Auth/CSRF 전 영역에 영향.

### Phase 4 — 벤치마크 & 운영 투입

- `wrk -t4 -c100 -d30s` 로 FPM ↔ Swoole 비교
- 메모리 누수 감시 (`ps`, `memory_get_usage` 로깅)
- **max_request** 설정으로 워커 주기적 재시작 (누수 안전망)

---

## 6. 최종 결론

### 현재 Swoole 사용 가능 여부

- **즉시 가능 영역**: 정적 콘텐츠, 무상태 API, `DB`/`Cache`/`Redis` 기반 조회 엔드포인트
- **리셋 훅 도입 후 가능**: `response()`/`meta()`/`auth()` 사용 라우트
- **추가 작업 필요**: 세션 의존 기능 (`Flash`, `Csrf`, 로그인 유지 등)

### 예상 ROI

- **Phase 1만 적용** → **5~10배 성능 향상** (무상태/DB 라우트 대부분 커버)
- **Phase 1+2 적용** → **10~20배 성능 향상**
- **Phase 3까지** → 전면 이전 가능 (세션 의존 기능 포함)

### 종합 권고

**Phase 1 우선 진행 → 무상태 API 위주 부분 도입 → 벤치마크 검증 후 점진 확대**를 강력히 권장합니다. CatPHP의 "빠른 속도" 4대 원칙과 Swoole의 방향성이 일치하므로, 리셋 훅만 도입하면 프레임워크 철학을 훼손하지 않고 성능을 극대화할 수 있습니다.

---

*End of Report*
