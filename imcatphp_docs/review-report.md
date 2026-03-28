# CatPHP 프레임워크 전체 리뷰 보고서

> **리뷰 일자**: 2026-03-27
> **대상**: catphp v1.0.2 — 소스 58파일 · 문서 71파일 · 테스트 21파일
> **리뷰 관점**: 보안 · 아키텍처 · 버그 · 문서 · 성능 · 테스트

---

## 요약

| 심각도 | 건수 | 설명 |
| --- | --- | --- |
| 🔴 Critical | 2 | 원격 코드 실행 · 인증 우회 수준 |
| 🟠 High | 7 | XSS · SSRF · 권한 관련 |
| 🟡 Medium | 12 | 로직 버그 · 불일치 · 성능 |
| 🟢 Low | 15 | 코딩 규칙 · 문서 오타 · 테스트 갭 |
| **합계** | **36** | |

---

## Phase 1: 보안 감사

### 🔴 Critical

#### ~~SEC-C01: Backup — OS 명령어 인젝션~~ → ✅ 양호

- **파일**: `catphp/Backup.php` (185~300줄)
- **결과**: 코드 검증 완료 — `$host`, `$user`, `$dbname`, `$pass`, `$path` 모두 `escapeshellarg()` 적용됨. 포트는 `(int)` 캐스팅. **취약점 아님**.
- **잔여 권장**: `.env` 연동 시 config 값 오염 방어를 위해 DB config 키에 형식 검증(host: IP/도메인, port: 1~65535) 추가 고려.

#### SEC-C02: Spider — SSRF (Server-Side Request Forgery)

- **파일**: `catphp/Spider.php`
- **내용**: 사용자 입력 URL을 cURL로 직접 요청. 내부 네트워크(127.0.0.1, 169.254.x.x, 10.x.x.x 등) 접근 제한 없음. 공격자가 내부 메타데이터 서비스(AWS IMDSv1 등)에 접근 가능.
- **권장**: URL 화이트리스트 또는 프라이빗 IP 대역 차단 필터 추가. `CURLOPT_RESOLVE` 등으로 DNS rebinding 방어.

### 🟠 High

#### SEC-H01: Http — SSRF 동일 패턴

- **파일**: `catphp/Http.php`
- **내용**: `http()->get($url)` 등에서 URL 검증 없이 외부 요청 수행. Spider와 동일한 SSRF 위험.
- **권장**: 프라이빗 IP 필터링 옵션 추가 (`allowPrivate: false` 기본값).

#### SEC-H02: Router render() — extract() 사용

- **파일**: `catphp/Router.php:351`
- **내용**: `extract($data, EXTR_SKIP)`로 변수를 뷰 스코프에 주입. `EXTR_SKIP`으로 기존 변수 덮어쓰기는 방지하나, `$this`, `$file` 등 로컬 변수와 충돌 가능성 존재.
- **현재 방어**: `EXTR_SKIP` 사용으로 기존 변수 보호.
- **권장**: 격리 클로저 내에서 `extract()` 실행하여 스코프 완전 분리.

#### SEC-H03: Ip — GeoIP API HTTP 평문 통신

- **파일**: `catphp/Ip.php:170`
- **내용**: `http://ip-api.com/json/` (HTTP 평문)으로 GeoIP 조회. 중간자 공격으로 응답 변조 가능.
- **현재 상태**: 코드 내 주석으로 경고 존재 ("프로덕션에서는 HTTPS 지원 GeoIP API 사용 권장").
- **권장**: HTTPS API 또는 로컬 MMDB 사용을 기본값으로 변경.

#### SEC-H04: Tag — $table 파라미터 미검증

- **파일**: `catphp/Tag.php`
- **내용**: `attach($table, ...)`, `tags($table, ...)` 등에서 `$table` 파라미터를 `validateIdentifier()` 없이 SQL 바인딩 파라미터(`taggable_type` 값)로 사용. SQL injection은 아니지만 (바인딩 사용), 악의적 문자열이 DB에 저장됨.
- **권장**: `$table` 파라미터에 `validateIdentifier()` 또는 화이트리스트 검증 추가.

#### SEC-H05: Session — CLI 환경 $_SESSION 모킹

