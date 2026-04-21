# CHANGELOG

## [v1.1.0] — 2026-04-21

### Added — Swoole 상주 프로세스 지원

- **`server.php`**: Swoole 전용 진입점 신설 (FPM 대비 6~15배 성능)
- **`routes/swoole.php`**: 라우트 정의 파일 (Public/index.php 에서 이관)
- **`catphp/SwooleSent.php`**: Swoole 컨텍스트 정상 종료 신호 예외
- **Session 다중 드라이버**: `native` / `redis` / `memory` — Swoole 자동 감지
  - Flash/Csrf 는 `session()` API 만 사용하므로 무변경 호환
  - Redis 드라이버는 요청 종료 시 자동 flush
- **싱글턴 resetInstance()**: Response/Request/Meta/Auth/Sitemap/Session
  - Swoole 요청 간 상태 누수 차단 (SEO/응답헤더/인증 우회 방지)
- **Response/Json 직접 출력**: Swoole 컨텍스트에서 `$res->end()` 호출로 출력 버퍼 우회
- **Swoole 브리지 개선**: WorkerStart 코루틴 래핑, coroutine context 주입, task 워커 분기
- **배포 자동화**: `deploy/` — remote-deploy.sh, patch-*.php, systemd unit, nginx conf

### Changed

- **호환성**: 최소 요구 PHP 버전을 `8.2`에서 **`8.1`** 로 하향 (PHP 8.1/8.2/8.3/8.4 전부 지원)
- **cli.php**: `check:env` 명령의 PHP 버전 기준이 `8.1.0` 이상으로 변경
- **config/app.php**: `session.driver` 옵션 추가 (기본 `native`), `swoole.pool` 기본값

### Performance — catphp.imcat.dev 프로덕션 벤치마크

- 정적 HTML: 500 → **3,113 RPS** (6.2배)
- DB 쿼리 API: 200 → **3,069 RPS** (15.3배)
- 8 프로세스로 ~226 MB 메모리 (상주)

### Removed

- **Hash/Auth/Encrypt/Telegram/User**: PHP 8.2 전용 `#[\SensitiveParameter]` 속성 제거 (총 10곳)
  - 해싱/암호화 기능 동작은 완전히 동일 (API 변경 없음)
  - 운영 환경 `display_errors=Off` 시 영향 없음

## [v1.0.8] — 2026-03-30

- v1.0.8 릴리즈


## [v1.0.7] — 2026-03-29

### Security

- **Auth**: 운영환경(`debug=false`)에서 `auth.secret` 32바이트 미만 시 `RuntimeException` 부팅 차단 (기존: 경고만)
- **Auth**: `password_algos()` 런타임 검증 — argon2 미지원 PHP에서 bcrypt 자동 폴백 + 경고 로그
- **Csrf**: 토큰 검증 실패 시 원인별 로깅 강화 (세션 없음 / 토큰 누락 / 불일치 + IP/URI/Method)
- **Http**: `allowPrivate()` SSRF 방어 해제 시 감사 로그 추가
- **Guard**: XSS 필터 확장 — `noscript`/`template`/`slot`/`xmp`/`annotation-xml`/`picture` 태그 + CSS `behavior`/`@import` 방어
- **Session**: 운영환경 쿠키 보안 검증 (secure+HTTPS, httponly, samesite+secure 조합)
- **Cors**: 운영환경 wildcard `*` origin 경고
- **Redis**: `flush()` 운영환경 차단, `keys()` 운영환경 경고 로그
- **Swoole**: API 라우트(`/api/`) 에러 응답을 CatUI JSON 포맷으로 통일

### Fixed

- **Env**: `write()` Windows mandatory file lock 버그 수정 — `file()` 대신 `stream_get_contents()` 사용
- **Search**: PgSQL `tsvector` NULL 전파 버그 수정 — `COALESCE` 래핑

### Added

- **Guard 테스트**: XSS 회귀테스트 12건 + 경로탐색 회귀테스트 6건 추가 (287개 전체 통과)

## [v1.0.6] — 2026-03-28

- v1.0.6 릴리즈

## [v1.0.5] — 2026-03-28

- v1.0.5 릴리즈

## [v1.0.4] — 2026-03-28

### Fixed

- **Guard**: `path()` — 인코딩 우회 취약점 전면 재설계: 완전 수렴 디코딩(최대 10회), 오버롱 바이너리 직접 제거, 구분자 정규화 후 반복 치환 + 세그먼트 최종 검증 6단계 파이프라인
- **Guard**: `xssClean()` — 비공백 제어문자(`\x01`~`\x1F`) 제거 추가: `java\x08script:` 등 프로토콜 삽입 우회 방어
- **Request**: `host()` — 포트 번호 범위 검증(1–65535) 추가: `localhost:99999` 등 잘못된 포트 차단
- **Log**: `write()` — 로그 인젝션 방어에 탭 문자(`\t`) 필터 추가
- **Auth**: `getInstance()` — HMAC 시크릿 32바이트 미만 시 경고 로그 추가 (브루트포스 방어)
- **Router**: `dispatch()` — `_method` 오버라이드에 허용 HTTP 메서드 화이트리스트 추가
- **Upload**: `save()` — finfo MIME 탐지 실패 시 업로드 거부로 변경 (기존: 검증 건너뜀)
- **Backup**: `execCommand()` — 에러 메시지에서 DB 자격 증명 노출 방지 (상세 에러는 로그에만 기록)

## [v1.0.3] — 2026-03-27

### Fixed

- **Guard**: `path()` — `test/..` 등 슬래시 없는 `..` 세그먼트 경로 우회 방어 추가
- **Perm**: `assign()` — 미등록 역할 할당 시 `InvalidArgumentException` 발생하도록 검증 추가
- **Meta**: `render()` — OG/Twitter 태그의 property/name 속성명에 `htmlspecialchars` 적용 (XSS 방어)
- **Debug**: `dd()` — 호출 위치 표시가 grandparent 프레임을 가리키던 버그 수정 (frame[0]으로 변경)
- **User**: `sanitizeInput()` PHPDoc 위치 정리

## [v1.0.2] — 2026-03-24

- v1.0.2 릴리즈

## [v1.0.1] — 2026-03-23

- v1.0.1 릴리즈
