# Firewall — IP 차단/허용

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Firewall` |
| 파일 | `catphp/Firewall.php` (211줄) |
| Shortcut | `firewall()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Ip` (미들웨어에서 IP 감지) |
| 저장 방식 | JSON 파일 (`banned.json`) |

---

## 설정

```php
// config/app.php
'firewall' => [
    'path' => __DIR__ . '/../storage/firewall',   // 차단 목록 저장 디렉토리
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `allow` | `allow(string $ipOrCidr): self` | `self` | IP 또는 CIDR 허용 등록 |
| `deny` | `deny(string $ip): self` | `self` | IP 차단 (`ban()` 별칭) |
| `ban` | `ban(string $ip): self` | `self` | IP 차단 + 파일 저장 |
| `unban` | `unban(string $ip): self` | `self` | IP 차단 해제 + 파일 저장 |
| `isAllowed` | `isAllowed(string $ip): bool` | `bool` | IP 허용 여부 (허용 목록 → 차단 목록 순) |
| `isDenied` | `isDenied(string $ip): bool` | `bool` | IP 차단 여부 |
| `bannedList` | `bannedList(): array` | `array` | 차단 IP 목록 (`[['ip'=>..., 'banned_at'=>...], ...]`) |
| `middleware` | `middleware(): callable` | `callable` | 라우터 미들웨어 (차단 IP → 403) |

---

## CLI 명령어

```bash
php cli.php firewall:ban 192.168.1.100    # IP 차단
php cli.php firewall:unban 192.168.1.100  # 차단 해제
php cli.php firewall:list                  # 차단 목록
```

---

## 사용 예제

### IP 차단/해제

```php
firewall()->ban('192.168.1.100');
firewall()->unban('192.168.1.100');
```

### 허용 목록 (화이트리스트)

```php
// 특정 IP 항상 허용 (차단 목록에 있어도 우선)
firewall()->allow('10.0.0.1');

// CIDR 범위 허용 (IPv4 + IPv6)
firewall()->allow('192.168.0.0/24');
firewall()->allow('2001:db8::/32');
```

### 확인

```php
if (firewall()->isDenied($ip)) {
    // 차단된 IP
}

if (firewall()->isAllowed($ip)) {
    // 허용된 IP (허용 목록 또는 미차단)
}
```

### 미들웨어 등록

```php
router()->use(firewall()->middleware());
// 차단 IP → 403 JSON 응답 + exit
```

### Guard 연동 (자동 차단)

```php
// config: guard.auto_ban = true
// Guard가 공격 감지 시 자동으로 firewall()->ban() 호출
```

---

## 내부 동작

### 저장 구조

```text
storage/firewall/banned.json
{
    "192.168.1.100": 1711324800,    ← IP: 차단 시각 (Unix timestamp)
    "10.0.0.50": 1711325000
}
```

### 지연 로딩

차단 목록은 최초 조회 시점에 1회만 파일에서 로드한다 (`$loaded` 플래그).

### 파일 잠금

| 작업 | 잠금 |
| --- | --- |
| 읽기 (`load()`) | `flock(LOCK_SH)` — 공유 락 |
| 쓰기 (`save()`) | `file_put_contents(LOCK_EX)` — 배타 락 |

### CIDR 매칭

`isInCidr()` — IPv4와 IPv6 모두 지원하는 바이너리 마스크 비교:

```text
isInCidr('192.168.1.50', '192.168.1.0/24')
├─ inet_pton() → 바이너리 변환
├─ 마스크 생성 (24비트 → '\xff\xff\xff\x00')
└─ (IP & mask) === (subnet & mask) → true
```

### 허용 우선 규칙

```text
isAllowed($ip)
├─ 허용 목록에 직접 매칭? → true
├─ 허용 CIDR에 포함? → true
└─ 차단 목록에 없으면 → true, 있으면 → false
```

> **허용 목록이 차단 목록보다 우선한다.**

---

## 보안 고려사항

- **IP 형식 검증**: `ban()`, `unban()`에서 `filter_var(FILTER_VALIDATE_IP)` — 잘못된 IP 거부
- **파일 잠금**: 동시 요청 시 데이터 손상 방지
- **JSON 저장**: PHP `serialize()` 대신 JSON 사용 — 역직렬화 공격 불가
- **미들웨어**: 차단 IP에 403 응답 + `exit` — 후속 핸들러 실행 차단

---

## 주의사항

1. **파일 기반 한계**: 대량의 IP 차단(수만 개 이상)에는 성능 문제. Redis나 DB 기반 방화벽 권장.

2. **허용 목록은 메모리 전용**: `allow()`로 등록한 허용 IP는 파일에 저장되지 않는다. 애플리케이션 재시작 시 초기화.

3. **IPv6 지원**: `ban()`, `isInCidr()` 모두 IPv6 지원. 단, IPv4와 IPv6 혼합 CIDR 비교는 주소 길이 불일치로 `false` 반환.

4. **디렉토리 자동 생성**: `ban()` 호출 시 `storage/firewall/` 디렉토리가 없으면 자동 생성.

5. **Rate 도구 연동**: `Rate` 도구에서 속도 제한 초과 시 자동으로 `firewall()->ban()` 호출 가능.

---

## 연관 도구

- [Ip](Ip.md) — IP 감지 (미들웨어에서 `\ip()->address()` 호출)
- [Guard](Guard.md) — 공격 감지 시 자동 차단 (`auto_ban`)
- [Rate](Rate.md) — 속도 제한 초과 시 차단 연동