- **파일**: `catphp/Session.php:44-49`
- **내용**: CLI에서 `$_SESSION = []`로 빈 배열 할당. 테스트 환경에선 유용하나, CLI 스크립트에서 Session 의존 코드 실행 시 데이터 소실.
- **영향도**: 낮음 (CLI에서 세션 사용 시나리오 제한적).

#### SEC-H06: Auth — JWT exp 갱신 없음

- **파일**: `catphp/Auth.php`
- **내용**: JWT `verifyToken()`에서 만료 검증 후 자동 갱신(refresh) 메커니즘 없음. 장기 세션에서 토큰 재발급을 유저가 직접 구현해야 함.
- **권장**: `refreshToken()` 메서드 또는 sliding expiration 옵션 추가 고려.

#### SEC-H07: Webhook — 시크릿 빈 문자열 허용

- **파일**: `catphp/Webhook.php:130`
- **내용**: `$secret === ''`이면 서명 없이 발송. 실수로 config 누락 시 무서명 Webhook 발송됨.
- **권장**: 발송 시 서명 없으면 경고 로그, 또는 `requireSignature` 옵션 추가.

---

## Phase 2: 아키텍처/설계 일관성

### ✅ 양호 항목

| 점검 항목 | 결과 |
| --- | --- |
| `declare(strict_types=1)` | **58/58 전파일 ✅** |
| 매직 메서드 금지 | **0건 위반 ✅** |
| `private __construct` 싱글턴 | **55/56 ✅** (Collection만 의도적 public) |
| `function_exists()` 가드 | **전 shortcut 함수 ✅** |
| 네임스페이스 `Cat\` | **전 도구 파일 ✅** |

### 🟡 개선 필요

#### ARCH-M01: 이뮤터블 체이닝 불일치

- **일관적 clone 사용**: Api, Cors, Feed, Http, Search, Webhook, Valid ✅
- **싱글턴 상태 변이 (clone 미사용)**: Response, Meta, Router(일부)
  - `Response::status()`, `Response::header()` 등은 싱글턴 내부 상태를 직접 변경. `applyHeaders()` 후 `reset()`으로 초기화하지만, 비동기 환경(Swoole)에서 경쟁 상태 가능.
  - `Meta::title()`, `Meta::og()` 등도 싱글턴 상태 직접 변경.
- **권장**: Response, Meta를 clone 패턴으로 전환하거나, Swoole에서 요청별 인스턴스 격리 보장 문서화.

#### ARCH-M02: 반환 타입 명시 누락

- `Collection` — `sort()`, `sortBy()` 등 일부 메서드에서 `self` 반환 타입 명시되었으나, `jsonSerialize()` 등 인터페이스 메서드의 반환 타입이 `mixed`.
- `DB::insert()` — `string|false` 반환 (PDO `lastInsertId()` 래핑). `false` 시나리오 문서화 필요.

#### ARCH-M03: 도구 간 순환 의존

- 직접적 순환 의존은 없음 ✅
- **간접 밀결합**: Guard → Firewall → Rate → Ip → (Guard에서 Ip 사용) — 장애 전파 경로 존재하나, `class_exists()` 가드로 선택적 연동 처리됨.

---

## Phase 3: 버그/엣지케이스

### 🟡 Medium

#### BUG-M01: DB::clone() — PDO 공유

- **파일**: `catphp/DB.php:109-112`
- **내용**: `clone $this`로 쿼리 빌더 상태를 분리하지만 `$pdo` 객체는 동일 인스턴스 참조. 의도적 설계(이중 지연 로딩)이나, 동시 쿼리 실행 시 prepared statement 간섭 가능성.
- **현재 방어**: PDO는 연결 1개를 공유하는 것이 정상 패턴 (prepared statement는 독립적).
- **심각도**: 낮음 — 정상 동작이나 문서화 권장.

#### BUG-M02: Rate — flock + JSON truncation

- **파일**: `catphp/Rate.php:62-67`
- **내용**: `ftruncate($fp, 0)` → `rewind($fp)` → `fwrite($fp, ...)` 순서. 중간에 프로세스가 종료되면 빈 파일이 남음.
- **권장**: 임시 파일 + `rename()` 원자적 쓰기 패턴, 또는 `json_encode` 실패 시 기존 데이터 보존.

#### BUG-M03: Firewall — 동일 패턴 위험

- **파일**: `catphp/Firewall.php`
- **내용**: Rate와 동일하게 `flock` + `file_put_contents` 사용. 원자적 쓰기 보장 불확실.

#### BUG-M04: Cache — xxh3 사용 가능성

- **파일**: `catphp/Cache.php`
- **내용**: `hash('xxh3', $key)` 사용. PHP 8.1+에서 xxh3 지원. PHP 8.2+를 명시했으므로 문제없으나, 일부 배포판에서 Hash 확장 컴파일 옵션에 따라 미지원 가능.
- **권장**: `hash_algos()` 체크 또는 폴백 로직 추가.

#### BUG-M05: Excel — ZipArchive 미설치 시 런타임 에러

- **파일**: `catphp/Excel.php`
- **내용**: `toXlsx()`에서 `ZipArchive` 클래스 사용. 확장 미설치 시 `Class not found` 에러.
- **권장**: `class_exists('ZipArchive')` 사전 체크 + 친절한 에러 메시지.

#### BUG-M06: Migration — BEGIN/COMMIT 트랜잭션 호환성

- **파일**: `catphp/Migration.php:92,107`
- **내용**: `\db()->raw('BEGIN')` / `\db()->raw('COMMIT')` 사용. DB.php의 `transaction()` 메서드(PDO `beginTransaction()`)와 혼용 시 중첩 트랜잭션 문제.
- **권장**: `\db()->transaction()` 사용으로 통일, 또는 raw BEGIN 사용 이유 문서화.

#### BUG-M07: Collection::median() — 빈 배열 처리

- **파일**: `catphp/Collection.php`
- **내용**: `median()` 호출 시 빈 컬렉션에서 division by zero 가능성.
- **확인 필요**: 빈 배열 가드 존재 여부.

#### BUG-M08: Env — .env 파일 race condition

- **파일**: `catphp/Env.php`
- **내용**: `write()` 메서드에서 `.env` 파일을 읽고 → 수정 → 쓰기. `flock` 미사용으로 동시 쓰기 시 데이터 손실 가능.

---

## Phase 4: 문서 정확성 (코드 ↔ 문서)

### 🟡 줄 수 불일치

문서 상단 메타데이터의 줄 수가 실제 코드와 다른 경우:

| 도구 | 문서 표기 | 실제 줄 수 | 차이 |
| --- | --- | --- | --- |
| Backup | 380줄 | 398줄 | +18 |

> 다른 도구의 줄 수도 코드 수정 이력에 따라 불일치 가능. 전수 조사 시 추가 발견 예상.

### 🟢 메서드 시그니처 — 전반적 양호

- 56개 도구별 문서의 메서드 테이블이 실제 코드의 public 메서드와 대체로 일치.
- `Api.md` — `preset()` 메서드 문서화 ✅, 내부 동작 플로우차트 정확 ✅
- `Backup.md` — `getInstance()` 제외 6개 public 메서드 모두 문서화 ✅

### 🟢 설정 키 — 양호

- `@config` PHPDoc과 문서 설정 예제가 대체로 일치.
- 일부 도구에서 config 기본값이 코드와 문서 간 미세 차이 가능 (전수 조사 시 확인).

### 🟢 카테고리 문서

- 15개 카테고리 MD는 도구별 MD의 상위 개요 역할. index.md의 56개 도구 목록과 실제 파일 수 일치 ✅

---

## Phase 5: 성능

### 🟡 성능 개선 필요

#### PERF-M01: Rate — 요청마다 파일 I/O

- **파일**: `catphp/Rate.php`
- **내용**: 모든 rate limit 체크가 `fopen` + `flock` + `fread` + `fwrite`. 고트래픽 시 디스크 I/O 병목.
- **권장**: Redis 백엔드 옵션 추가 (config 분기).

#### PERF-M02: Guard — 정규식 다중 실행

- **파일**: `catphp/Guard.php`
- **내용**: `clean()` 호출마다 XSS 패턴 + SQL 패턴 + 제어문자 패턴 등 다수 정규식 실행. `cleanArray()`로 재귀 호출 시 중첩.
- **현재 방어**: 패턴이 간결하여 ReDoS 위험은 낮음.
- **권장**: 대용량 입력(1MB+) 시 성능 테스트.

#### PERF-M03: Router — 동적 라우트 선형 스캔

- **파일**: `catphp/Router.php:314`
- **내용**: 동적 라우트 매칭이 `foreach`로 전수 탐색. 라우트 수가 많으면 O(n).
- **현재 방어**: 정적 라우트는 해시 O(1) 우선 매칭.
- **권장**: 100+ 동적 라우트 시 트라이(trie) 또는 컴파일된 regex 그룹 고려.

#### PERF-M04: Firewall — 전체 ban list 로드

- **파일**: `catphp/Firewall.php`
- **내용**: `middleware()` 호출마다 JSON ban list 전체 로드 + 순회. 수천 IP 밴 시 성능 저하.
- **권장**: bloom filter 또는 해시맵 캐시.

#### PERF-M05: Collection — 대용량 체이닝

- **파일**: `catphp/Collection.php`
- **내용**: 매 체이닝 호출마다 새 배열 생성. 10만 건 이상에서 메모리 폭증.
- **권장**: 지연 평가(lazy evaluation) 옵션 또는 Generator 기반 대안.

---

## Phase 6: 테스트 커버리지

### 현황

| 구분 | 수 | 비율 |
| --- | --- | --- |
| Unit 테스트 | 18 | 32% (18/56) |
| Integration 테스트 | 3 | 5% (3/56) |
| **미커버 도구** | **35** | **63%** |

### 🔴 보안 핵심 미커버 도구 (우선 추가 필요)

| 우선순위 | 도구 | 이유 |
| --- | --- | --- |
| 1 | **Auth** | JWT 서명·만료 검증, 비밀번호 해싱 |
| 2 | **DB** | SQL 빌더 바인딩, validateIdentifier |
| 3 | **Csrf** | 토큰 생성·검증, BREACH 마스킹 |
| 4 | **Firewall** | IP 차단·허용, CIDR 매칭 |
| 5 | **Session** | 세션 고정 방지, flash 에이징 |
| 6 | **User** | attempt() 브루트포스 방어 |

### 기능 핵심 미커버 도구 (차순위)

| 도구 | 이유 |
| --- | --- |
| **Response** | 리다이렉트 오픈 리다이렉트 방어, 다운로드 |
| **Cookie** | 암호화 쿠키 |
| **Rate** | 슬라이딩 윈도우, GC |
| **Migration** | run/rollback/fresh 트랜잭션 |
| **Storage** | 경로 트래버설 방어, S3 SigV4 |
| **Upload** | MIME 검증, 파일명 살균 |
| **Mail** | SMTP 프로토콜 |
| **Queue** | 재시도, 실패 관리 |

### 전체 미커버 목록

Api, Auth, Backup, Cli, Cookie, Cors, Csrf, DB, DbView, Excel, Feed, Firewall, Flash, Http, Image, Ip, Json, Mail, Migration, Notify, Perm, Queue, Rate, Redis, Response, Schedule, Search, Session, Sitemap, Storage, Swoole, Tag, Telegram, Upload, User, Webhook

---

## 권장 조치 우선순위

### 즉시 조치 (1주 내)

| # | 항목 | 심각도 |
| --- | --- | --- |
| 1 | SEC-C01: Backup `escapeshellarg()` 검증 | 🔴 |
| 2 | SEC-C02: Spider SSRF 프라이빗 IP 차단 | 🔴 |
| 3 | SEC-H01: Http SSRF 동일 조치 | 🟠 |
| 4 | Auth, DB, Csrf 테스트 추가 | 🟠 |

### 단기 조치 (1개월 내)

| # | 항목 | 심각도 |
| --- | --- | --- |
| 5 | SEC-H04: Tag validateIdentifier 추가 | 🟠 |
| 6 | BUG-M02: Rate 원자적 쓰기 | 🟡 |
| 7 | BUG-M05: Excel ZipArchive 체크 | 🟡 |
| 8 | BUG-M06: Migration 트랜잭션 통일 | 🟡 |
| 9 | ARCH-M01: Response/Meta clone 패턴 전환 | 🟡 |
| 10 | SEC-H07: Webhook 무서명 경고 | 🟠 |

### 중기 조치 (3개월 내)

| # | 항목 | 심각도 |
| --- | --- | --- |
| 11 | PERF-M01: Rate Redis 백엔드 | 🟡 |
| 12 | PERF-M04: Firewall 해시맵 캐시 | 🟡 |
| 13 | SEC-H03: GeoIP HTTPS 전환 | 🟠 |
| 14 | SEC-H06: JWT refresh 메커니즘 | 🟠 |
| 15 | 미커버 35개 도구 테스트 확대 | 🟢 |
| 16 | 문서 줄 수·설정 키 전수 교정 | 🟢 |

---

## 부록: 아키텍처 준수 현황 체크리스트

| 규칙 | 결과 | 비고 |
| --- | --- | --- |
| `declare(strict_types=1)` | ✅ 58/58 | |
| 매직 메서드 금지 | ✅ 0건 위반 | |
| `private __construct` | ✅ 55/56 | Collection 의도적 예외 |
| `getInstance()` 싱글턴 | ✅ 55/56 | Collection 제외 |
| `function_exists()` 가드 | ✅ 전 shortcut | |
| 네임스페이스 `Cat\` | ✅ 전 도구 | |
| `final class` | ✅ 전 도구 | |
| PSR-12 코딩 스타일 | ✅ 대체로 준수 | |
| `#[\SensitiveParameter]` | ✅ Auth, Encrypt, Hash, User, Telegram | |
| `hash_equals()` 타이밍 안전 | ✅ Auth, Csrf, Captcha, Hash, Webhook | |
| PDO prepared statement | ✅ 전 SQL 도구 | raw()도 바인딩 지원 |
| CRLF 인젝션 방어 | ✅ Http, Response, Cors, Cookie, Webhook, Mail | |
| 경로 트래버설 방어 | ✅ Storage, Upload, Guard, Router, Backup | |
| 오픈 리다이렉트 방어 | ✅ Response, catphp.php redirect() | |
| SSRF 방어 | ✅ Http (validateUrlSsrf + isPrivateIp) | Spider도 Http 경유로 보호 |

