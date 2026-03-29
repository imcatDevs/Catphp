# Webhook — Webhook 발송/수신 + HMAC 서명

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Webhook` |
| 파일 | `catphp/Webhook.php` (328줄) |
| Shortcut | `webhook()` |
| 싱글턴 | `getInstance()` — 이뮤터블 체이닝 (`clone`) |
| 의존 확장 | `ext-curl` |
| 연동 | `Cat\Log` (실패 로깅) |

---

## 설정

```php
// config/app.php
'webhook' => [
    'secret'      => 'your-webhook-secret',   // HMAC 서명 시크릿
    'timeout'     => 10,                        // cURL 타임아웃 (초)
    'retry'       => 3,                         // 재시도 횟수
    'retry_delay' => 1,                         // 재시도 간격 (초)
    'log'         => true,                      // 실패 로깅
],
```

---

## 메서드 레퍼런스

### 발송 빌더

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `to` | `to(string $url): self` | `self` | 대상 URL (http/https만 허용) |
| `secret` | `secret(string $secret): self` | `self` | HMAC 시크릿 설정 |
| `payload` | `payload(array $data): self` | `self` | 페이로드 설정 |
| `header` | `header(string $name, string $value): self` | `self` | 커스텀 헤더 추가 (CRLF 방어) |
| `send` | `send(): WebhookResult` | `WebhookResult` | 발송 실행 (재시도 포함) |

### 수신

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `receive` | `receive(): self` | `self` | 수신 모드 (php://input + 서명 헤더 파싱) |
| `isValid` | `isValid(?string $secret = null): bool` | `bool` | HMAC 서명 검증 (null이면 config 기본값) |
| `getPayload` | `getPayload(): ?array` | `?array` | 수신 페이로드 반환 |
| `getRawBody` | `getRawBody(): string` | `string` | 수신 raw body |

### 유틸

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `sign` | `sign(string $payload, string $secret): string` | `string` | HMAC-SHA256 서명 생성 |
| `signatureHeader` | `signatureHeader(string $payload, string $secret): string` | `string` | 서명 헤더 값 생성 (`sha256={hex}`) |

### WebhookResult 클래스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `status` | `status(): int` | `int` | HTTP 상태 코드 |
| `body` | `body(): string` | `string` | 응답 body |
| `ok` | `ok(): bool` | `bool` | 성공 여부 (2xx) |
| `attempts` | `attempts(): int` | `int` | 시도 횟수 |
| `json` | `json(): ?array` | `?array` | 응답 JSON 디코딩 |

---

## 사용 예제

### 발송

```php
$result = webhook()
    ->to('https://api.example.com/hooks/deploy')
    ->secret('my-secret')
    ->payload(['event' => 'deploy', 'version' => '1.2.0'])
    ->header('X-Custom', 'value')
    ->send();

if ($result->ok()) {
    echo "발송 성공: " . $result->status();
} else {
    echo "실패 (" . $result->status() . "): " . $result->body();
}
```

### 수신 + 검증

```php
router()->post('/webhooks/github', function () {
    $wh = webhook()->receive();

    if (!$wh->isValid('github-webhook-secret')) {
        json()->forbidden('Invalid signature');
    }

    $payload = $wh->getPayload();
    $event = $payload['action'] ?? '';
    // 이벤트 처리...

    json()->ok();
});
```

### config 기본값으로 발송

```php
// config의 secret, timeout, retry, retry_delay 사용
$result = webhook()
    ->to('https://hooks.example.com/notify')
    ->payload(['message' => '알림'])
    ->send();
```

---

## 내부 동작

### 발송 흐름

```text
send()
├─ JSON 인코딩 → payload
├─ HMAC-SHA256 서명 생성 → X-Webhook-Signature 헤더
├─ cURL 요청 (CURLOPT_FOLLOWLOCATION = false)
├─ 실패 시 재시도 (retry 횟수만큼, retry_delay 간격)
└─ WebhookResult 반환
```

### 수신 검증 흐름

```text
receive()
├─ php://input raw body 읽기
├─ X-Webhook-Signature 또는 X-Hub-Signature-256 헤더 추출
├─ isValid(?string $secret)
│   ├─ secret 미지정 → config 기본값 사용
│   ├─ HMAC-SHA256 재계산
│   ├─ GitHub 스타일 'sha256=' 접두사 처리
│   └─ hash_equals() 타이밍 안전 비교
└─ getPayload() → JSON 디코딩
```

### HMAC 서명 구조

```text
sign($payload, $secret)
→ hash_hmac('sha256', $payload, $secret)
→ 64자 hex 문자열

헤더: X-Webhook-Signature: sha256={hex}
```

---

## 보안 고려사항

### URL 스키마 검증 (SSRF 방어)

`to()` 메서드에서 http/https만 허용:

```php
$scheme = parse_url($url, PHP_URL_SCHEME);
if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
    throw new \InvalidArgumentException("Webhook URL은 http 또는 https만 허용됩니다: {$url}");
}
```

### 리다이렉트 SSRF 방어

`CURLOPT_FOLLOWLOCATION = false` — 리다이렉트를 통한 내부 네트워크 접근 차단.

### 타이밍 안전 비교

`hash_equals()`로 서명 비교 — 타이밍 공격 방지.

### CRLF 인젝션 방어

`header()` 메서드에서 `\r`, `\n` 제거.

### GitHub 호환

`X-Hub-Signature-256` 헤더와 `sha256=` 접두사 자동 처리.

---

## 주의사항

1. **ext-curl 필수**: cURL 확장이 없으면 발송 불가.

2. **재시도 블로킹**: `send()` 재시도는 동기 실행. 3회 재시도 시 최대 `timeout × 3 + retry_delay × 2`초 소요. 비동기 필요 시 Queue 도구 활용.

3. **수신 raw body**: `php://input`은 1회만 읽을 수 있으므로, `receive()` 전에 다른 코드에서 읽으면 안 된다.

4. **시크릿 관리**: 발신·수신 양측이 동일한 시크릿을 공유해야 서명 검증 성공.

5. **대용량 페이로드**: 발송 시 JSON 인코딩 크기에 주의. 매우 큰 데이터는 URL 참조 방식 권장.

---

## 연관 도구

- [Http](Http.md) — 범용 HTTP 클라이언트 (서명 없는 요청)
- [Api](Api.md) — API 미들웨어 (수신 측 보호)
- [Queue](Queue.md) — 비동기 Webhook 발송
- [Log](Log.md) — 실패 로깅
