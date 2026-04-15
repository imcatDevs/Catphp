<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Storage — 파일시스템 추상화 (로컬 / S3)
 *
 * 사용법:
 *   storage()->put('uploads/file.txt', $content);
 *   storage()->get('uploads/file.txt');
 *   storage()->delete('uploads/file.txt');
 *   storage()->url('uploads/file.txt');
 *   storage()->disk('s3')->put('backups/db.sql', $dump);
 */
final class Storage
{
    private static ?self $instance = null;

    private string $defaultDisk;

    /** @var array<string, array<string, mixed>> 디스크 설정 */
    private array $disks;

    /** 현재 디스크 (체이닝용) */
    private ?string $currentDisk = null;

    /** 경로 트래버설 방어 — root 밖 접근 차단 (세그먼트 기반 정규화) */
    private function safePath(string $path): string
    {
        $path = str_replace(['\\', "\0"], ['/', ''], $path);

        // 세그먼트 기반 정규화 — '..' 및 '.' 세그먼트 제거
        $segments = explode('/', $path);
        $safe = [];
        foreach ($segments as $seg) {
            if ($seg === '..' ) {
                // root 밖으로 나가면 차단
                if (empty($safe)) {
                    throw new \RuntimeException("경로 트래버설 차단: {$path}");
                }
                array_pop($safe);
            } elseif ($seg !== '' && $seg !== '.') {
                $safe[] = $seg;
            }
        }

        $normalized = implode('/', $safe);
        if ($normalized === '') {
            throw new \RuntimeException("경로 트래버설 차단: 유효하지 않은 경로");
        }

        // realpath 기반 이중 검증 (디렉토리가 존재하는 경우)
        $root = realpath($this->diskConfig()['root'] ?? '') ?: ($this->diskConfig()['root'] ?? '');
        $full = $root . '/' . $normalized;
        $realDir = realpath(dirname($full));
        if ($realDir !== false) {
            $realRoot = realpath($root);
            if ($realRoot !== false && !str_starts_with($realDir, $realRoot)) {
                throw new \RuntimeException("경로 트래버설 차단: {$path}");
            }
        }

        return $normalized;
    }

    private function __construct()
    {
        $this->defaultDisk = (string) config('storage.default', 'local');
        $this->disks = (array) config('storage.disks', [
            'local' => ['driver' => 'local', 'root' => __DIR__ . '/../storage/app'],
            'public' => ['driver' => 'local', 'root' => __DIR__ . '/../Public/uploads', 'url' => '/uploads'],
        ]);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 디스크 선택 (이뮤터블 — 연속 호출 안전) */
    public function disk(string $name): self
    {
        if (!isset($this->disks[$name])) {
            throw new \RuntimeException("디스크 미설정: {$name}");
        }
        $c = clone $this;
        $c->currentDisk = $name;
        return $c;
    }

    /** 현재 디스크 설정 */
    private function diskConfig(): array
    {
        $name = $this->currentDisk ?? $this->defaultDisk;
        return $this->disks[$name] ?? throw new \RuntimeException("디스크 미설정: {$name}");
    }

    // ── 파일 읽기/쓰기 ──

    /** 파일 저장 */
    public function put(string $path, string $content): bool
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $driver = $cfg['driver'] ?? 'local';

        if ($driver === 's3') {
            return $this->s3Put($cfg, $path, $content);
        }

        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($fullPath, $content, LOCK_EX) !== false;
    }

    /** 파일 읽기 */
    public function get(string $path): ?string
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $driver = $cfg['driver'] ?? 'local';

