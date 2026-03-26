# User — 유저 관리 (자동 XSS 살균)

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\User` |
| 파일 | `catphp/User.php` (307줄) |
| Shortcut | `user()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\DB`, `Cat\Auth`, `Cat\Guard` |

---

## 설정

```php
// config/app.php
'user' => [
    'table'       => 'users',       // 유저 테이블명
    'primary_key' => 'id',          // PK 컬럼명
    'hidden'      => ['password'],  // 응답에서 제외할 필드
],
```

---

## 메서드 레퍼런스

### 조회 (자동 XSS 살균)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `current` | `current(): ?array` | `?array` | 현재 로그인 유저 |
| `find` | `find(int\|string $id): ?array` | `?array` | ID로 조회 |
| `findBy` | `findBy(string $column, mixed $value): ?array` | `?array` | 필드로 조회 |
| `get` | `get(string $field, mixed $default = null): mixed` | `mixed` | 현재 유저 특정 필드 |
| `list` | `list(int $limit = 20, int $offset = 0): array` | `array` | 유저 목록 |
| `search` | `search(string $column, string $keyword, int $limit = 20): array` | `array` | 유저 검색 (LIKE) |
| `count` | `count(): int` | `int` | 유저 수 |
| `exists` | `exists(string $column, mixed $value): bool` | `bool` | 존재 확인 |

### 원본 조회 (XSS 살균 없음)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `raw` | `raw(int\|string $id): ?array` | `?array` | 원본 데이터 (비밀번호 검증용) |
| `rawBy` | `rawBy(string $column, mixed $value): ?array` | `?array` | 원본 필드 조회 |

### 쓰기

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `create` | `create(array $data): string\|false` | `string\|false` | 유저 생성 (비밀번호 자동 해싱) |
| `update` | `update(int\|string $id, array $data): int` | `int` | 유저 수정 (비밀번호 자동 해싱) |
| `delete` | `delete(int\|string $id): int` | `int` | 유저 삭제 |

### 인증

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `attempt` | `attempt(string $email, string $password, string $emailColumn = 'email'): bool` | `bool` | 로그인 시도 (타이밍 공격 방어) |
| `refresh` | `refresh(): ?array` | `?array` | 세션 유저 DB 새로고침 |

---

## 사용 예제

### 로그인

```php
router()->post('/login', function () {
    if (user()->attempt(input('email'), input('password'))) {
        response()->redirect('/dashboard');
    }
    flash()->error('이메일 또는 비밀번호가 올바르지 않습니다.');
    response()->back();
});
```

### 현재 유저

```php
$name = user()->get('name');
$user = user()->current();   // 전체 정보 (hidden 필드 제외, XSS 살균)
```

### CRUD

```php
// 생성 (비밀번호 자동 해싱)
$id = user()->create([
    'name'     => '홍길동',
    'email'    => 'hong@example.com',
    'password' => 'plain-password',  // 자동 해싱됨
    'role'     => 'user',
]);

// 수정
user()->update($id, ['name' => '김철수']);

// 삭제
user()->delete($id);
```

### 검색

```php
$results = user()->search('name', '홍', limit: 10);
```

### 세션 새로고침

```php
// 유저 정보 변경 후 세션 갱신
user()->update(auth()->id(), ['name' => '새이름']);
user()->refresh();  // 세션에 최신 DB 데이터 반영
```

---

## 내부 동작

### 자동 XSS 살균

모든 조회 메서드(`current`, `find`, `findBy`, `list`, `search`, `refresh`)는 결과를 `guard()->cleanArray()`로 살균한다. 비밀번호 등 hidden 필드는 사전 제거.

### 비밀번호 자동 해싱

`create()`, `update()`에서 password 필드가 해시가 아닌 평문이면 자동으로 `auth()->hashPassword()` 적용. 해시 여부는 접두사(`$2y$`, `$argon2id$` 등)로 판별.

### 타이밍 공격 방어

```text
attempt($email, $password)
├─ rawBy($emailColumn, $email) — DB 조회
├─ 유저 없으면 → 더미 해시로 verifyPassword() 실행
│   └─ 동일 시간 소비 → 존재 여부 유추 불가
├─ 비밀번호 검증
└─ 성공 시 → hidden 제거 → auth()->login()
```

### 입력 살균 (쓰기)

`create()`, `update()` 데이터는 `guard()->cleanArray($data, PASSWORD_FIELDS)`로 살균. 비밀번호 관련 필드(`password`, `passwd`, `password_hash`, `pass`, `secret`)는 살균에서 제외. 추가로 비밀번호 외 모든 문자열 필드에 `trim()` 적용.

---

## 보안 고려사항

- **`#[\SensitiveParameter]`**: `attempt()`의 password 파라미터에 적용
- **타이밍 공격 방어**: 유저 미존재 시에도 동일한 해싱 시간 소비
- **LIKE 이스케이프**: `search()`에서 `%`, `_`, `\` 이스케이프 — SQL 와일드카드 인젝션 방지
- **hidden 필드**: 비밀번호 등 민감 필드가 API 응답/세션에 포함되지 않음

---

## 주의사항

1. **`raw()` 사용 주의**: XSS 살균 없이 원본 데이터를 반환한다. 비밀번호 검증 등 내부 로직에서만 사용.
2. **`refresh()` 삭제된 유저**: DB에서 유저가 삭제되면 `auth()->logout()` 호출 후 `null` 반환.
3. **hidden 필드**: config의 `hidden`에 없는 필드도 `password` 외 민감 정보가 있으면 추가 필요.

---

## 연관 도구

- [Auth](Auth.md) — JWT/세션 인증
- [Guard](Guard.md) — XSS 살균
- [DB](DB.md) — 쿼리 실행
- [Perm](Perm.md) — 역할/권한 관리
