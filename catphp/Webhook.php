<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Webhook — Webhook 발송/수신 + HMAC 서명 검증
 *
 * 사용법:
 *   // 발송
 *   webhook()->to('https://example.com/hook')
 *            ->payload(['event' => 'user.created', 'data' => $user])
 *            ->send();
 *
 *   webhook()->to('https://...')
 *            ->secret('my-secret')
 *            ->payload($data)
 *            ->send();
 *
 *   // 수신 검증
 *   $wh = webhook()->receive();
 *   if ($wh->isValid('my-secret')) {
 *       $data = $wh->getPayload();
 *   }
 *
 * @config array{
 *     secret?: string,     // 기본 HMAC 시크릿
 *     timeout?: int,       // HTTP 타임아웃 (초, 기본 10)
 *     retry?: int,         // 실패 시 재시도 횟수 (기본 0)
 *     retry_delay?: int,   // 재시도 간 대기 (초, 기본 1)
 *     log?: bool,          // 발송/수신 로깅 (기본 false)
 * } webhook  → config('webhook.secret')
 */
final class Webhook
{
    private static ?self $instance = null;

    private string $defaultSecret;
    private int $timeout;
    private int $retry;
    private int $retryDelay;
    private bool $logEnabled;

    // ── 발송 빌더 상태 ──
    private string $targetUrl = '';
    private ?string $secretKey = null;
    /** @var array<string, mixed> */
    private array $payloadData = [];
    /** @var array<string, string> */
    private array $customHeaders = [];

    // ── 수신 상태 ──
    private ?string $receivedBody = null;
    private ?string $receivedSignature = null;

    private function __construct()
    {
        $this->defaultSecret = (string) \config('webhook.secret', '');
        $this->timeout = (int) \config('webhook.timeout', 10);
        $this->retry = (int) \config('webhook.retry', 0);
        $this->retryDelay = (int) \config('webhook.retry_delay', 1);
        $this->logEnabled = (bool) \config('webhook.log', false);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 발송 빌더 (이뮤터블 체이닝) ──

    /** 대상 URL 설정 (http/https만 허용) */
    public function to(string $url): self
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            throw new \InvalidArgumentException("Webhook URL은 http 또는 https만 허용됩니다: {$url}");
        }
        $c = clone $this;
        $c->targetUrl = $url;
        return $c;
    }

    /** HMAC 시크릿 설정 (발송 시 서명 자동 첨부) */
    public function secret(string $secret): self
    {
        $c = clone $this;
        $c->secretKey = $secret;
        return $c;
    }

    /**
     * 페이로드 설정
     *
     * @param array<string, mixed> $data
     */
    public function payload(array $data): self
    {
        $c = clone $this;
        $c->payloadData = $data;
        return $c;
    }

    /** 커스텀 헤더 추가 */
    public function header(string $name, string $value): self
    {
        $c = clone $this;
        // CRLF 인젝션 방어
        $c->customHeaders[str_replace(["\r", "\n", "\0"], '', $name)] = str_replace(["\r", "\n", "\0"], '', $value);
        return $c;
    }

    /**
     * Webhook 발송
     *
     * @return WebhookResult 발송 결과
     */
    public function send(): WebhookResult
    {
        if ($this->targetUrl === '') {
            throw new \RuntimeException('Webhook 대상 URL이 지정되지 않았습니다.');
        }

        $body = json_encode($this->payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = '{}';
        }

        $secret = $this->secretKey ?? $this->defaultSecret;
        $signature = '';
        if ($secret !== '') {
            $signature = $this->sign($body, $secret);
        }

        $maxAttempts = max(1, $this->retry + 1);
        $lastResult = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastResult = $this->doSend($body, $signature, $attempt);

            if ($lastResult->ok()) {
                break;
            }

            // 마지막 시도가 아니면 대기 후 재시도
            if ($attempt < $maxAttempts && $this->retryDelay > 0) {
                sleep($this->retryDelay);
            }
        }

        // 로깅
        if ($this->logEnabled && class_exists('Cat\\Log', false)) {
            $status = $lastResult->ok() ? 'OK' : 'FAIL';
            \logger()->info("Webhook {$status}: {$this->targetUrl}", [
                'status'   => $lastResult->status(),
                'attempts' => $lastResult->attempts(),
            ]);
        }

        return $lastResult;
    }

    // ── 수신 ──

    /**
     * 수신된 Webhook 페이로드 읽기
     *
     * php://input에서 body를 읽고, X-Webhook-Signature 헤더를 파싱.
     */
    public function receive(): self
    {
        $c = clone $this;
        $raw = file_get_contents('php://input');
        $c->receivedBody = $raw === false ? '' : $raw;
        $c->receivedSignature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        return $c;
    }

    /**
     * 수신된 Webhook 서명 검증
     *
     * @param string|null $secret 검증용 시크릿 (null이면 config 기본값)
     */
    public function isValid(?string $secret = null): bool
    {
        if ($this->receivedBody === null) {
            return false;
        }

        $secret ??= $this->defaultSecret;
        if ($secret === '') {
            return false;
        }

        $sig = $this->receivedSignature ?? '';
        if ($sig === '') {
            return false;
        }

        // "sha256=..." 접두사 처리 (GitHub 스타일)
        $expected = $this->sign($this->receivedBody, $secret);
        $actual = str_starts_with($sig, 'sha256=') ? substr($sig, 7) : $sig;

        return hash_equals($expected, $actual);
    }

    /**
     * 수신된 페이로드 반환 (JSON 디코딩)
     *
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        if ($this->receivedBody === null || $this->receivedBody === '') {
            return null;
        }

        // JSON DoS 방어: 크기 1MB + 깊이 32 제한 (Request.php와 동일)
        if (strlen($this->receivedBody) > 1_048_576) {
            return null;
        }

        $data = json_decode($this->receivedBody, true, 32);
        return is_array($data) ? $data : null;
    }

    /** 수신된 원본 body 반환 */
    public function getRawBody(): string
    {
        return $this->receivedBody ?? '';
    }

    // ── 유틸 ──

    /** HMAC-SHA256 서명 생성 */
    public function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /** 서명 헤더 문자열 생성 (sha256=xxx) */
    public function signatureHeader(string $payload, string $secret): string
    {
        return 'sha256=' . $this->sign($payload, $secret);
    }

    // ── 내부 ──

    /** HTTP POST 발송 (cURL) */
    private function doSend(string $body, string $signature, int $attempt): WebhookResult
    {
        $ch = curl_init($this->targetUrl);
        if ($ch === false) {
            return new WebhookResult(0, 'cURL 초기화 실패', $attempt);
        }

        $headers = [
            'Content-Type: application/json',
            'User-Agent: CatPHP-Webhook/1.0',
        ];

        if ($signature !== '') {
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        }

        foreach ($this->customHeaders as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return new WebhookResult(0, $error, $attempt);
        }

        return new WebhookResult($statusCode, (string) $responseBody, $attempt);
    }
}

/**
 * Webhook 발송 결과 값 객체
 */
final class WebhookResult
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly int $attemptCount,
    ) {}

    /** HTTP 상태 코드 */
    public function status(): int
    {
        return $this->statusCode;
    }

    /** 응답 body */
    public function body(): string
    {
        return $this->body;
    }

    /** 성공 여부 (2xx) */
    public function ok(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** 시도 횟수 */
    public function attempts(): int
    {
        return $this->attemptCount;
    }

    /** JSON 디코딩 */
    public function json(): ?array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }
}
