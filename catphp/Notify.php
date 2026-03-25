<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Notify — 다채널 알림
 *
 * Mail, Telegram, 커스텀 채널을 통합 관리.
 *
 * 사용법:
 *   notify()->to('user@example.com')->via('mail')->send('제목', '본문');
 *   notify()->to('@chat_id')->via('telegram')->send('알림 메시지');
 *   notify()->to('user@a.com')->via('mail', 'telegram')->send('제목', '본문');
 *   notify()->channel('slack', fn(string $to, string $subject, string $body) => ...);
 */
final class Notify
{
    private static ?self $instance = null;

    /** @var string[] 수신자 */
    private array $recipients = [];

    /** @var string[] 사용할 채널 */
    private array $channels = [];

    /** @var array<string, callable> 커스텀 채널 핸들러 */
    private static array $customChannels = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    // ── 빌더 ──

    /** 수신자 설정 */
    public function to(string ...$recipients): self
    {
        $c = clone $this;
        $c->recipients = array_merge($c->recipients, $recipients);
        return $c;
    }

    /** 채널 선택 */
    public function via(string ...$channels): self
    {
        $c = clone $this;
        $c->channels = array_merge($c->channels, $channels);
        return $c;
    }

    /** 커스텀 채널 등록 (전역) */
    public static function channel(string $name, callable $handler): void
    {
        self::$customChannels[$name] = $handler;
    }

    // ── 발송 ──

    /**
     * 알림 발송
     *
     * @return array<string, bool> 채널별 성공 여부
     */
    public function send(string $subject, string $body = ''): array
    {
        if (empty($this->recipients)) {
            throw new \RuntimeException('수신자(to)를 지정하세요.');
        }
        if (empty($this->channels)) {
            throw new \RuntimeException('채널(via)을 지정하세요.');
        }

        $results = [];

        foreach ($this->channels as $channel) {
            try {
                $results[$channel] = match ($channel) {
                    'mail'     => $this->sendMail($subject, $body),
                    'telegram' => $this->sendTelegram($subject, $body),
                    default    => $this->sendCustom($channel, $subject, $body),
                };
            } catch (\Throwable $e) {
                $results[$channel] = false;
                if (class_exists(\Cat\Log::class)) {
                    cat('Log')->error("Notify [{$channel}] 실패", [
                        'error'      => $e->getMessage(),
                        'recipients' => $this->recipients,
                    ]);
                }
            }
        }

        return $results;
    }

    /** 간편 발송 (제목만, 모든 설정된 채널) */
    public function alert(string $message): array
    {
        $c = clone $this;
        if (empty($c->channels)) {
            // 기본 채널 자동 선택
            if (config('mail.host')) {
                $c->channels[] = 'mail';
            }
            if (config('telegram.bot_token')) {
                $c->channels[] = 'telegram';
            }
        }

        return $c->send($message);
    }

    // ── 내부 로직 ──

    private function sendMail(string $subject, string $body): bool
    {
        if (!class_exists(\Cat\Mail::class)) {
            throw new \RuntimeException('Cat\\Mail 도구가 필요합니다.');
        }

        $sent = 0;
        $base = cat('Mail')->subject($subject)->body($body);

        // Mail::to()는 이뮤터블(clone) → 수신자별 개별 발송
        foreach ($this->recipients as $to) {
            if (str_contains($to, '@')) {
                if ($base->to($to)->send()) {
                    $sent++;
                }
            }
        }

        return $sent > 0;
    }

    private function sendTelegram(string $subject, string $body): bool
    {
        if (!class_exists(\Cat\Telegram::class)) {
            throw new \RuntimeException('Cat\\Telegram 도구가 필요합니다.');
        }

        $text = $body !== '' ? "<b>{$subject}</b>\n{$body}" : $subject;
        $sent = 0;

        foreach ($this->recipients as $to) {
            // @로 시작하면 텔레그램 chat_id, 이메일(@포함)은 건너뛰기
            if (str_starts_with($to, '@')) {
                $chatId = substr($to, 1);
            } elseif (str_contains($to, '@')) {
                // 이메일 주소 — 텔레그램 대상 아님
                continue;
            } else {
                // 순수 숫자 chat_id 등
                $chatId = $to;
            }

            if ($chatId !== '' && cat('Telegram')->to($chatId)->html($text)->send()) {
                $sent++;
            }
        }

        return $sent > 0;
    }

    private function sendCustom(string $channel, string $subject, string $body): bool
    {
        $handler = self::$customChannels[$channel]
            ?? throw new \RuntimeException("알 수 없는 알림 채널: {$channel}");

        $sent = 0;
        foreach ($this->recipients as $to) {
            try {
                $result = $handler($to, $subject, $body);
                // 핸들러가 false를 명시적으로 반환하지 않으면 성공으로 간주
                if ($result !== false) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                if (class_exists(\Cat\Log::class)) {
                    cat('Log')->error("Notify custom [{$channel}] 실패: {$to}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $sent > 0;
    }

}
