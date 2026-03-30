# CHANGELOG

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
