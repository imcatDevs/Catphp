# CatPHP 프레임워크

PHP 8.1+ 호환 경량 프레임워크. 4대 원칙: **빠른 속도 · 사용 편리 · 쉬운 학습 · 보안**

## 아키텍처

- `catphp/catphp.php` — 단일 코어 (autoloader + config + shortcuts)
- `catphp/*.php` — 플랫 도구 파일 (`Cat\X` → `catphp/X.php`)
- `Public/index.php` — 웹 + API 진입점
- `config/app.php` — 유저 설정
- `cli.php` — CLI 진입점

## 폴더 역할

| 폴더 | 역할 | 편집 주체 |
| --- | --- | --- |
| `catphp/` | 프레임워크 코어 + 도구 | 프레임워크 개발자 |
| `Public/` | 웹 진입점 + 라우트 | 유저 |
| `config/` | 설정 파일 | 유저 |
| `views/` | PHP 템플릿 | 유저 |
| `lang/` | 다국어 번역 | 유저 |
| `storage/` | 런타임 데이터 (자동 생성) | 시스템 |

## 규칙

- 한국어 주석/문서 작성
- `declare(strict_types=1)` 필수
- 매직 메서드 사용 금지