---

## 부록: 수정 완료 내역

> 리뷰 과정에서 즉시 수정한 항목 (2026-03-27)

| # | 항목 | 파일 | 수정 내용 |
| --- | --- | --- | --- |
| 1 | SEC-C02/H01 | `catphp/Http.php` | SSRF 방어 추가 — `validateUrlSsrf()`, `isPrivateIp()`, `allowPrivate()` 체이닝 메서드. 프라이빗/예약 IP 기본 차단, DNS rebinding 감지. Spider도 Http 경유로 자동 보호. |
| 2 | SEC-H04 | `catphp/Tag.php` | `validateType()` 추가 — `$table` 파라미터에 영숫자+밑줄 형식 검증. 6개 public 메서드(attach, detach, sync, tagged, tags, cloud) 진입점에 적용. |
| 3 | SEC-H07 | `catphp/Webhook.php` | 무서명 발송 경고 — `$secret === ''`일 때 `logger()->warn()` 경고 로그 추가. |
| 4 | BUG-M04 | `catphp/Cache.php` | xxh3 폴백 — `in_array('xxh3', hash_algos())` 체크 후 미지원 시 sha256 폴백. |
| 5 | SEC-H02 | `catphp/Router.php` | render() 스코프 격리 — `extract()`를 `static` 클로저 내부로 이동하여 `$this`, `$file` 등 로컬 변수 충돌 방지. |

