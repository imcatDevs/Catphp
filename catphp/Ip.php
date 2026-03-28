<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Ip — IP 정보 + GeoIP
 *
 * @config array{
 *     provider?: string,    // 'api' | 'mmdb' (기본 api)
 *     mmdb_path?: string,   // MaxMind .mmdb 파일 경로
 *     cache_ttl?: int,      // GeoIP 캐시 TTL (기본 86400)
 * } ip  → config('ip.provider')
 */
final class Ip
{
    private static ?self $instance = null;

    private function __construct(
        private readonly string $provider,
        private readonly ?string $mmdbPath,
        private readonly int $cacheTtl,
        private readonly array $trustedProxies,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            provider: \config('ip.provider') ?? 'api',
            mmdbPath: \config('ip.mmdb_path'),
            cacheTtl: (int) (\config('ip.cache_ttl') ?? 86400),
            trustedProxies: \config('ip.trusted_proxies') ?? [],
        );
    }

    /** 클라이언트 IP 감지 (신뢰 프록시에서만 헤더 사용) */
    public function address(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $isTrusted = $this->isTrustedProxy($remoteAddr);

        // CloudFlare (신뢰 프록시일 때만)
        if ($isTrusted && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $this->validateIp($_SERVER['HTTP_CF_CONNECTING_IP']);
            if ($ip !== null) {
                return $ip;
            }
        }
        // X-Forwarded-For (첫 번째 IP, 신뢰 프록시일 때만)
        if ($isTrusted && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = $this->validateIp(trim($ips[0]));
            if ($ip !== null) {
                return $ip;
            }
        }
        // X-Real-IP (신뢰 프록시일 때만)
        if ($isTrusted && !empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $this->validateIp($_SERVER['HTTP_X_REAL_IP']);
            if ($ip !== null) {
                return $ip;
            }
        }
        return $this->validateIp($remoteAddr) ?? '127.0.0.1';
    }

    /** IP 주소 형식 검증 (IPv4/IPv6) — 위조된 헤더의 XSS/로그 인젝션 차단 */
    private function validateIp(string $ip): ?string
    {
        $ip = trim($ip);
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    /** 신뢰 프록시 확인 */
    private function isTrustedProxy(string $ip): bool
    {
        // 빈 목록이면 모든 프록시 신뢰 (하위 호환) — ⚠ 보안 경고
        if (empty($this->trustedProxies)) {
            // 운영 환경에서는 반드시 ip.trusted_proxies 설정 필요
            static $warned = false;
            if (!$warned && class_exists('Cat\\Log', false)) {
                \logger()->warn('ip.trusted_proxies 미설정: 모든 프록시 신뢰 (IP 스푸핑 위험)');
                $warned = true;
            }
            return true;
        }
        return in_array($ip, $this->trustedProxies, true);
    }

    /** 국가 코드 (예: 'KR') */
    public function country(?string $ip = null): ?string
    {
        $info = $this->info($ip);
        return $info['country'] ?? null;
    }

    /** 도시명 */
    public function city(?string $ip = null): ?string
    {
        $info = $this->info($ip);
        return $info['city'] ?? null;
    }

    /** 위치 정보 (위도/경도) */
    public function location(?string $ip = null): ?array
    {
        $info = $this->info($ip);
        if (isset($info['lat'], $info['lon'])) {
            return ['lat' => $info['lat'], 'lon' => $info['lon']];
        }
        return null;
    }

    /** IP 범위 포함 확인 (CIDR, IPv4 + IPv6) */
    public function isInRange(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr);
        $bitsInt = (int) $bits;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $mask = str_repeat("\xff", intdiv($bitsInt, 8));
        $remainder = $bitsInt % 8;
        if ($remainder > 0) {
            $mask .= chr(0xff << (8 - $remainder) & 0xff);
        }
        $mask = str_pad($mask, strlen($ipBin), "\x00");

        return ($ipBin & $mask) === ($subnetBin & $mask);
    }

    /** GeoIP 전체 정보 */
    public function info(?string $ip = null): array
    {
        $ip ??= $this->address();

        // 프라이빗 IP 체크
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['ip' => $ip, 'country' => null, 'city' => null];
        }

        // 캐시 확인
        if (class_exists('Cat\\Cache', false)) {
            $cached = \cache()->get("geoip:{$ip}");
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->fetchGeoData($ip);

        // 캐시 저장
        if (class_exists('Cat\\Cache', false) && !empty($result['country'])) {
            \cache()->set("geoip:{$ip}", $result, $this->cacheTtl);
        }

        return $result;
    }

    /** GeoIP 데이터 조회 (API 폴백) */
    private function fetchGeoData(string $ip): array
    {
        // API 방식 (ip-api.com 무료 — HTTP 전용, HTTPS는 Pro 플랜 필요)
        // ⚠ 프로덕션에서는 HTTPS 지원 GeoIP API 또는 로컬 MMDB 사용 권장
        $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,lat,lon";

        $context = stream_context_create([
            'http' => ['timeout' => 3, 'ignore_errors' => true],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['ip' => $ip, 'country' => null, 'city' => null];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return ['ip' => $ip, 'country' => null, 'city' => null];
        }

        return [
            'ip'      => $ip,
            'country' => $data['countryCode'] ?? null,
            'city'    => $data['city'] ?? null,
            'lat'     => $data['lat'] ?? null,
            'lon'     => $data['lon'] ?? null,
        ];
    }
}
