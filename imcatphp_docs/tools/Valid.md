# Valid — 입력 검증

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Valid` |
| 파일 | `catphp/Valid.php` (263줄) |
| Shortcut | `valid()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\DB` (`unique` 규칙), `Cat\Log` (미등록 규칙 경고) |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `rules` | `rules(array $rules): self` | `self` | 검증 규칙 설정 (이뮤터블) |
| `check` | `check(array $data): self` | `self` | 검증 실행 (이뮤터블) |
| `fails` | `fails(): bool` | `bool` | 검증 실패 여부 |
| `errors` | `errors(): array` | `array` | 에러 목록 `['field' => ['msg1', ...]]` |
| `extend` | `extend(string $name, callable $callback): void` | `void` | 커스텀 규칙 등록 (static) |

---

## 내장 규칙

| 규칙 | 형식 | 설명 |
| --- | --- | --- |
| `required` | `required` | 필수 (`null`, `''` 금지) |
| `nullable` | `nullable` | null/빈 값이면 나머지 규칙 건너뛰기 |
| `email` | `email` | 유효한 이메일 |
| `min` | `min:N` | 문자열 최소 N자 / 숫자 최소값 |
| `max` | `max:N` | 문자열 최대 N자 / 숫자 최대값 |
| `between` | `between:N,M` | 범위 (문자열 길이 또는 숫자) |
| `in` | `in:a,b,c` | 허용 값 목록 |
| `numeric` | `numeric` | 숫자 |
| `integer` | `integer` | 정수 |
| `string` | `string` | 문자열 |
| `array` | `array` | 배열 |
| `alpha` | `alpha` | 알파벳만 (유니코드 포함) |
| `alpha_num` | `alpha_num` | 알파벳+숫자 (유니코드 포함) |
| `digits` | `digits:N` | 정확히 N자리 숫자 |
| `url` | `url` | 유효한 URL |
| `ip` | `ip` | 유효한 IP 주소 |
| `json` | `json` | 유효한 JSON |
| `date` | `date` | `strtotime()` 가능한 날짜 |
| `date_format` | `date_format:Y-m-d` | 특정 날짜 포맷 |
| `before` | `before:2025-01-01` | 지정일 이전 |
| `after` | `after:2025-01-01` | 지정일 이후 |
| `regex` | `regex:/^[A-Z]+$/` | 정규식 매칭 |
| `confirmed` | `confirmed` | `{field}_confirmation` 필드와 일치 |
| `unique` | `unique:table,column` | DB 중복 검사 |
| `same` | `same:other_field` | 다른 필드와 동일 |
| `different` | `different:other_field` | 다른 필드와 다름 |
| `size` | `size:N` | 정확한 크기 (문자열/숫자/배열) |

---

## 사용 예제

### 기본 검증

```php
$result = valid()
    ->rules([
        'name'     => 'required|string|min:2|max:50',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|min:8|confirmed',
        'age'      => 'nullable|integer|between:1,150',
    ])
    ->check(input());

if ($result->fails()) {
    json()->fail('검증 실패', $result->errors());
}
```

### 웹 폼 검증

```php
router()->post('/register', function () {
    $v = valid()->rules([
        'name'     => 'required|string|between:2,30',
        'email'    => 'required|email|unique:users',
        'password' => 'required|min:8|confirmed',
        'role'     => 'in:user,admin',
    ])->check(input());

    if ($v->fails()) {
        session()->flash('errors', $v->errors());
        session()->flash('old', input());
        response()->back();
    }

    // 성공 처리...
});
```

### 날짜 검증

```php
$v = valid()->rules([
    'start_date' => 'required|date_format:Y-m-d|after:today',
    'end_date'   => 'required|date_format:Y-m-d|after:2025-01-01',
])->check($data);
```

### 정규식 검증

```php
$v = valid()->rules([
    'phone' => 'required|regex:/^01[016789]-?\d{3,4}-?\d{4}$/',
    'code'  => 'required|regex:/^[A-Z]{3}-\d{4}$/',
])->check($data);
```

### 커스텀 규칙

```php
// 규칙 등록 (부팅 시 1회)
Valid::extend('phone_kr', function (string $field, mixed $value, array $params, array $data): ?string {
    if (!is_string($value) || !preg_match('/^01[016789]\d{7,8}$/', $value)) {
        return "{$field}은(는) 유효한 한국 전화번호여야 합니다";
    }
    return null;  // null = 통과
});

// 사용
$v = valid()->rules(['phone' => 'required|phone_kr'])->check($data);
```

---

## 내부 동작

### 검증 흐름

```text
valid()->rules([...])->check($data)
├─ clone → 이뮤터블
├─ 각 필드별:
│   ├─ nullable + 빈 값? → 건너뛰기
│   ├─ 규칙 문자열 '|' 분리
│   ├─ 각 규칙:
│   │   ├─ parseRule(): 규칙명 + 파라미터 분리
│   │   └─ validateRule(): match 표현식으로 검증
│   └─ 에러 누적 → fieldErrors[field][]
└─ 결과 반환 (clone된 인스턴스)
```

### 규칙 파싱

```text
'min:8'           → ['min', ['8']]
'in:a,b,c'        → ['in', ['a', 'b', 'c']]
'regex:/^[A-Z]+$/'→ ['regex', ['/^[A-Z]+$/']]  ← regex는 : 분리 안 함
'unique:users,email' → ['unique', ['users', 'email']]
```

### min/max 이중 동작

- **문자열**: `mb_strlen()` 기준 글자 수
- **숫자**: 값 크기 비교

```php
// 'min:3' — 문자열이면 3자 이상, 숫자면 3 이상
```

### unique 규칙

```text
'unique:users,email'
├─ table = 'users'
├─ column = 'email' (생략 시 필드명 사용)
└─ db()->table('users')->where('email', $value)->first()
```

---

## 보안 고려사항

- **unique 규칙**: `db()->where()` 사용 → PDO prepared statement로 SQL 인젝션 방지
- **regex 규칙 주의**: 사용자 입력을 regex 패턴으로 사용하면 ReDoS 공격 가능. 패턴은 코드에서 고정 정의
- **미등록 규칙 경고**: debug 모드에서 미등록 규칙 사용 시 `logger()->warn()` — 오타로 인한 검증 누락 방지

---

## 주의사항

1. **이뮤터블**: `rules()`, `check()` 모두 `clone` 반환. 싱글턴 상태 오염 없음.

2. **에러 메시지 한국어**: 모든 내장 메시지가 한국어 — 커스텀 메시지 오버라이드 기능은 미제공.

3. **nullable vs required**: `nullable`만 있으면 빈 값 허용. `nullable|required`이면 required가 적용됨.

4. **regex 콜론**: regex 규칙의 패턴 내부 `:`는 안전하게 보존됨 (`explode(':', $raw, 2)` 사용).

5. **confirmed 규칙**: `password` 필드에 `confirmed` 적용 시 `password_confirmation` 필드가 있어야 함.

6. **unique 규칙 DB 의존**: `unique` 규칙 사용 시 DB 도구가 반드시 설정되어 있어야 함.

---

## 연관 도구

- [Json](Json.md) — `json()->fail()` 검증 에러 응답
- [Guard](Guard.md) — XSS 살균 (검증 전 입력 정리)
- [DB](DB.md) — `unique` 규칙 내부 사용
- [Session](Session.md) — 웹 폼 에러 flash