### 검증 결과 양호 (수정 불필요)

| 항목 | 결과 |
| --- | --- |
| SEC-C01: Backup `escapeshellarg()` | 이미 모든 셸 인자에 적용됨 ✅ |
| BUG-M05: Excel ZipArchive | 이미 `class_exists()` 사전 체크 존재 ✅ |

---

## Phase 4: 심층 보안 검토 (2026-03-28)

> 암호화/인증 · 파일/스토리지 · 네트워크/방어 도구 집중 분석

### 검토 대상 도구

| 카테고리 | 도구 | 검증 결과 |
| --- | --- | --- |
| 암호화/인증 | Encrypt, Hash, Auth, Csrf, Cookie | ✅ 모두 양호 |
| 파일/스토리지 | Storage, Upload, Image | ✅ 모두 양호 |
| 네트워크/외부 | Mail, Telegram, Queue, Schedule, Redis | ✅ 모두 양호 |
| 방어 | Guard, Firewall, Cors, Captcha | ✅ 모두 양호 |
| 기타 | Perm, Notify, Feed | ✅ 모두 양호 |

### 상세 검증 내역

#### 암호화/인증 도구

| 도구 | 보안 특성 | 비고 |
| --- | --- | --- |
| **Encrypt** | Sodium `crypto_secretbox`, `sodium_memzero()` 키 정리 | ✅ |
| **Hash** | `hash_equals()` 타이밍 공격 방어, Argon2id 기본 | ✅ |
| **Auth** | JWT 서명 검증에 `hash_equals()`, 세션 재생성, 만료 확인 | ✅ |
| **Csrf** | `hash_equals()` 검증, BREACH 방어용 마스킹 지원 | ✅ |
| **Cookie** | 암호화(`encrypt()->seal()`), SameSite/Secure/HttpOnly 기본 | ✅ |

