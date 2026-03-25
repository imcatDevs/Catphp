# Mail — SMTP 이메일 발송

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Mail` |
| 파일 | `catphp/Mail.php` (359줄) |
| Shortcut | `mail()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 확장 | 없음 (순수 소켓 기반) |

---

## 설정

```php
// config/app.php
'mail' => [
    'host'       => 'smtp.example.com',
    'port'       => 587,
    'username'   => 'user@example.com',
    'password'   => 'secret',
    'encryption' => 'tls',          // tls | ssl | none
    'from_email' => 'noreply@example.com',
    'from_name'  => 'CatPHP',
],
```

---

## 메서드 레퍼런스

### 빌더 (이뮤터블)

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `to` | `to(string ...$emails): self` | `self` | 수신자 |
| `cc` | `cc(string ...$emails): self` | `self` | 참조 |
| `bcc` | `bcc(string ...$emails): self` | `self` | 숨은 참조 |
| `replyTo` | `replyTo(string $email): self` | `self` | 회신 주소 |
| `subject` | `subject(string $subject): self` | `self` | 제목 |
| `body` | `body(string $html): self` | `self` | HTML 본문 (text/plain 자동 생성) |
| `text` | `text(string $plain): self` | `self` | 순수 텍스트 본문 |
| `template` | `template(string $name, array $data = []): self` | `self` | 뷰 템플릿 기반 본문 |
| `attach` | `attach(string $path, ?string $name = null, ?string $mime = null): self` | `self` | 파일 첨부 |

### 발송

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `send` | `send(): bool` | `bool` | 이메일 발송 |
| `preview` | `preview(): string` | `string` | MIME 메시지 문자열 (디버그용) |

---

## 사용 예제

### 기본 발송

```php
mail()
    ->to('user@example.com')
    ->subject('환영합니다')
    ->body('<h1>안녕하세요!</h1><p>가입을 축하합니다.</p>')
    ->send();
```

### 다수 수신자 + CC/BCC

```php
mail()
    ->to('a@example.com', 'b@example.com')
    ->cc('manager@example.com')
    ->bcc('log@example.com')
    ->replyTo('support@example.com')
    ->subject('공지사항')
    ->body('<p>내용</p>')
    ->send();
```

### 파일 첨부

```php
mail()
    ->to('user@example.com')
    ->subject('보고서')
    ->body('<p>첨부된 보고서를 확인하세요.</p>')
    ->attach('storage/reports/2025-Q1.pdf')
    ->attach('storage/data.xlsx', '데이터.xlsx')
    ->send();
```

### 뷰 템플릿

```php
// views/emails/welcome.php를 렌더링
mail()
    ->to($user['email'])
    ->subject('가입 환영')
    ->template('emails/welcome', ['name' => $user['name']])
    ->send();
```

### 디버그

```php
$mime = mail()
    ->to('test@example.com')
    ->subject('테스트')
    ->body('<p>내용</p>')
    ->preview();

echo $mime;  // MIME 헤더 + 본문 (실제 발송 없음)
```

---

## 내부 동작

### SMTP 흐름

```text
send()
├─ fsockopen(smtp.host:port) — SSL이면 ssl:// 프리픽스
├─ EHLO (호스트명)
├─ STARTTLS (TLS 암호화 시)
│   └─ TLSv1.2/1.3 핸드셰이크
├─ AUTH LOGIN (base64 인코딩)
├─ MAIL FROM:<from_email>
├─ RCPT TO:<each recipient> (to + cc + bcc)
├─ DATA → MIME 메시지 전송
└─ QUIT
```

### MIME 구조

```text
첨부 파일 있음:
  multipart/mixed
  ├─ multipart/alternative
  │   ├─ text/plain (base64)
  │   └─ text/html (base64)
  └─ attachment (base64)

첨부 없음 + HTML:
  multipart/alternative
  ├─ text/plain (base64)
  └─ text/html (base64)

텍스트만:
  text/plain (base64)
```

### 헤더 인코딩

비 ASCII 문자(한글 등)는 RFC 2047 방식으로 인코딩:

```text
Subject: =?UTF-8?B?7ZWc6riA7KCc66qp?=
```

---

## 보안 고려사항

- **CRLF 인젝션 방어**: 모든 이메일 주소에서 `\r\n\0` 제거 + `FILTER_VALIDATE_EMAIL` 검증
- **TLS 암호화**: `STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | TLSv1_3_CLIENT` — 안전한 TLS만 허용
- **Message-ID**: `random_bytes(16)` 기반 고유 ID
- **MIME boundary**: `random_bytes(16)` 기반 예측 불가 경계 문자열

---

## 주의사항

1. **순수 소켓**: 외부 라이브러리(PHPMailer, SwiftMailer) 없이 직접 SMTP 통신. 대부분의 SMTP 서버와 호환.
2. **body() 자동 plain**: `body()`로 HTML 설정 시 `strip_tags()`로 text/plain 버전 자동 생성.
3. **첨부 파일 크기**: 메모리에 base64 인코딩 전체를 로드. 대용량 파일은 메모리 제한 주의.
4. **from 필수**: `mail.from_email` config 없으면 `RuntimeException`.
5. **타임아웃**: 소켓 연결 타임아웃 10초 고정.

---

## 연관 도구

- [Notify](Notify.md) — 다채널 알림 (Mail 채널 내부 사용)
- [Router](Router.md) — `render()` 템플릿 (template 메서드)
