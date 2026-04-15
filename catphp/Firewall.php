<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Firewall — IP 차단/허용
 *
 * @config array{
 *     path: string,       // 차단 목록 저장 디렉토리
 *     auto_ban?: bool,    // 자동 차단 활성화 (기본 true)
 * } firewall  → config('firewall.path')
 */
final class Firewall
{
    private static ?self $instance = null;

    /** @var array<string, int> 차단 IP 목록 [ip => timestamp] */
    private array $banned = [];

    /** @var array<string, bool> 허용 IP/CIDR 목록 */
    private array $allowed = [];

    private bool $loaded = false;

    private function __construct(
        private readonly string $path,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            path: \config('firewall.path') ?? __DIR__ . '/../storage/firewall',
        );
    }

    /** 저장소 디렉토리 보장 */
    private function ensureDir(): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /** 차단 목록 파일 경로 */
    private function bannedFile(): string
    {
        return $this->path . '/banned.json';
    }

    /** 차단 목록 로드 (지연, flock 공유 락) */
    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        $file = $this->bannedFile();
        if (!is_file($file)) {
            return;
        }

        $fp = fopen($file, 'r');
        if ($fp === false) {
            return;
        }
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($raw !== false && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $this->banned = $data;
            }
        }
    }

    /** 차단 목록 저장 */
    private function save(): void
    {
        $this->ensureDir();
        file_put_contents(
            $this->bannedFile(),
            json_encode($this->banned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /** IP 허용 등록 */
    public function allow(string $ipOrCidr): self
    {
        $this->allowed[$ipOrCidr] = true;
        return $this;
    }

    /** IP/CIDR 차단 등록 */
    public function deny(string $ipOrCidr): self
    {
        return $this->ban($ipOrCidr);
    }

    /** IP/CIDR 차단 */
    public function ban(string $ipOrCidr, ?string $reason = null): self
    {
        self::validateIpOrCidr($ipOrCidr);
        $this->atomicUpdate(function () use ($ipOrCidr): void {
            $this->banned[$ipOrCidr] = time();
        });

        if ($reason !== null && class_exists('Cat\\Log', false)) {
            \logger()->warn("IP 차단: {$ipOrCidr} — {$reason}");
        }

        return $this;
    }

    /** IP/CIDR 차단 해제 */
    public function unban(string $ipOrCidr): self
    {
        self::validateIpOrCidr($ipOrCidr);
        $this->atomicUpdate(function () use ($ipOrCidr): void {
            unset($this->banned[$ipOrCidr]);
        });
        return $this;
    }

    /** 원자적 read-modify-write (flock 배타 락) */
    private function atomicUpdate(callable $modifier): void
    {
        $this->ensureDir();
        $file = $this->bannedFile();

        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return;
        }

        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        if ($raw !== false && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $this->banned = $data;
            }
        }

        $modifier();

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($this->banned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);

        $this->loaded = true;
    }

    /** IP 또는 CIDR 형식 검증 */
    private static function validateIpOrCidr(string $value): void
    {
        // 개별 IP
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return;
        }

        // CIDR (192.168.0.0/24, 2001:db8::/32)
        if (str_contains($value, '/')) {
            [$subnet, $bits] = explode('/', $value, 2);
            if (filter_var($subnet, FILTER_VALIDATE_IP) && ctype_digit($bits)) {
                $maxBits = str_contains($subnet, ':') ? 128 : 32;
                if ((int) $bits >= 0 && (int) $bits <= $maxBits) {
                    return;
                }
            }
        }

        throw new \InvalidArgumentException("유효하지 않은 IP/CIDR: {$value}");
    }

    /** IP 허용 여부 확인 */
    public function isAllowed(string $ip): bool
    {
        // 허용 목록에 있으면 항상 허용
        if (array_key_exists($ip, $this->allowed)) {
            return true;
        }

        // CIDR 허용 확인
        foreach (array_keys($this->allowed) as $cidr) {
            if (str_contains($cidr, '/') && $this->isInCidr($ip, $cidr)) {
                return true;
            }
        }

        return !$this->isDenied($ip);
    }

    /** IP 차단 여부 확인 (개별 IP + CIDR 범위 매칭) */
    public function isDenied(string $ip): bool
    {
        $this->load();

        // 정확 매칭 (O(1))
        if (isset($this->banned[$ip])) {
            return true;
        }

        // CIDR 범위 매칭
        foreach (array_keys($this->banned) as $entry) {
            if (str_contains($entry, '/') && $this->isInCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /** 차단 IP 목록 반환 */
    public function bannedList(): array
    {
        $this->load();
        $list = [];
        foreach ($this->banned as $ip => $timestamp) {
            $list[] = ['ip' => $ip, 'banned_at' => date('Y-m-d H:i:s', $timestamp)];
        }
        return $list;
    }

    /** 미들웨어: 차단 IP 요청 거부 */
    public function middleware(): callable
    {
        return function (): ?bool {
            $ip = \ip()->address();
            if ($this->isDenied($ip) && !$this->isAllowed($ip)) {
                \json()->forbidden();
            }
            return null;
        };
    }

    /** CIDR 범위 포함 확인 (IPv4 + IPv6) */
    private function isInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $bitsInt = (int) $bits;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // 주소 길이 불일치 (IPv4 vs IPv6)
        if (strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        // 바이너리 마스크 생성 + 비교
        $mask = str_repeat("\xff", intdiv($bitsInt, 8));
        $remainder = $bitsInt % 8;
        if ($remainder > 0) {
            $mask .= chr(0xff << (8 - $remainder) & 0xff);
        }
        $mask = str_pad($mask, strlen($ipBin), "\x00");

        return ($ipBin & $mask) === ($subnetBin & $mask);
    }
}
