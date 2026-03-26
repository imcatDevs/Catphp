<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Mail — SMTP 이메일 발송
 *
 * 순수 소켓 기반 SMTP 클라이언트. 외부 의존성 없음.
 *
 * 사용법:
 *   mail()->to('user@example.com')
 *         ->subject('제목')
 *         ->body('<h1>안녕하세요</h1>')
 *         ->send();
 *
 *   mail()->to('a@b.com')->cc('c@d.com')
 *         ->attach('/path/to/file.pdf')
 *         ->template('welcome', ['name' => 'Alice'])
 *         ->send();
 */
final class Mail
{
    private static ?self $instance = null;

    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUser;
    private string $smtpPass;
    private string $encryption; // tls, ssl, none
    private string $fromEmail;
    private string $fromName;

    // 빌더 상태 (send 후 초기화)
    /** @var string[] */
    private array $to = [];
    /** @var string[] */
    private array $cc = [];
    /** @var string[] */
    private array $bcc = [];
    private string $subjectText = '';
    private string $bodyHtml = '';
    private string $bodyPlain = '';
    private ?string $replyTo = null;
    /** @var array<int, array{path: string, name: string, mime: string}> */
    private array $attachments = [];

    private function __construct()
    {
        $this->smtpHost   = (string) config('mail.host', 'localhost');
        $this->smtpPort   = (int) config('mail.port', 587);
        $this->smtpUser   = (string) config('mail.username', '');
        $this->smtpPass   = (string) config('mail.password', '');
        $this->encryption = (string) config('mail.encryption', 'tls');
        $this->fromEmail  = (string) config('mail.from_email', '');
        $this->fromName   = (string) config('mail.from_name', 'CatPHP');
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 빌더 메서드 (체이닝) ──

    public function to(string ...$emails): self
    {
        $c = clone $this;
        foreach ($emails as $email) {
            $c->to[] = self::sanitizeEmail($email);
        }
        return $c;
    }

    public function cc(string ...$emails): self
    {
        $c = clone $this;
        foreach ($emails as $email) {
            $c->cc[] = self::sanitizeEmail($email);
        }
        return $c;
    }

    public function bcc(string ...$emails): self
    {
        $c = clone $this;
        foreach ($emails as $email) {
            $c->bcc[] = self::sanitizeEmail($email);
        }
        return $c;
    }

    public function replyTo(string $email): self
    {
        $c = clone $this;
        $c->replyTo = self::sanitizeEmail($email);
        return $c;
    }

    public function subject(string $subject): self
    {
        $c = clone $this;
        $c->subjectText = $subject;
        return $c;
    }

    /** HTML 본문 (자동으로 text/plain 버전도 생성) */
    public function body(string $html): self
    {
        $c = clone $this;
        $c->bodyHtml = $html;
        $c->bodyPlain = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        return $c;
    }

    /** 순수 텍스트 본문 */
    public function text(string $plain): self
    {
        $c = clone $this;
        $c->bodyPlain = $plain;
        return $c;
    }

    /** 뷰 템플릿 기반 본문 */
    public function template(string $name, array $data = []): self
    {
        return $this->body(\render($name, $data));
    }

    /** 파일 첨부 */
    public function attach(string $path, ?string $name = null, ?string $mime = null): self
    {
        if (!is_file($path)) {
            throw new \RuntimeException("첨부 파일 없음: {$path}");
        }
        $c = clone $this;
        $c->attachments[] = [
            'path' => $path,
            'name' => $name ?? basename($path),
            'mime' => $mime ?? (mime_content_type($path) ?: 'application/octet-stream'),
        ];
        return $c;
    }

    // ── 발송 ──

    /** 이메일 발송 (clone 기반 — 싱글턴 상태 오염 없음) */
    public function send(): bool
    {
        if (empty($this->to)) {
            throw new \RuntimeException('수신자(to)를 지정하세요.');
        }
        if ($this->fromEmail === '') {
            throw new \RuntimeException('mail.from_email 설정이 필요합니다.');
        }

        return $this->sendSmtp();
    }

    /** 발송 없이 MIME 메시지 문자열 반환 (디버그용) */
    public function preview(): string
    {
        $mime = $this->buildMime();
        return $mime['headers'] . "\r\n\r\n" . $mime['body'];
    }

    // ── 내부 로직 ──

    /** 이메일 주소 CRLF 인젝션 방어 */
    private static function sanitizeEmail(string $email): string
    {
        $email = str_replace(["\r", "\n", "\0"], '', $email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("유효하지 않은 이메일: {$email}");
        }
        return $email;
    }

    /**
     * @return array{headers: string, body: string}
     */
    private function buildMime(): array
    {
        $boundary = '----CatPHP_' . bin2hex(random_bytes(16));
        $headers = [];

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: ' . $this->formatAddress($this->fromEmail, $this->fromName);
        $headers[] = 'To: ' . implode(', ', $this->to);
        if (!empty($this->cc)) {
            $headers[] = 'Cc: ' . implode(', ', $this->cc);
        }
        if ($this->replyTo !== null) {
            $headers[] = 'Reply-To: ' . $this->replyTo;
        }
        $headers[] = 'Subject: ' . $this->encodeHeader($this->subjectText);
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->extractDomain($this->fromEmail) . '>';

        $body = '';

        if (!empty($this->attachments)) {
            // multipart/mixed (본문 + 첨부)
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            $altBoundary = '----CatPHP_Alt_' . bin2hex(random_bytes(8));

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
            $body .= $this->buildAlternativeBody($altBoundary);
            $body .= "\r\n";

            foreach ($this->attachments as $att) {
                $safeName = self::sanitizeMimeValue($att['name']);
                $safeMime = self::sanitizeMimeValue($att['mime']);
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: {$safeMime}; name=\"{$safeName}\"\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$safeName}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode(file_get_contents($att['path'])));
            }
            $body .= "--{$boundary}--\r\n";
        } elseif ($this->bodyHtml !== '') {
            // multipart/alternative (HTML + 텍스트)
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $body = $this->buildAlternativeBody($boundary);
        } else {
            // 텍스트만
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($this->bodyPlain));
        }

        return ['headers' => implode("\r\n", $headers), 'body' => $body];
    }