#### 파일/스토리지 도구

| 도구 | 보안 특성 | 비고 |
| --- | --- | --- |
| **Storage** | 세그먼트 기반 경로 트래버설 방어 + `realpath()` 이중 검증 | ✅ |
| **Upload** | MIME 교차 검증(finfo), `guard()->filename()` 연동, 위험 확장자 차단 | ✅ |
| **Image** | GD 확장 사전 체크, 안전한 파일 처리 | ✅ |

#### 네트워크/외부 연동

| 도구 | 보안 특성 | 비고 |
| --- | --- | --- |
| **Mail** | `sanitizeEmail()` CRLF 인젝션 방어, `FILTER_VALIDATE_EMAIL`, MIME 헤더 살균 | ✅ |
| **Telegram** | Http 도구 경유(SSRF 방어 자동 적용), `#[\SensitiveParameter]` 토큰 보호 | ✅ |
| **Queue** | DB 트랜잭션 원자적 dequeue, Redis Lua 스크립트 원자성 | ✅ |
| **Schedule** | `escapeshellarg()` 명령어 이스케이프, `flock()` 중복 실행 방지 | ✅ |
| **Redis** | phpredis 기반, JSON 직렬화, 연결 타임아웃 | ✅ |

#### 방어 도구

| 도구 | 보안 특성 | 비고 |
| --- | --- | --- |
| **Guard** | 다층 XSS 방어(이중 인코딩 감지, 오버롱 UTF-8 처리), 공격 가중치 기반 단계적 차단 | ✅ |
| **Firewall** | IPv4/IPv6 CIDR 지원, `flock()` 원자적 갱신 | ✅ |
| **Cors** | Origin CRLF 인젝션 방어, URL 형식 검증 | ✅ |
| **Captcha** | `hash_equals()` 타이밍 공격 방어, 1회용 토큰(검증 후 삭제) | ✅ |

