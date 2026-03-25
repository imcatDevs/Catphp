# Cookie — 쿠키 관리

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Cookie` |
| 파일 | `catphp/Cookie.php` (99줄) |
| Shortcut | `cookie()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Encrypt` (암호화), `Cat\Guard` (살균) |

---

## 설정

```php
// config/app.php
'cookie' => [
    'encrypt'  => true,    // 암호화 여부 (기본 true)
    'samesite' => 'Lax',   // SameSite 속성: Lax | Strict | None
    'secure'   => false,   // HTTPS 전용 (운영 환경에서 true 권장)
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `get` | `get(string $name, mixed $default = null): mixed` | `mixed` | 쿠키 읽기 (복호화 + 살균) |
| `set` | `set(string $name, mixed $value, int $ttl = 86400): bool` | `bool` | 쿠키 설정 (암호화) |
| `del` | `del(string $name): bool` | `bool` | 쿠키 삭제 |
| `has` | `has(string $name): bool` | `bool` | 쿠키 존재 확인 |

---

## 사용 예제

### 기본 사용

```php
// 쿠키 설정 (기본 24시간, 암호화)
cookie()->set('theme', 'dark');
cookie()->set('lang', 'ko', 86400 * 365);  // 1년

// 읽기 (자동 복호화 + XSS 살균)
$theme = cookie()->get('theme', 'light');

// 삭제
cookie()->del('theme');

// 존재 확인
if (cookie()->has('theme')) { ... }
```

### 배열/객체 저장

```php
// 배열도 저장 가능 (JSON 직렬화)
cookie()->set('preferences', ['font_size' => 16, 'color' => 'blue']);

// 읽기 시 자동 역직렬화
$prefs = cookie()->get('preferences');
// → ['font_size' => 16, 'color' => 'blue']
```

### 암호화 비활성화

```php
// config: cookie.encrypt = false
// 평문 쿠키 (서명만 필요한 경우 등)
cookie()->set('visitor_id', 'abc123');
```

---

## 내부 동작

### set() 흐름

```text
set('theme', 'dark', 86400)
├─ 값 직렬화: 문자열 그대로 / 배열은 json_encode
├─ encrypt=true → encrypt()->seal($raw)
│   └─ Sodium 암호화 → base64 문자열
└─ setcookie($name, $encrypted, [
       'expires'  => time() + 86400,
       'path'     => '/',
       'secure'   => $this->secure,
       'httponly'  => true,
       'samesite' => 'Lax',
   ])
```

### get() 흐름

```text
get('theme')
├─ $_COOKIE['theme'] 읽기
├─ encrypt=true → encrypt()->open($raw)
│   ├─ 복호화 성공 → JSON 디코딩 시도
│   │   └─ 배열이면 배열, 아니면 문자열
│   └─ 복호화 실패 → $default (변조된 쿠키)
├─ Guard 살균:
│   ├─ 문자열 → guard()->clean()
│   └─ 배열 → guard()->cleanArray()
└─ 결과 반환
```

---

## 보안 고려사항

### 자동 암호화

기본적으로 모든 쿠키가 Sodium으로 암호화된다. 클라이언트에서 쿠키 값을 읽거나 변조할 수 없다.

### 자동 살균

`get()` 시 `guard()->clean()` / `guard()->cleanArray()` 적용 — 쿠키를 통한 XSS 공격 차단.

### 변조 감지

암호화된 쿠키가 변조되면 `encrypt()->open()` 실패 → `null` 반환 → `$default` 반환. Sodium의 Poly1305 MAC이 무결성을 보장.

### HttpOnly

`httponly: true` — JavaScript에서 `document.cookie`로 접근 불가. XSS를 통한 쿠키 탈취 방지.

---

## 주의사항

1. **encrypt.key 필수**: `encrypt=true` 시 `config('encrypt.key')` 설정 필요. 없으면 `RuntimeException`.

2. **쿠키 크기 제한**: 브라우저별 쿠키 크기 제한(약 4KB). 암호화 + base64 인코딩으로 원본보다 커지므로 대용량 데이터는 세션 사용 권장.

3. **암호화 키 변경**: 키 변경 시 기존 쿠키 복호화 실패 → `$default` 반환. 사용자 재설정 필요.

4. **del() 동작**: `$_COOKIE`에서 제거 + 과거 만료 쿠키 전송. 같은 요청 내에서는 `$_COOKIE`가 갱신되지 않을 수 있다.

5. **path**: 모든 쿠키가 `/` 경로에 설정된다. 경로별 쿠키가 필요하면 `response()->cookie()` 사용.

---

## 연관 도구

- [Encrypt](Encrypt.md) — Sodium 암호화 (쿠키 암호화 내부 사용)
- [Guard](Guard.md) — XSS 살균 (쿠키 읽기 시 자동 적용)
- [Session](Session.md) — 서버 측 데이터 저장 (세션 쿠키)
- [Response](Response.md) — `response()->cookie()` 저수준 쿠키 설정
