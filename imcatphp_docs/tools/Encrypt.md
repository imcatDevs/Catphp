# Encrypt — Sodium 대칭키 암호화

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Encrypt` |
| 파일 | `catphp/Encrypt.php` (85줄) |
| Shortcut | `encrypt()` |
| 싱글턴 | `getInstance()` — 빈 키 시 `RuntimeException` |
| 의존 확장 | `ext-sodium` |

---

## 설정

```php
// config/app.php
'encrypt' => [
    'key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    // 또는 raw hex/binary 문자열
    // 필수, 빈 값이면 RuntimeException
],
```

### 키 생성

```bash
# PHP에서 키 생성
php -r "echo 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)) . PHP_EOL;"
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `seal` | `seal(string $plaintext): string` | `string` | 암호화 → base64 문자열 (nonce 포함) |
| `open` | `open(string $encrypted): ?string` | `?string` | 복호화 → 평문 또는 `null` (실패 시) |
| `sign` | `sign(string $message): string` | `string` | HMAC 서명 (Sodium auth) |
| `verify` | `verify(string $message, string $signature): bool` | `bool` | HMAC 서명 검증 |

---

## 사용 예제

### 암호화/복호화

```php
// 암호화
$encrypted = encrypt()->seal('민감한 데이터');
// → "base64로 인코딩된 문자열" (nonce + ciphertext)

// 복호화
$plain = encrypt()->open($encrypted);
// → '민감한 데이터'

// 복호화 실패 (변조된 데이터)
$result = encrypt()->open('잘못된문자열');
// → null
```

### HMAC 서명/검증

```php
$message = '중요한 메시지';
$sig = encrypt()->sign($message);

// 검증
$valid = encrypt()->verify($message, $sig);  // true
$valid = encrypt()->verify('변조됨', $sig);   // false
```

### 실전: 쿠키 암호화

```php
// 저장
$value = encrypt()->seal(json_encode(['user_id' => 123]));
setcookie('session_data', $value, time() + 3600);

// 읽기
$plain = encrypt()->open($_COOKIE['session_data'] ?? '');
if ($plain !== null) {
    $data = json_decode($plain, true);
}
```

---

## 내부 동작

### 암호화 알고리즘

| 구성 요소 | 값 |
| --- | --- |
| 알고리즘 | `sodium_crypto_secretbox` (XSalsa20-Poly1305) |
| 키 길이 | `SODIUM_CRYPTO_SECRETBOX_KEYBYTES` (32바이트) |
| Nonce | `SODIUM_CRYPTO_SECRETBOX_NONCEBYTES` (24바이트, 랜덤) |
| 인증 | Poly1305 MAC (변조 감지) |

### seal() 흐름

```text
seal($plaintext)
├─ nonce = random_bytes(24)          ← 매번 새 nonce
├─ ciphertext = secretbox($plaintext, $nonce, $key)
└─ return base64_encode(nonce + ciphertext)
```

### open() 흐름

```text
open($encrypted)
├─ decoded = base64_decode($encrypted)
├─ 길이 검증 (최소 24바이트)
├─ nonce = decoded[0:24]
├─ ciphertext = decoded[24:]
├─ plaintext = secretbox_open($ciphertext, $nonce, $key)
└─ 실패 → null (MAC 불일치 = 변조 감지)
```

### 키 처리

```text
config('encrypt.key') → $rawKey
├─ 'base64:...' 접두사 → base64_decode
├─ 길이 ≠ 32바이트 → sodium_crypto_generichash()로 32바이트 맞춤
└─ 최종 키 → $this->key (readonly)
```

### 메모리 안전 정리

`__destruct()`에서 `sodium_memzero($this->key)` 호출 — 키가 메모리에 잔류하지 않도록 안전하게 제거.

---

## 보안 고려사항

- **`#[\SensitiveParameter]`**: 키 파라미터에 적용 → 스택 트레이스에 노출 방지
- **`sodium_memzero()`**: 소멸자에서 키 메모리 안전 정리
- **빈 키 차단**: `RuntimeException`으로 키 없이 사용 불가
- **Nonce 재사용 없음**: 매 암호화마다 `random_bytes(24)` 새 nonce 생성
- **인증된 암호화**: Poly1305 MAC으로 변조 감지 (복호화 실패 시 `null`)

---

## 주의사항

1. **키 변경 시**: 이전 키로 암호화된 데이터는 복호화 불가. 키 로테이션 시 마이그레이션 필요.

2. **`open()` 반환값 확인**: `null` 반환은 변조 또는 잘못된 키를 의미. 항상 `null` 체크 필수.

3. **대용량 데이터**: Sodium은 메모리에 전체 데이터를 로드한다. 매우 큰 파일 암호화에는 스트리밍 암호화 사용 권장.

4. **ext-sodium 필수**: PHP 7.2+에 번들되어 있지만, 일부 환경에서는 별도 설치 필요.

---

## 연관 도구

- [Auth](Auth.md) — JWT 서명 (별도 HMAC)
- [Cookie](Cookie.md) — 쿠키 암호화 (내부적으로 `encrypt()` 사용)
- [Session](Session.md) — 세션 데이터 보호