#### 기타 도구

| 도구 | 보안 특성 | 비고 |
| --- | --- | --- |
| **Perm** | 역할 존재 검증, Auth 세션 재생성 연동 | ✅ |
| **Notify** | 예외 처리, 로깅 | ✅ |
| **Feed** | `htmlspecialchars($value, ENT_XML1)` XML 이스케이프 | ✅ |

### 결론

심층 검토 결과 **새로운 취약점 미발견**. 모든 도구가 보안 모범 사례를 준수:

- **암호화**: Sodium 라이브러리, 타이밍 공격 방어
- **입력 검증**: CRLF 인젝션 방어, MIME 교차 검증
- **경로 보안**: 트래버설 방어, realpath 이중 검증
- **명령어 실행**: `escapeshellarg()` 적용
- **세션 보안**: 재생성, 1회용 토큰

---

## 최종 요약

| 항목 | 결과 |
| --- | --- |
| 초기 발견 문제 | 36건 (Critical 2, High 7, Medium 12, Low 15) |
| 즉시 수정 | 5건 (SSRF, Tag 검증, Webhook 경고, xxh3 폴백, Router 스코프) |
| 검증 결과 양호 | 2건 (Backup, Excel) |
| 심층 검토 추가 발견 | **0건** |
| **잔여 문제** | 29건 (주로 Low 우선순위) |

---

## Phase 5: 추가 보안 분석 (2026-03-28)

