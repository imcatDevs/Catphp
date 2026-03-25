# Public/ 유저 코드 지침

이 디렉토리는 웹 진입점과 유저 라우트 코드를 포함한다.

## require 패턴

```php
<?php declare(strict_types=1);
require __DIR__ . '/../catphp/catphp.php';
config(require __DIR__ . '/../config/app.php');
errors(config('app.debug'));
```

## 라우터 사용

- `router()->get()`, `post()`, `put()`, `delete()` 등으로 라우트 등록
- `router()->group('/prefix', fn() => ...)` 그룹 라우트
- `router()->dispatch()` 파일 끝에서 한 번만 호출

## API 개발

- `api()->cors()->rateLimit()->auth()->guard()->apply()` 미들웨어 적용
- `json()->ok()` / `json()->fail()` 통일된 JSON 응답

## 보안 주의

- `catphp/`, `config/` 디렉토리는 웹에서 직접 접근 불가해야 함
- DocumentRoot는 반드시 `Public/` 디렉토리로 설정
- 사용자 입력은 `guard()` 또는 `valid()`로 검증
