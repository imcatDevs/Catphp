# Perm — 역할/권한 관리 (RBAC)

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Perm` |
| 파일 | `catphp/Perm.php` (95줄) |
| Shortcut | `perm()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Auth` (현재 사용자), `Cat\Json` (미들웨어 에러 응답) |

---

## 설정

```php
// config/app.php
'perm' => [
    'roles' => ['admin', 'editor', 'user'],   // 역할 목록
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `role` | `role(string $role, array $permissions): self` | `self` | 역할에 권한 부여 |
| `can` | `can(string $permission): bool` | `bool` | 현재 사용자 권한 확인 |
| `cannot` | `cannot(string $permission): bool` | `bool` | 권한 없음 확인 |
| `roles` | `roles(): array` | `array` | 등록된 역할 목록 |
| `assign` | `assign(string $role): void` | `void` | 현재 사용자에게 역할 할당 (세션 갱신) |
| `middleware` | `middleware(string ...$allowedRoles): callable` | `callable` | 역할 기반 접근 제어 미들웨어 |

---

## 사용 예제

### 권한 정의 (부팅 시)

```php
// 역할별 권한 등록
perm()
    ->role('admin', ['*'])  // admin은 can()에서 자동 전체 허용
    ->role('editor', ['post.create', 'post.edit', 'post.delete', 'comment.moderate'])
    ->role('user', ['post.create', 'comment.create']);
```

### 권한 확인

```php
if (perm()->can('post.edit')) {
    // 편집 가능
}

if (perm()->cannot('user.delete')) {
    json()->error('Forbidden', 403);
}
```

### 미들웨어

```php
// admin, editor만 접근
router()->group('/admin', function () {
    router()->use(perm()->middleware('admin', 'editor'));

    router()->get('/posts', function () {
        json()->ok(db()->table('posts')->all());
    });
});

// admin만 접근
router()->delete('/api/users/{id:int}', function (int $id) {
    api()->cors()->auth()->apply();
    (perm()->middleware('admin'))();  // 인라인 호출
    user()->delete($id);
    json()->ok();
});
```

### 역할 할당

```php
// 사용자 역할 변경 (세션 즉시 반영)
perm()->assign('editor');
```

### 뷰에서 조건부 렌더링

```php
<?php if (perm()->can('post.edit')): ?>
    <a href="/posts/edit/<?= $post['id'] ?>">편집</a>
<?php endif; ?>
```

---

## 내부 동작

### can() 흐름

```text
can('post.edit')
├─ auth()->user() → null이면 false
├─ user['role'] ?? 'user'
├─ role === 'admin'? → true (전체 허용)
└─ permissions[$role]에 'post.edit' 포함? → true/false
```

### admin 슈퍼유저

`admin` 역할은 `can()` 내부에서 **모든 권한을 자동 허용**한다. `role()` 정의 불필요.

### middleware() 흐름

```text
middleware('admin', 'editor')
├─ auth()->user() → null이면 401 + exit
├─ user['role'] in ['admin', 'editor']? → 통과
└─ 아니면 403 + exit
```

### assign() 동작

세션의 유저 데이터에 `role` 필드를 갱신하고 `auth()->login()`으로 세션 재저장:

```php
$user['role'] = $role;
auth()->login($user);
```

---

## 주의사항

1. **메모리 기반**: 권한 정의는 매 요청마다 `role()`로 등록해야 한다. DB 기반 권한 관리가 필요하면 부팅 시 DB에서 로드.

2. **role 필드 의존**: 사용자 데이터에 `role` 키가 있어야 한다. 없으면 기본 `'user'` 역할.

3. **admin 하드코딩**: `admin` 문자열이 코드에 하드코딩되어 있다. 역할명을 변경하려면 소스 수정 필요.

4. **미들웨어 응답**: 미인증 시 `json()->error()` 호출 → API 전용. 웹 페이지 리다이렉트가 필요하면 커스텀 미들웨어 작성.

---

## 연관 도구

- [Auth](Auth.md) — 인증 (현재 사용자 정보)
- [User](User.md) — 유저 관리
- [Router](Router.md) — 미들웨어 등록
