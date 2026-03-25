# Notify — 다채널 알림

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Notify` |
| 파일 | `catphp/Notify.php` (194줄) |
| Shortcut | `notify()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Mail` (mail 채널), `Cat\Telegram` (telegram 채널), `Cat\Log` (실패 로깅) |

---

## 설정

별도 config 없음. 각 채널의 설정(`mail.*`, `telegram.*`)을 사용.

---

## 메서드 레퍼런스

### 빌더 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `to` | `to(string ...$recipients): self` | `self` | 수신자 |
| `via` | `via(string ...$channels): self` | `self` | 채널 선택 (`mail`, `telegram`, 커스텀) |

### 채널 등록

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `channel` | `channel(string $name, callable $handler): void` | `void` | 커스텀 채널 등록 (static) |

### 발송

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `send` | `send(string $subject, string $body = ''): array` | `array` | 알림 발송 → 채널별 성공 여부 |
| `alert` | `alert(string $message): array` | `array` | 간편 발송 (채널 자동 선택) |

---

## 사용 예제

### 이메일 알림

```php
notify()
    ->to('user@example.com')
    ->via('mail')
    ->send('서버 점검 공지', '<p>내일 02:00~04:00 점검 예정입니다.</p>');
```

### 텔레그램 알림

```php
notify()
    ->to('@123456789')    // @ 접두사 = 텔레그램 chat_id
    ->via('telegram')
    ->send('배포 완료', 'v2.0.1 배포되었습니다.');
```

### 다채널 동시 발송

```php
$results = notify()
    ->to('admin@example.com', '@987654321')
    ->via('mail', 'telegram')
    ->send('긴급 알림', '서버 CPU 95% 초과');

// $results = ['mail' => true, 'telegram' => true]
```

### 간편 알림 (alert)

```php
// 채널 미지정 시 mail.host와 telegram.bot_token 설정 여부로 자동 선택
notify()->to('admin@example.com')->alert('디스크 용량 부족');
```

### 커스텀 채널

```php
// Slack 채널 등록
Notify::channel('slack', function (string $to, string $subject, string $body): bool {
    $payload = json_encode(['text' => "{$subject}\n{$body}"]);
    $ch = curl_init($to);  // $to = Slack webhook URL
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result === 'ok';
});

// 사용
notify()
    ->to('https://hooks.slack.com/services/xxx')
    ->via('slack')
    ->send('배포 알림', 'v2.0 배포 완료');
```

---

## 내부 동작

### 수신자 라우팅

```text
send($subject, $body)
├─ mail 채널: @가 포함된 수신자 → mail()->to($email)->send()
├─ telegram 채널:
│   ├─ @로 시작 → chat_id (@ 제거)
│   ├─ @포함(이메일) → 건너뛰기
│   └─ 순수 숫자 → chat_id
└─ 커스텀 채널: handler($to, $subject, $body)
```

### 에러 처리

```text
각 채널 실행:
├─ try → 성공: results[$channel] = true
├─ catch → results[$channel] = false
│   └─ logger()->error() (Cat\Log 존재 시)
└─ 다른 채널은 계속 실행
```

### alert() 자동 채널 선택

```text
alert($message)
├─ channels 비어있으면:
│   ├─ config('mail.host') 존재 → 'mail' 추가
│   └─ config('telegram.bot_token') 존재 → 'telegram' 추가
└─ send($message)
```

---

## 주의사항

1. **채널 도구 의존**: `mail` 채널은 `Cat\Mail`, `telegram` 채널은 `Cat\Telegram` 클래스가 필요.
2. **수신자 구분**: 이메일(`@` 포함)과 텔레그램(`@` 접두사 또는 숫자)이 자동 구분됨.
3. **커스텀 핸들러 반환값**: `false` 반환 시 실패로 처리. 그 외(`null`, `true`, 기타)는 성공.
4. **부분 실패**: 일부 채널이 실패해도 나머지 채널은 계속 실행. 결과 배열로 확인.

---

## 연관 도구

- [Mail](Mail.md) — 이메일 채널
- [Telegram](Telegram.md) — 텔레그램 채널
- [Log](Log.md) — 실패 로깅
