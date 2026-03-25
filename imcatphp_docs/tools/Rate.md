# Rate — 레이트 리미트 (속도 제한)

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Rate` |
| 파일 | `catphp/Rate.php` (136줄) |
| Shortcut | `rate()` |
| 싱글턴 | `getInstance()` |
| 의존 도구 | `Cat\Ip` (IP 기반 키 생성) |
| 저장 방식 | JSON 파일 (슬라이딩 윈도우) |

---

## 설정

```php
// config/app.php
'rate' => [
    'path' => __DIR__ . '/../storage/rate',   // 레이트 파일 저장 디렉토리
],
```

---

## 메서드 레퍼런스

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `limit` | `limit(string $key, int $window, int $max): bool` | `bool` | 요청 기록 + 허용 여부 반환 |
| `check` | `check(string $key, int $window, int $max): bool` | `bool` | 조회만 (기록하지 않음) |
| `remaining` | `remaining(string $key, int $window, int $max): int` | `int` | 남은 요청 횟수 |
| `reset` | `reset(string $key): bool` | `bool` | 레이트 리미트 초기화 (파일 삭제) |

---

## 사용 예제

### 기본 속도 제한

```php
// 1분에 60회 제한
if (!rate()->limit('api', 60, 60)) {
    json()->error('Too Many Requests', 429);
}
```

### 엔드포인트별 분리

```php
// 로그인: 5분에 5회
if (!rate()->limit('login', 300, 5)) {
    json()->error('로그인 시도 횟수 초과', 429);
}

// 검색: 1분에 30회
if (!rate()->limit('search', 60, 30)) {
    json()->error('검색 요청 초과', 429);
}
```

### 남은 횟수 확인

```php
$remaining = rate()->remaining('api', 60, 100);
header("X-RateLimit-Remaining: {$remaining}");
```

### 조회만 (기록 없이)

```php
// 제한 상태만 확인 (횟수 증가 없음)
if (!rate()->check('api', 60, 100)) {
    // 이미 제한 상태
}
```

### 초기화

```php
// 특정 키의 레이트 리미트 리셋
rate()->reset('login');
```

### Api 도구와 연동

```php
// api()->rateLimit()이 내부적으로 rate()->limit() + rate()->remaining() 호출
api()->cors()->rateLimit(60, 100)->apply();
```

---

## 내부 동작

### 슬라이딩 윈도우 알고리즘

```text
limit('api', 60, 100)
│
├─ IP 감지: ip()->address()
├─ 파일 경로: storage/rate/{md5(key:ip)}.json
├─ flock(LOCK_EX) — 배타 락
├─ 기존 데이터 로드
├─ 만료 히트 제거: 현재 시간 - window 이전 기록 삭제
├─ count(hits) >= max? → false (제한 초과)
├─ hits[] += now → 기록 추가
├─ 파일 쓰기
└─ flock(LOCK_UN) — 락 해제
```

### 파일 구조

```text
storage/rate/{hash}.json
{
    "hits": [1711324800, 1711324801, 1711324802, ...]
}
```

- 파일명: `md5("{key}:{ip}")` — IP + 키 조합으로 고유 파일
- hits 배열: 각 요청의 Unix timestamp

### 동시성 보호

| 작업 | 잠금 |
| --- | --- |
| `limit()` | `flock(LOCK_EX)` — 배타 락 (읽기+쓰기 원자적) |
| `check()` | 잠금 없음 (읽기만) |
| `remaining()` | 잠금 없음 (읽기만) |

### 디렉토리 자동 생성

최초 `limit()` 호출 시 `storage/rate/` 디렉토리가 없으면 자동 생성. `static $dirChecked`로 1회만 체크.

---

## 보안 고려사항

- **IP 기반**: `ip()->address()`로 클라이언트 IP 감지. `trusted_proxies` 설정이 중요
- **파일 잠금**: `flock(LOCK_EX)`로 동시 요청 시 경쟁 조건 방지
- **키 해싱**: 파일명에 `md5()` 사용 — IP 주소가 파일명에 노출되지 않음

---

## 주의사항

1. **파일 기반 한계**: 대규모 트래픽(수천 RPS)에서는 파일 I/O가 병목. Redis 기반 Rate Limit 권장.

2. **슬라이딩 윈도우**: 고정 윈도우(매 분 0초 리셋)가 아닌 슬라이딩 윈도우 방식. 더 정확하지만 hits 배열이 커질 수 있다.

3. **IP 기반 제한**: NAT/프록시 뒤 사용자가 같은 IP를 공유하면 공정하지 않을 수 있다. API 키 기반 제한이 더 정확.

4. **`check()` vs `limit()`**: `check()`는 읽기 전용이라 잠금 없이 빠르지만, 동시 요청 시 정확도가 떨어질 수 있다.

5. **파일 정리**: 오래된 레이트 파일은 자동 삭제되지 않는다. 크론잡으로 `storage/rate/` 디렉토리를 주기적으로 정리 권장.

6. **`reset()` 주의**: 현재 IP의 레이트만 초기화한다. 다른 IP의 레이트는 영향 없음.

---

## 연관 도구

- [Api](Api.md) — API 미들웨어 (`api()->rateLimit()` 내부 사용)
- [Ip](Ip.md) — IP 감지 (`rate()` 내부에서 IP 기반 키 생성)
- [Firewall](Firewall.md) — Rate Limit 초과 시 IP 차단 연동 가능
