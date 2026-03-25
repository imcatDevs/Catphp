# Flash — 플래시 메시지

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Flash` |
| 파일 | `catphp/Flash.php` (66줄) |
| Shortcut | `flash()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Session`, `Cat\Guard` |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `set` | `set(string $type, string $message): void` | `void` | 메시지 설정 (Guard 살균) |
| `get` | `get(): array` | `array` | 메시지 읽기 (읽은 후 삭제) |
| `has` | `has(): bool` | `bool` | 메시지 존재 확인 |
| `success` | `success(string $message): void` | `void` | 성공 메시지 |
| `error` | `error(string $message): void` | `void` | 에러 메시지 |
| `warning` | `warning(string $message): void` | `void` | 경고 메시지 |
| `info` | `info(string $message): void` | `void` | 정보 메시지 |

---

## 사용 예제

### 메시지 설정 (리다이렉트 전)

```php
router()->post('/posts', function () {
    $id = db()->table('posts')->insert(input());
    flash()->success('게시글이 저장되었습니다.');
    response()->redirect("/posts/{$id}");
});

router()->post('/login', function () {
    if (!user()->attempt(input('email'), input('password'))) {
        flash()->error('이메일 또는 비밀번호가 올바르지 않습니다.');
        response()->back();
    }
    flash()->info('환영합니다!');
    response()->redirect('/dashboard');
});
```

### 메시지 표시 (뷰)

```php
<?php if (flash()->has()): ?>
    <?php foreach (flash()->get() as $msg): ?>
        <div class="alert alert-<?= $msg['type'] ?>">
            <?= $msg['message'] ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
```

### 타입별 메시지

```php
flash()->success('저장 완료');
flash()->error('삭제 실패');
flash()->warning('입력값을 확인하세요');
flash()->info('새 버전이 있습니다');
```

### 커스텀 타입

```php
flash()->set('custom_type', '커스텀 메시지');
```

---

## 내부 동작

### 저장 구조

```text
$_SESSION['_flash'] = [
    ['type' => 'success', 'message' => '저장 완료'],
    ['type' => 'error', 'message' => '실패'],
]
```

### set() 흐름

```text
set('success', '저장 완료')
├─ type 살균: 영문+밑줄만 허용 (preg_replace)
├─ message 살균: guard()->clean()
└─ $_SESSION['_flash'][]에 추가
```

### get() 흐름

```text
get()
├─ $_SESSION['_flash'] 읽기
├─ $_SESSION['_flash'] = [] (즉시 비움)
└─ 배열 반환
```

---

## 보안 고려사항

- **타입 살균**: `preg_replace('/[^a-zA-Z_]/', '', $type)` — XSS 방지 (HTML class에 사용되므로)
- **메시지 살균**: `guard()->clean()` — XSS, CRLF 등 종합 살균
- **일회성**: `get()` 호출 시 즉시 삭제 → 같은 메시지가 반복 표시되지 않음

---

## 주의사항

1. **세션 의존**: `session()` 도구가 활성이어야 한다. API 전용 서버에서는 사용 불가.

2. **일회성**: `get()` 호출 시 메시지가 삭제된다. 다시 표시하려면 다시 `set()` 해야 한다.

3. **복수 메시지**: 같은 요청에서 여러 번 `set()` 가능. `get()`은 배열로 모두 반환.

4. **Session의 flash와 차이**: `session()->flash()`는 키-값 기반, `flash()`는 타입-메시지 배열 기반. UI 표시에 더 적합.

---

## 연관 도구

- [Session](Session.md) — 세션 저장소 (내부 사용)
- [Guard](Guard.md) — 메시지 XSS 살균
- [Response](Response.md) — 리다이렉트 (`response()->redirect()`, `response()->back()`)
