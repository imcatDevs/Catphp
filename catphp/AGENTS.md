# catphp/ 코어 편집 지침

이 디렉토리는 CatPHP 프레임워크의 코어와 도구 파일을 포함한다.

## 도구 파일 작성 규약

- 네임스페이스: `Cat\ClassName`
- `final class` + `private constructor` + `static getInstance()` 싱글턴
- 생성자: named args + readonly config
- 이중 지연 로딩: 객체 생성 시 리소스 연결하지 않음
- 클래스 PHPDoc에 `@config` 태그로 `config/app.php` 키 명시 필수

## autoloader 경로 규칙

- `Cat\X` → `catphp/X.php` (플랫 매핑, 중첩 없음)
- 파일명 = 클래스명 (PascalCase)

## 수정 시 주의

- Shortcut 함수 추가/변경 시 `catphp.php`도 함께 수정
- `function_exists()` 가드 필수
- 도구 간 순환 의존 금지
