# Ip — IP 감지 + GeoIP

| 항목 | 값 |
| --- | --- |
| 클래스 | `Cat\Ip` |
| 파일 | `catphp/Ip.php` (195줄) |
| Shortcut | `ip()` |
| 싱글턴 | `getInstance()` |
| 의존 확장 | 없음 (GeoIP API는 `file_get_contents` 사용) |
| 연동 | `Cat\Cache` (GeoIP 캐시), `Cat\Request` (`ip()` 위임) |

---

## 설정

```php
// config/app.php
'ip' => [
    'provider'        => 'api',           // 'api' (ip-api.com) 또는 'mmdb' (MaxMind)
    'mmdb_path'       => null,            // MaxMind .mmdb 파일 경로
    'cache_ttl'       => 86400,           // GeoIP 캐시 유효시간 (초, 기본 24시간)
    'trusted_proxies' => [],              // 신뢰 프록시 IP 목록 (빈 배열 = 모두 신뢰)
],
```

---

## 메서드 레퍼런스

### IP 감지

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `address` | `address(): string` | `string` | 클라이언트 실제 IP (프록시 헤더 포함) |

### GeoIP

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `country` | `country(?string $ip = null): ?string` | `?string` | 국가 코드 (예: `'KR'`) |
| `city` | `city(?string $ip = null): ?string` | `?string` | 도시명 |
| `location` | `location(?string $ip = null): ?array` | `?array` | 위도/경도 `['lat'=>..., 'lon'=>...]` |
| `info` | `info(?string $ip = null): array` | `array` | 전체 정보 `{ip, country, city, lat, lon}` |

### CIDR

| 메서드 | 시그니처 | 반환 | 설명 |
| --- | --- | --- | --- |
| `isInRange` | `isInRange(string $ip, string $cidr): bool` | `bool` | CIDR 범위 포함 확인 (IPv4 + IPv6) |

---

## 사용 예제

### 클라이언트 IP 감지

```php
$clientIp = ip()->address();
// → '203.0.113.50' (프록시 뒤에서도 실제 IP)
```

### GeoIP 정보

```php
$country = ip()->country();          // 'KR'
$city    = ip()->city();             // 'Seoul'
$loc     = ip()->location();         // ['lat' => 37.5665, 'lon' => 126.978]

// 특정 IP 조회
$info = ip()->info('8.8.8.8');
// ['ip' => '8.8.8.8', 'country' => 'US', 'city' => 'Mountain View', 'lat' => 37.386, 'lon' => -122.084]
```

### CIDR 범위 확인

```php
ip()->isInRange('192.168.1.50', '192.168.1.0/24');  // true
ip()->isInRange('10.0.0.1', '192.168.0.0/16');       // false

// IPv6
ip()->isInRange('2001:db8::1', '2001:db8::/32');     // true

// 단일 IP 비교 (CIDR 아닌 경우)
ip()->isInRange('1.2.3.4', '1.2.3.4');               // true
```

### 국가별 접근 제어

```php
router()->use(function () {
    $country = ip()->country();
    if ($country !== null && in_array($country, ['CN', 'RU'], true)) {
        http_response_code(403);
        echo 'Access denied';
        return false;
    }
    return null;
});
```

---

## 내부 동작

### IP 감지 순서

```text
address()
├─ $remoteAddr = $_SERVER['REMOTE_ADDR']
├─ isTrustedProxy($remoteAddr) 확인
│   ├─ trusted_proxies 비어있으면 → 모두 신뢰 (하위 호환)
│   └─ 목록에 있으면 → 신뢰
│
├─ 신뢰 프록시일 때만 헤더 참조:
│   ├─ 1. HTTP_CF_CONNECTING_IP (CloudFlare)
│   ├─ 2. HTTP_X_FORWARDED_FOR (첫 번째 IP)
│   └─ 3. HTTP_X_REAL_IP (Nginx)
│
└─ 모든 IP는 filter_var(FILTER_VALIDATE_IP) 검증
```

### GeoIP 조회 흐름

```text
info($ip)
├─ 프라이빗 IP? → {ip, country: null, city: null} 즉시 반환
├─ Cache 조회 (geoip:{ip}) → 캐시 히트? → 반환
├─ fetchGeoData($ip)
│   └─ http://ip-api.com/json/{ip} 호출 (타임아웃 3초)
├─ 성공 + 국가 정보 존재 → 캐시 저장 (TTL 적용)
└─ 반환
```

### CIDR 매칭 (IPv4 + IPv6)

`inet_pton()` → 바이너리 변환 → 비트마스크 비교. IPv4와 IPv6 혼합 비교는 주소 길이 불일치로 `false`.

---

## 보안 고려사항

### 신뢰 프록시 (trusted_proxies)

- **빈 배열** (기본): 모든 프록시 신뢰 — `X-Forwarded-For` 등 헤더를 무조건 참조
- **명시 설정**: 지정된 IP만 프록시로 인정 — 나머지는 `REMOTE_ADDR` 직접 사용

```php
// 권장: 프록시 IP 명시
'trusted_proxies' => ['10.0.0.1', '10.0.0.2'],
```

> **운영 환경에서는 반드시 `trusted_proxies`를 설정하라.** 빈 배열이면 공격자가 `X-Forwarded-For` 헤더를 위조하여 IP를 속일 수 있다.

### IP 형식 검증

모든 헤더에서 추출한 IP는 `filter_var(FILTER_VALIDATE_IP)`로 검증한다. 위조된 헤더의 XSS/로그 인젝션을 차단.

### 프라이빗 IP 제외

GeoIP 조회에서 `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`로 프라이빗/예약 IP를 제외. 외부 API 호출 낭비 방지.

---

## 주의사항

1. **ip-api.com 제한**: 무료 플랜은 HTTP 전용 (HTTPS 미지원), 분당 45회 제한. 프로덕션에서는 로컬 MMDB 또는 유료 API 사용 권장.

2. **캐시 의존**: `Cat\Cache`가 로드되지 않으면 매 요청마다 GeoIP API를 호출한다. 반드시 캐시를 활성화하라.

3. **trusted_proxies 빈 배열**: 기본값은 하위 호환을 위해 모든 프록시를 신뢰한다. 보안을 위해 반드시 프록시 IP를 명시 설정.

4. **CloudFlare 우선**: CloudFlare 환경에서는 `CF-Connecting-IP`가 `X-Forwarded-For`보다 우선 참조된다.

5. **GeoIP 타임아웃**: 외부 API 호출은 3초 타임아웃. 네트워크 문제 시 `null` 반환.

---

## 연관 도구

- [Firewall](Firewall.md) — IP 차단/허용 (`ip()->address()` 내부 사용)
- [Request](Request.md) — `request()->ip()` → Ip 도구 위임
- [Guard](Guard.md) — 공격 감지 시 IP 로깅
- [Geo](Geo.md) — 지역화 (IP 기반 언어/통화)
- [Rate](Rate.md) — IP 기반 속도 제한
