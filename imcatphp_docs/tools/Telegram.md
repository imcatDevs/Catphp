# Telegram — 텔레그램 Bot API

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Telegram` |
| 파일 | `catphp/Telegram.php` (199줄) |
| Shortcut | `telegram()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 도구 | `Cat\Http` (API 호출), `Cat\Log` (실패 로깅) |

---

## 설정

```php
// config/app.php
'telegram' => [
    'bot_token'  => env()->get('TELEGRAM_BOT_TOKEN'),
    'chat_id'    => env()->get('TELEGRAM_CHAT_ID'),      // 기본 수신자
    'admin_chat' => env()->get('TELEGRAM_ADMIN_CHAT'),    // 관리자 채팅
],
```

---

## 메서드 레퍼런스

### 빌더 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `to` | `to(string $chatId): self` | `self` | 수신자 chat_id |
| `message` | `message(string $text): self` | `self` | 일반 텍스트 메시지 |
| `html` | `html(string $text): self` | `self` | HTML 포맷 메시지 |
| `markdown` | `markdown(string $text): self` | `self` | MarkdownV2 포맷 메시지 |
| `photo` | `photo(string $url, ?string $caption = null): self` | `self` | 사진 발송 |
| `file` | `file(string $path, ?string $caption = null): self` | `self` | 파일 발송 |
| `keyboard` | `keyboard(array $buttons): self` | `self` | 인라인 키보드 |

### 발송

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `send` | `send(): bool` | `bool` | 메시지 전송 |

---

## 사용 예제

### 텍스트 메시지

```php
telegram()->message('서버 배포 완료 v2.0.1')->send();

// 특정 수신자
telegram()->to('123456789')->message('알림 테스트')->send();
```

### HTML 포맷

```php
telegram()->html(
    '<b>배포 알림</b>' . "\n"
    . '버전: <code>v2.0.1</code>' . "\n"
    . '시간: ' . date('Y-m-d H:i:s')
)->send();
```

### MarkdownV2 포맷

```php
telegram()->markdown(
    '*긴급 알림*' . "\n"
    . '`CPU 사용률: 95%`'
)->send();
```

### 사진 발송

```php
telegram()->photo(
    'https://example.com/chart.png',
    '일일 트래픽 리포트'
)->send();
```

### 파일 발송

```php
telegram()->file(
    'storage/reports/2025-Q1.pdf',
    '2025년 1분기 보고서'
)->send();
```

### 인라인 키보드

```php
telegram()->message('작업을 선택하세요')
    ->keyboard([
        [['text' => '승인', 'callback_data' => 'approve'], ['text' => '거부', 'callback_data' => 'reject']],
        ['상세 보기'],  // 문자열 → text=callback_data
    ])
    ->send();
```

---

## 내부 동작

### API 호출 흐름

```text
send()
├─ chatId = $this->chatId ?? $defaultChatId
├─ botToken/chatId 빈 값 → false 반환
├─ filePath 있으면 → apiUpload('sendDocument')
│   └─ http()->upload(url, filePath, 'document', extra)
├─ photoUrl 있으면 → apiCall('sendPhoto')
│   └─ http()->post(url, {chat_id, photo, caption})
└─ 텍스트 → apiCall('sendMessage')
    └─ http()->post(url, {chat_id, text, parse_mode, reply_markup})
```

### 키보드 변환

```text
keyboard([['승인'], [['text'=>'거부', 'url'=>'...']]])
├─ 문자열 버튼 → {text: '승인', callback_data: '승인'}
├─ 배열 버튼 → 그대로 사용
└─ JSON → reply_markup 파라미터
```

### 에러 처리

API 응답의 `ok` 필드가 `false`이면:

- `logger()->error()` 로깅 (`Cat\Log` 존재 시)
- `send()` → `false` 반환

---

## 보안 고려사항

- **`#[\SensitiveParameter]`**: `$botToken` 파라미터 — 스택 트레이스에서 노출 방지
- **봇 토큰**: `.env` 파일에 저장 권장. 코드에 하드코딩 금지.

---

## 주의사항

1. **봇 토큰 필수**: `telegram.bot_token` 미설정 시 `send()` → `false`.
2. **chat_id**: `to()` 미호출 시 기본 `telegram.chat_id` 사용.
3. **파일 크기**: Telegram Bot API 제한: 파일 최대 50MB, 사진 최대 10MB.
4. **MarkdownV2 이스케이프**: 특수문자(`_`, `*`, `[`, `]`, `(`, `)`, `~`, `` ` ``, `>`, `#`, `+`, `-`, `=`, `|`, `{`, `}`, `.`, `!`)는 `\`로 이스케이프 필요.
5. **Http 의존**: 내부적으로 `http()->post()` / `http()->upload()` 사용.

---

## 연관 도구

- [Http](Http.md) — API 호출 (내부 사용)
- [Notify](Notify.md) — 다채널 알림 (Telegram 채널)
- [Event](Event.md) — 이벤트 기반 알림