        if ($driver === 's3') {
            return $this->s3Get($cfg, $path);
        }

        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        if (!is_file($fullPath)) {
            return null;
        }
        $content = file_get_contents($fullPath);
        return $content !== false ? $content : null;
    }

    /** 파일 존재 여부 */
    public function exists(string $path): bool
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $driver = $cfg['driver'] ?? 'local';

        if ($driver === 's3') {
            return $this->s3Exists($cfg, $path);
        }

        return is_file(rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/'));
    }

    /** 파일 삭제 */
    public function delete(string $path): bool
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $driver = $cfg['driver'] ?? 'local';

        if ($driver === 's3') {
            return $this->s3Delete($cfg, $path);
        }

        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        return is_file($fullPath) && unlink($fullPath);
    }

    /** 파일 복사 */
    public function copy(string $from, string $to): bool
    {
        $content = $this->get($from);
        if ($content === null) {
            return false;
        }
        return $this->put($to, $content);
    }

    /** 파일 이동 */
    public function move(string $from, string $to): bool
    {
        if (!$this->copy($from, $to)) {
            return false;
        }
        return $this->delete($from);
    }

    /** 파일 크기 (바이트) */
    public function size(string $path): int
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $driver = $cfg['driver'] ?? 'local';

        if ($driver === 's3') {
            return $this->s3Size($cfg, $path);
        }

        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        return is_file($fullPath) ? (int) filesize($fullPath) : 0;
    }

    /** 최종 수정 시간 (Unix timestamp) */
    public function lastModified(string $path): int
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        return is_file($fullPath) ? (int) filemtime($fullPath) : 0;
    }

    /** 파일 MIME 타입 */
    public function mimeType(string $path): ?string
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        if (!is_file($fullPath)) {
            return null;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $fullPath);
        finfo_close($finfo);
        return $mime ?: null;
    }

    /** 공개 URL 반환 */
    public function url(string $path): string
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $driver = $cfg['driver'] ?? 'local';

        if ($driver === 's3') {
            $region = $cfg['region'] ?? 'us-east-1';
            $bucket = $cfg['bucket'] ?? '';
            return "https://{$bucket}.s3.{$region}.amazonaws.com/" . ltrim($path, '/');
        }

        $baseUrl = $cfg['url'] ?? '';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /** 디렉토리 목록 (경로 트래버설 방어 포함) */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $cfg = $this->diskConfig();
        $root = rtrim($cfg['root'] ?? '', '/\\');
        $directory = $directory !== '' ? $this->safePath($directory) : '';
        $dir = $root . '/' . ltrim($directory, '/');

        if (!is_dir($dir)) {
            return [];
        }

        $result = [];
        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $result[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        } else {
            foreach (scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $fullPath = $dir . '/' . $item;
                if (is_file($fullPath)) {
                    $relative = $directory ? $directory . '/' . $item : $item;
                    $result[] = $relative;
                }
            }
        }

        sort($result);
        return $result;
    }

    /** 디렉토리 생성 */
    public function makeDirectory(string $path): bool
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        if (is_dir($fullPath)) {
            return true;
        }
        return mkdir($fullPath, 0755, true);
    }

    /** 디렉토리 삭제 (재귀) */
    public function deleteDirectory(string $path): bool
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        if (!is_dir($fullPath)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        return rmdir($fullPath);
    }

    /** 파일 스트리밍 (대용량 다운로드) */
    public function stream(string $path, ?string $downloadName = null): void
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');

        if (!is_file($fullPath)) {
            throw new \RuntimeException("파일 없음: {$path}");
        }

        $mime = $this->mimeType($path) ?? 'application/octet-stream';
        $rawName = $downloadName ?? basename($path);
        $safeName = rawurlencode(str_replace(["\r", "\n", "\0"], '', $rawName));
        $size = filesize($fullPath);

        header("Content-Type: {$mime}");
        header("Content-Disposition: attachment; filename=\"{$safeName}\"");
        header("Content-Length: {$size}");
        header('Cache-Control: no-cache');

        readfile($fullPath);
    }

    /** 파일 추가 쓰기 (로그 등) */
    public function append(string $path, string $content): bool
    {
        $path = $this->safePath($path);
        $cfg = $this->diskConfig();
        $fullPath = rtrim($cfg['root'] ?? '', '/\\') . '/' . ltrim($path, '/');
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($fullPath, $content, FILE_APPEND | LOCK_EX) !== false;
    }

    // ── S3 드라이버 (cURL 기반 서명 v4) ──

    private function s3Put(array $cfg, string $path, string $content): bool
    {
        $response = $this->s3Request('PUT', $cfg, $path, $content);
        return $response['code'] === 200;
    }

    private function s3Get(array $cfg, string $path): ?string
    {
        $response = $this->s3Request('GET', $cfg, $path);
        return $response['code'] === 200 ? $response['body'] : null;
    }

    private function s3Exists(array $cfg, string $path): bool
    {
        $response = $this->s3Request('HEAD', $cfg, $path);
        return $response['code'] === 200;
    }

    private function s3Delete(array $cfg, string $path): bool
    {
        $response = $this->s3Request('DELETE', $cfg, $path);
        return $response['code'] === 204 || $response['code'] === 200;
    }

    private function s3Size(array $cfg, string $path): int
    {
        $response = $this->s3Request('HEAD', $cfg, $path);
        return (int) ($response['headers']['content-length'] ?? 0);
    }

    /**
     * S3 API 요청 (AWS Signature V4)
     *
     * @return array{code: int, body: string, headers: array<string, string>}
     */
    private function s3Request(string $method, array $cfg, string $path, string $body = ''): array
    {
        $key       = $cfg['key'] ?? '';
        $secret    = $cfg['secret'] ?? '';
        $region    = $cfg['region'] ?? 'us-east-1';
        $bucket    = $cfg['bucket'] ?? '';
        $endpoint  = $cfg['endpoint'] ?? "https://{$bucket}.s3.{$region}.amazonaws.com";

        $path = '/' . ltrim($path, '/');
        $url = rtrim($endpoint, '/') . $path;
        $date = gmdate('Ymd\THis\Z');
        $dateShort = gmdate('Ymd');

        $payloadHash = hash('sha256', $body);
        $headers = [
            'Host'                 => parse_url($url, PHP_URL_HOST),
            'x-amz-date'          => $date,
            'x-amz-content-sha256' => $payloadHash,
        ];
        if ($body !== '') {
            $headers['Content-Length'] = (string) strlen($body);
        }

        // Canonical Request
        ksort($headers);
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headers)));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
        }
        $canonicalRequest = "{$method}\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        // String to Sign
        $scope = "{$dateShort}/{$region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        // Signing Key
        $signingKey = hash_hmac('sha256', 'aws4_request',
            hash_hmac('sha256', 's3',
                hash_hmac('sha256', $region,
                    hash_hmac('sha256', $dateShort, 'AWS4' . $secret, true),
                true),
            true),
        true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$key}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        // cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => array_map(fn($k, $v) => "{$k}: {$v}", array_keys($headers), array_values($headers)),
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $response = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $responseHeaders = [];
        if (is_string($response)) {
            $headerStr = substr($response, 0, $headerSize);
            foreach (explode("\r\n", $headerStr) as $line) {
                if (str_contains($line, ':')) {
                    [$hk, $hv] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($hk))] = trim($hv);
                }
            }
        }

        return [
            'code'    => $code,
            'body'    => is_string($response) ? substr($response, $headerSize) : '',
            'headers' => $responseHeaders,
        ];
    }
}