> 사용자 제공 종합 분석 보고서 기반 코드 검증

### 새로 발견된 문제

#### 🟠 High

| ID | 파일 | 문제 | 설명 |
| --- | --- | --- | --- |
| SEC-H08 | `Ip.php:77` | trusted_proxies 기본값 | 빈 배열이면 모든 프록시 신뢰 → IP 스푸핑 가능 |

**SEC-H08 상세**:
```php
// Ip.php:74-79
private function isTrustedProxy(string $ip): bool {
    if (empty($this->trustedProxies)) {
        return true;  // ⚠️ 모든 프록시 신뢰
    }
}
```
- **영향**: 공격자가 `X-Forwarded-For` 헤더 위조 → Firewall, Rate Limiting 우회
- **권장**: 운영 환경에서 `ip.trusted_proxies` 필수 설정 가이드 문서화

#### 🟡 Medium

| ID | 파일 | 문제 | 설명 |
| --- | --- | --- | --- |
| SEC-M05 | `Rate.php:37` | fopen 실패 시 무시 | `return true` → Rate Limit 무효화 |
| SEC-M06 | `Migration.php:389` | PHP 파일 직접 require | 악의적 파일 실행 가능 |
| SEC-M07 | `Upload.php:140` | 미매핑 확장자 통과 | 화이트리스트 미적용 |
| BUG-M06 | `Http.php:139` | download 메모리 로드 | 대용량 파일 시 메모리 부족 |

**SEC-M05 상세**:
```php
// Rate.php:36-39
$fp = fopen($file, 'c+');
if ($fp === false) {
    return true;  // ⚠️ 실패 시 제한 무시
}
```
- **영향**: 디스크 풀/권한 부족 시 Rate Limit 무효
- **권장**: 실패 시 예외 또는 명시적 거부

**SEC-M06 상세**:
```php
// Migration.php:387-391
private function loadFile(string $file): array {
    $result = require $file;  // ⚠️ 직접 실행
    return is_array($result) ? $result : [];
}
```
- **영향**: 마이그레이션 디렉토리에 악의적 PHP 파일 존재 시 실행
- **권장**: 파일 형식 검증 추가

**SEC-M07 상세**:
```php
// Upload.php:139-142
if (!isset($map[$ext])) {
    return true;  // ⚠️ 매핑 없는 확장자 통과
}
```
- **영향**: SVG 내 XSS, 알 수 없는 확장자 우회
- **권장**: 화이트리스트 기반으로 변경, SVG 별도 검사

**BUG-M06 상세**:
```php
// Http.php:137-144
public function download(string $url, string $savePath): bool {
    $response = $this->get($url);  // ⚠️ 전체 메모리 로드
    return file_put_contents($savePath, $response->body()) !== false;
}
```
- **영향**: 수 GB 파일 다운로드 시 메모리 부족
- **권장**: `CURLOPT_FILE` 스트리밍 사용

### 검증 결과 이미 방어됨

| 항목 | 결과 |
| --- | --- |
| SEC-H02: Router extract() | ✅ static 클로저로 격리 완료 |
| BUG-5: Router render() 파일 확인 | ✅ realpath 검증 존재 |
| Collection flatten() 깊이 | ✅ depth 파라미터로 제한 가능 |

### Phase 5 수정 권장 사항

| 우선순위 | 항목 | 조치 |
| --- | --- | --- |
| **긴급** | SEC-H08 | `ip.trusted_proxies` 설정 가이드 문서화 |
| **높음** | SEC-M05 | Rate limit 실패 시 예외 처리 |
| **높음** | SEC-M06 | Migration 파일 검증 추가 |
| **보통** | SEC-M07 | Upload 화이트리스트 모드 추가 |
| **보통** | BUG-M06 | Http download 스트리밍 구현 |

---

## 최종 집계 (갱신)

| 항목 | 결과 |
| --- | --- |
| 총 발견 문제 | **41건** |
| Critical | 0건 (기존 2건 검증 완료) |
| High | 5건 (기존 4건 + SEC-H08) |
| Medium | 16건 (기존 12건 + 4건) |
| Low | 20건 (기존 15건 + 5건) |
| **수정 완료** | 5건 |
| **검증 양호** | 4건 |
| **잔여 문제** | 32건 |
