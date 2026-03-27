# CHANGELOG

## [v1.0.4] — 2026-03-27

### Fixed

- **Guard**: `path()` — 인코딩 우회 취약점 전면 재설계: 완전 수렴 디코딩(최대 10회), 오버롱 바이너리 직접 제거, 구분자 정규화 후 반복 치환 + 세그먼트 최종 검증 6단계 파이프라인

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