    private function buildAlternativeBody(string $boundary): string
    {
        $body = '';
        if ($this->bodyPlain !== '') {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($this->bodyPlain));
        }
        if ($this->bodyHtml !== '') {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($this->bodyHtml));
        }
        $body .= "--{$boundary}--\r\n";
        return $body;
    }

    private function sendSmtp(): bool
    {
        $host = $this->smtpHost;
        $port = $this->smtpPort;

        if ($this->encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            throw new \RuntimeException("SMTP 연결 실패: {$errstr} ({$errno})");
        }

        try {
            $this->smtpRead($socket, 220);
            $this->smtpWrite($socket, "EHLO " . gethostname(), 250);

            // STARTTLS
            if ($this->encryption === 'tls') {
                $this->smtpWrite($socket, "STARTTLS", 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('STARTTLS 핸드셰이크 실패');
                }
                $this->smtpWrite($socket, "EHLO " . gethostname(), 250);
            }

            // 인증
            if ($this->smtpUser !== '') {
                $this->smtpWrite($socket, "AUTH LOGIN", 334);
                $this->smtpWrite($socket, base64_encode($this->smtpUser), 334);
                $this->smtpWrite($socket, base64_encode($this->smtpPass), 235);
            }

            // 발신/수신
            $this->smtpWrite($socket, "MAIL FROM:<{$this->fromEmail}>", 250);

            $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
            foreach ($allRecipients as $rcpt) {
                $this->smtpWrite($socket, "RCPT TO:<{$rcpt}>", 250);
            }

            // 데이터
            $this->smtpWrite($socket, "DATA", 354);
            $mime = $this->buildMime();
            fwrite($socket, $mime['headers'] . "\r\n\r\n" . $mime['body'] . "\r\n.\r\n");
            $this->smtpRead($socket, 250);

            $this->smtpWrite($socket, "QUIT", 221);

            return true;
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     */
    private function smtpWrite($socket, string $command, int $expectedCode): void
    {
        fwrite($socket, $command . "\r\n");
        $this->smtpRead($socket, $expectedCode);
    }

    /**
     * @param resource $socket
     */
    private function smtpRead($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // 4번째 문자가 공백이면 마지막 줄
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP 에러 (기대: {$expectedCode}, 응답: {$code}): " . trim($response));
        }
        return $response;
    }

    private function formatAddress(string $email, string $name = ''): string
    {
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . " <{$email}>";
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function extractDomain(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? 'localhost';
    }

    /** MIME 헤더 값 살균 (CRLF/NULL/따옴표 인젝션 방어) */
    private static function sanitizeMimeValue(string $value): string
    {
        return str_replace(['"', "\r", "\n", "\0"], '', $value);
    }
}
