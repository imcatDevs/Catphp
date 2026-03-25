# Csrf — CSRF 보호

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Csrf` |
| 파일 | `catphp/Csrf.php` (79줄) |
| Shortcut | `csrf()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Session` (토큰 저장), `Cat\Json` (API 에러 응답) |

---

## 설정

별도 config 없음. 세션에 `_csrf_token` 키로 토큰을 저장한다.

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `token` | `token(): string` | `string` | CSRF 토큰 반환 (없으면 64자 hex 생성) |
| `field` | `field(): string` | `string` | `<input type="hidden">` HTML 출력 |
| `verify` | `verify(): bool` | `bool` | 토큰 검증 (GET/HEAD/OPTIONS는 자동 통과) |
| `middleware` | `middleware(): callable` | `callable` | 라우터 미들웨어 (실패 시 403) |

---

## 사용 예제

### 폼에 토큰 삽입

```php
<form method="POST" action="/posts">
    <?= csrf()->field() ?>
    <!-- → <input type="hidden" name="_csrf_token" value="a1b2c3..."> -->
    <input type="text" name="title">
    <button type="submit">작성</button>
</form>
```

### 수동 검증

```php
router()->post('/posts', function () {
    if (!csrf()->verify()) {
        response()->status(403)->html('CSRF 토큰 불일치');
    }
    // 정상 처리...
});
```

### 미들웨어 등록

```php
// 전역 미들웨어 (모든 POST/PUT/DELETE에 자동 적용)
router()->use(csrf()->middleware());
```

### AJAX 요청

```php
// JavaScript에서 헤더로 전송
fetch('/api/posts', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': '<?= csrf()->token() ?>',
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({ title: '제목' }),
});
```

---

## 내부 동작

### 토큰 생성

```text
token()
├─ session('_csrf_token') 존재? → 반환
└─ 없으면 → bin2hex(random_bytes(32)) → 64자 hex 토큰
   └─ session()->set('_csrf_token', $token)
```

### 검증 흐름

```text
verify()
├─ GET/HEAD/OPTIONS → true (검증 불필요)
├─ 세션에 토큰 없음 → false
├─ $_POST['_csrf_token'] 또는 X-CSRF-TOKEN 헤더에서 토큰 추출
└─ hash_equals($expected, $token) → 타이밍 안전 비교
```

### 미들웨어 동작

검증 실패 시:

- **API 경로** (`/api/`로 시작): `json()->error('CSRF token mismatch', 403)`
- **웹 경로**: HTML 403 페이지 + exit

---

## 보안 고려사항

- **`random_bytes(32)`**: 암호학적으로 안전한 32바이트(256비트) 난수 → 64자 hex
- **`hash_equals()`**: 타이밍 공격 방지를 위한 상수 시간 비교
- **`htmlspecialchars()`**: `field()` 출력에서 토큰 값 이스케이프 (XSS 방어)
- **GET/HEAD/OPTIONS 제외**: 부수효과 없는 안전 메서드는 검증 건너뜀

---

## 주의사항

1. **세션 의존**: CSRF 토큰은 세션에 저장되므로, 세션이 비활성화된 환경(순수 API 서버)에서는 작동하지 않는다.

2. **SPA 환경**: 서버 렌더링 없이 SPA만 사용하는 경우, 초기 페이지 로드 시 토큰을 meta 태그나 쿠키로 전달해야 한다.

3. **토큰 재사용**: 같은 세션 동안 동일 토큰이 유지된다 (요청마다 갱신되지 않음). 보안이 극도로 중요한 경우 요청마다 토큰 갱신 로직 추가 필요.

4. **API 미들웨어 충돌**: JWT 기반 API에서는 CSRF 보호가 불필요하다 (Authorization 헤더 자체가 CSRF 방어). API 라우트에서는 CSRF 미들웨어를 제외하는 것이 일반적.

---

## 연관 도구

- [Session](Session.md) — 토큰 저장소
- [Auth](Auth.md) — 인증 (세션 + JWT)
- [Guard](Guard.md) — XSS 살균 (폼 입력 보호)
- [Router](Router.md) — 미들웨어 등록
