# Captcha — 캡차 생성/검증

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Captcha` |
| 파일 | `catphp/Captcha.php` (253줄) |
| Shortcut | `captcha()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Session` (정답 저장) |
| 의존 확장 | `ext-gd` |

---

## 설정

```php
// config/app.php
'captcha' => [
    'width'       => 150,      // 이미지 너비
    'height'      => 50,       // 이미지 높이
    'length'      => 5,        // 코드 길이
    'charset'     => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',  // 사용 문자
    'session_key' => '_captcha',
],
```

---

## 메서드 레퍼런스

### 이미지 캡차

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `image` | `image(): never` | `never` | PNG 이미지 직접 출력 |
| `src` | `src(): string` | `string` | base64 data URI |
| `html` | `html(string $id = 'captcha', string $refreshUrl = ''): string` | `string` | `<img>` 태그 반환 |

### 수학 캡차

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `math` | `math(): array` | `array` | `{question, html}` |

### 검증

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `verify` | `verify(string $input): bool` | `bool` | 사용자 입력 검증 (1회용) |

### 설정 오버라이드 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `size` | `size(int $width, int $height): self` | `self` | 이미지 크기 |
| `length` | `length(int $length): self` | `self` | 코드 길이 |

---

## 사용 예제

### 이미지 캡차 사용

```php
// 캡차 이미지 라우트
router()->get('/captcha', function () {
    captcha()->image();  // PNG 출력 + exit
});

// 폼에서 사용
<img src="/captcha" onclick="this.src='/captcha?'+Date.now()" style="cursor:pointer;">
<input name="captcha" placeholder="캡차 입력">
```

### base64 캡차 (SPA용)

```php
// API 엔드포인트
router()->get('/api/captcha', function () {
    json()->ok(['src' => captcha()->src()]);
});

// 또는 HTML 태그
echo captcha()->html('captcha-img', '/api/captcha/refresh');
```

### 수학 캡차 사용

```php
$captcha = captcha()->math();
echo $captcha['html'];  // '15 + 7 = ?'
// 세션에 정답(22) 자동 저장
```

### 입력 검증

```php
router()->post('/register', function () {
    if (!captcha()->verify(input('captcha'))) {
        flash()->error('캡차가 올바르지 않습니다.');
        response()->back();
    }
    // 회원가입 처리...
});
```

### 커스텀 크기

```php
$src = captcha()->size(200, 60)->length(6)->src();
```

---

## 내부 동작

### 코드 생성

```text
generateCode()
├─ charset에서 random_int()로 length개 선택
├─ 혼동 문자 제외: 0, O, 1, I, l (기본 charset)
└─ session()->set('_captcha', $code)
```

### 이미지 생성

```text
createImage($code)
├─ imagecreatetruecolor(width, height)
├─ 밝은 배경색 (랜덤)
├─ 노이즈 라인 6개
├─ 노이즈 점 100개
├─ 텍스트: 문자별 랜덤 위치 + 랜덤 색상
└─ GD 내장 폰트 (크기 5)
```

### verify() 흐름

```text
verify($input)
├─ session()->get('_captcha') → 저장된 정답
├─ hash_equals(strtolower($stored), strtolower($input))
├─ session()->forget('_captcha') → 1회용 삭제
└─ 결과 반환
```

---

## 보안 고려사항

- **`hash_equals()`**: 타이밍 안전 비교 — 문자 단위 비교 시간 차이 공격 방지
- **1회용**: `verify()` 호출 시 세션에서 정답 즉시 삭제 — 재사용 방지
- **`random_int()`**: 암호학적으로 안전한 난수 — 코드 예측 불가
- **캐시 방지**: `image()` 출력 시 `Cache-Control: no-store, no-cache` 헤더

---

## 주의사항

1. **ext-gd 필수**: GD 확장 없으면 인스턴스 생성 시 `RuntimeException`.
2. **세션 의존**: 정답이 세션에 저장되므로 `session()` 도구가 활성이어야 한다.
3. **GD 내장 폰트**: TrueType 폰트 미지원. 한글 캡차 불가 (영숫자만).
4. **image()는 never**: 내부에서 `exit` 호출.
5. **수학 캡차 음수 방지**: 빼기 연산 시 큰 수에서 작은 수를 빼도록 자동 교환.

---

## 연관 도구

- [Session](Session.md) — 캡차 정답 저장
- [Guard](Guard.md) — 폼 입력 살균
- [Csrf](Csrf.md) — CSRF 토큰 (캡차와 병행 사용 권장)
