# Event — 이벤트 디스패처

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Event` |
| 파일 | `catphp/Event.php` (121줄) |
| Shortcut | `event()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | 없음 |

---

## 설정

별도 config 없음.

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `on` | `on(string $event, callable $callback, int $priority = 0): int` | `int` | 리스너 등록 → 리스너 ID |
| `once` | `once(string $event, callable $callback, int $priority = 0): int` | `int` | 일회성 리스너 등록 |
| `emit` | `emit(string $event, mixed ...$args): self` | `self` | 이벤트 발산 |
| `off` | `off(string $event, ?int $listenerId = null): self` | `self` | 리스너 제거 (ID 또는 전체) |
| `hasListeners` | `hasListeners(string $event): bool` | `bool` | 리스너 존재 확인 |

---

## 사용 예제

### 리스너 등록 + 발산

```php
event()->on('user.created', function (array $user) {
    mail()->to($user['email'])->subject('환영합니다')->body('<h1>가입 완료</h1>')->send();
});

event()->on('user.created', function (array $user) {
    logger()->info("새 사용자: {$user['name']}");
});

// 이벤트 발산
event()->emit('user.created', ['name' => '홍길동', 'email' => 'hong@example.com']);
```

### 우선순위

```php
// priority가 높은 리스너가 먼저 실행
event()->on('order.placed', fn($order) => logger()->info('로그'), 10);
event()->on('order.placed', fn($order) => notifyAdmin($order), 100);  // 이것이 먼저
event()->on('order.placed', fn($order) => sendEmail($order), 50);

event()->emit('order.placed', $order);
// 실행 순서: notifyAdmin(100) → sendEmail(50) → 로그(10)
```

### 전파 중단

```php
event()->on('payment.process', function ($payment) {
    if ($payment['amount'] > 1000000) {
        logger()->warn('고액 결제 차단');
        return false;  // 이후 리스너 실행 중단
    }
}, 100);

event()->on('payment.process', function ($payment) {
    processPayment($payment);  // 위에서 false 반환 시 실행되지 않음
}, 0);
```

### 일회성 리스너

```php
event()->once('app.boot', function () {
    logger()->info('앱 최초 부팅');
    // 두 번째 emit에서는 호출되지 않음
});

event()->emit('app.boot');  // 실행됨
event()->emit('app.boot');  // 실행 안 됨
```

### 리스너 제거

```php
$id = event()->on('cache.clear', fn() => logger()->info('캐시 삭제'));

// 특정 리스너 제거
event()->off('cache.clear', $id);

// 이벤트의 모든 리스너 제거
event()->off('cache.clear');
```

### 리스너 존재 확인

```php
if (event()->hasListeners('user.deleted')) {
    event()->emit('user.deleted', $userId);
}
```

---

## 내부 동작

### 리스너 저장 구조

```text
listeners['user.created'] = [
    ['id' => 0, 'callback' => fn, 'priority' => 100, 'once' => false],
    ['id' => 1, 'callback' => fn, 'priority' => 0,   'once' => true],
]
```

### 지연 정렬 (Lazy Sort)

```text
on() / once()
├─ listeners[$event][]에 추가
└─ dirty[$event] = true (정렬 필요 표시)

emit()
├─ dirty[$event] 체크
│   └─ true → usort(priority DESC) → dirty 해제
├─ 리스너 순회 실행
├─ once 리스너 → 실행 후 제거 예약
└─ false 반환 → break (전파 중단)
```

### once 제거 안전성

`once` 리스너는 인덱스를 역순으로 `array_splice()` — 인덱스 밀림 방지.

---

## 주의사항

1. **우선순위 방향**: 높은 값이 먼저 실행 (내림차순).
2. **전파 중단**: 콜백이 `false`를 반환하면 나머지 리스너 미실행. `null`이나 `void`는 계속 전파.
3. **ID 기반 제거**: `on()`/`once()` 반환값(int ID)으로 특정 리스너만 제거 가능.
4. **싱글턴 상태**: 요청 생명주기 동안 리스너가 누적. PHP-FPM에서는 요청마다 초기화.

---

## 연관 도구

- [Log](Log.md) — 이벤트 로깅
- [Notify](Notify.md) — 이벤트 기반 알림
- [Telegram](Telegram.md) — 이벤트 알림 채널
