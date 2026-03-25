# Hash — 파일 체크섬 / 무결성 검증

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Hash` |
| 파일 | `catphp/Hash.php` (184줄) |
| Shortcut | `hash()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | 없음 (PHP 내장 `hash_*` 함수) |

---

## 설정

```php
// config/app.php
'hash' => [
    'algo' => 'sha256',   // 기본 해시 알고리즘
],
```

---

## 메서드 레퍼런스

### 파일 해싱

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `file` | `file(string $path, ?string $algo = null): string` | `string` | 파일 해시 계산 |
| `verify` | `verify(string $path, string $expectedHash, ?string $algo = null): bool` | `bool` | 파일 무결성 검증 |
| `checksum` | `checksum(string $path): string` | `string` | 파일 CRC32 체크섬 |

### 문자열 해싱

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `string` | `string(string $data, ?string $algo = null): string` | `string` | 문자열 해시 |
| `hmac` | `hmac(string $data, string $key, ?string $algo = null): string` | `string` | HMAC 서명 |
| `verifyHmac` | `verifyHmac(string $data, string $expectedMac, string $key, ?string $algo = null): bool` | `bool` | HMAC 검증 |

### 비밀번호

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `password` | `password(string $password): string` | `string` | 비밀번호 해시 (Argon2id/Bcrypt) |
| `passwordVerify` | `passwordVerify(string $password, string $hash): bool` | `bool` | 비밀번호 검증 |
| `passwordNeedsRehash` | `passwordNeedsRehash(string $hash): bool` | `bool` | 리해시 필요 여부 |

### 유틸리티

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `equals` | `equals(string $known, string $user): bool` | `bool` | 타이밍 안전 비교 |
| `algorithms` | `algorithms(): array` | `array` | 사용 가능한 알고리즘 목록 |
| `directory` | `directory(string $path, ?string $algo = null): array` | `array` | 디렉토리 매니페스트 |
| `verifyDirectory` | `verifyDirectory(string $path, array $manifest, ?string $algo = null): array` | `array` | 디렉토리 무결성 검증 |

---

## 사용 예제

### 파일 해시

```php
$sha256 = hash()->file('storage/backup.zip');
$md5    = hash()->file('storage/backup.zip', 'md5');

// 무결성 검증
if (hash()->verify('download.zip', $expectedHash)) {
    echo '파일 무결성 확인';
}
```

### 문자열 해시

```php
$hash = hash()->string('hello world');          // SHA-256
$hash = hash()->string('hello world', 'md5');   // MD5
```

### HMAC 서명/검증

```php
$mac = hash()->hmac('중요 데이터', 'secret-key');

if (hash()->verifyHmac('중요 데이터', $mac, 'secret-key')) {
    echo '서명 유효';
}
```

### 비밀번호 해싱

```php
$hashed = hash()->password('my-password');          // Argon2id 해시
$valid  = hash()->passwordVerify('my-password', $hashed);  // true

if (hash()->passwordNeedsRehash($existingHash)) {
    // 알고리즘/비용 변경 시 리해시 필요
    $newHash = hash()->password($plainPassword);
}
```

### 디렉토리 매니페스트

```php
// 디렉토리 전체 파일의 해시 생성
$manifest = hash()->directory('Public/assets');
// ['css/app.css' => 'abc123...', 'js/app.js' => 'def456...', ...]

// 매니페스트 저장
file_put_contents('manifest.json', json_encode($manifest));

// 무결성 검증
$diff = hash()->verifyDirectory('Public/assets', $manifest);
// ['modified' => ['css/app.css'], 'added' => ['js/new.js'], 'removed' => []]
```

### 타이밍 안전 비교

```php
// 타이밍 공격 방지
if (hash()->equals($storedToken, $userToken)) {
    // 토큰 일치
}
```

---

## 내부 동작

### 비밀번호 알고리즘

`config('auth.algo')` 설정에 따라 분기:

| 설정값 | PHP 상수 | 설명 |
| --- | --- | --- |
| `argon2id` (기본) | `PASSWORD_ARGON2ID` | 메모리 하드 해시 |
| `bcrypt` | `PASSWORD_BCRYPT` | 72바이트 제한 |

### Bcrypt 72바이트 제한

```php
if ($phpAlgo === PASSWORD_BCRYPT && strlen($password) > 72) {
    throw new \InvalidArgumentException('...');
}
```

72바이트 초과 비밀번호는 Bcrypt에서 잘림 — 명시적 에러로 방지.

### 디렉토리 무결성 검증 흐름

```text
directory($path)
├─ RecursiveDirectoryIterator (SKIP_DOTS)
├─ 각 파일: hash_file($algo, $file)
├─ 상대 경로 키 (\ → / 정규화)
└─ ksort (일관된 순서)

verifyDirectory($path, $manifest)
├─ 현재 매니페스트 생성
├─ manifest에 있는데 현재 없음 → removed
├─ manifest에 있는데 해시 다름 → modified
└─ 현재에 있는데 manifest에 없음 → added
```

---

## 보안 고려사항

- **`#[\SensitiveParameter]`**: `hmac()`, `verifyHmac()`, `password()`, `passwordVerify()`의 키/비밀번호 파라미터
- **`hash_equals()`**: `verify()`, `verifyHmac()`, `equals()`에서 타이밍 안전 비교
- **파일 해시**: `hash_file()` — 대용량 파일도 메모리 효율적 (스트리밍)

---

## 주의사항

1. **Auth와 역할 분담**: `Hash` 도구는 범용 해싱, `Auth` 도구는 인증 전용. 비밀번호 해싱은 두 도구 모두 가능하나 `Auth`를 권장.
2. **알고리즘 기본값**: `hash.algo` 미설정 시 `sha256`. 보안 목적이면 SHA-256 이상 권장.
3. **CRC32**: `checksum()`은 `crc32b` 사용. 무결성 검증 전용, 보안 용도 부적합.
4. **디렉토리 크기**: 파일 수가 많으면 `directory()`가 느릴 수 있다. 캐시 활용 권장.

---

## 연관 도구

- [Auth](Auth.md) — 인증 전용 비밀번호 해싱
- [Encrypt](Encrypt.md) — 대칭 암호화
- [Backup](Backup.md) — 백업 파일 무결성 검증
