# CHANGELOG

## [v1.0.4] — 2026-03-28

- v1.0.4 릴리즈


## [v1.0.3] — 2026-03-27

- v1.0.3 릴리즈


## [v1.0.4] — 2026-03-27

### Fixed

- **Guard**: `path()` — 인코딩 우회 취약점 전면 재설계: 완전 수렴 디코딩(최대 10회), 오버롱 바이너리 직접 제거, 구분자 정규화 후 반복 치환 + 세그먼트 최종 검증 6단계 파이프라인
- **Guard**: `xssClean()` — 비공백 제어문자(`\x01`~`\x1F`) 제거 추가: `java\x08script:` 등 프로토콜 삽입 우회 방어
- **Request**: `host()` — 포트 번호 범위 검증(1–65535) 추가: `localhost:99999` 등 잘못된 포트 차단
- **Log**: `write()` — 로그 인젝션 방어에 탭 문자(`\t`) 필터 추가
- **Auth**: `getInstance()` — HMAC 시크릿 32바이트 미만 시 경고 로그 추가 (브루트포스 방어)
- **Router**: `dispatch()` — `_method` 오버라이드에 허용 HTTP 메서드 화이트리스트 추가
- **Upload**: `save()` — finfo MIME 탐지 실패 시 업로드 거부로 변경 (기존: 검증 건너뜀)
- **Backup**: `execCommand()` — 에러 메시지에서 DB 자격 증명 노출 방지 (상세 에러는 로그에만 기록)

## [v1.0.3] — 2026-03-26

### Fixed

- **Guard**: `path()` — `test/..` 등 슬래시 없는 `..` 세그먼트 경로 우회 방어 추가
- **Perm**: `assign()` — 미등록 역할 할당 시 `InvalidArgumentException` 발생하도록 검증 추가
- **Meta**: `render()` — OG/Twitter 태그의 property/name 속성명에 `htmlspecialchars` 적용 (XSS 방어)
- **Debug**: `dd()` — 호출 위치 표시가 grandparent 프레임을 가리키던 버그 수정 (frame[0]으로 변경)
- **User**: `sanitizeInput()` PHPDoc 위치 정리

## [v1.0.2] — 2026-03-24

- v1.0.2 릴리즈

## [v1.0.1] — 2026-03-23

### Removed

- 1
