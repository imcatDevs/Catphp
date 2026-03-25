<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Telegram — 텔레그램 Bot API
 *
 * @config array{
 *     bot_token: string,    // 텔레그램 봇 토큰
 *     chat_id?: string,     // 기본 채팅 ID
 *     admin_chat?: string,  // 관리자 채팅 ID
 * } telegram  → config('telegram.bot_token')
 */
final class Telegram
{
    private static ?self $instance = null;

    private ?string $chatId = null;
    private ?string $text = null;
    private ?string $parseMode = null;
    private ?string $photoUrl = null;
    private ?string $filePath = null;
    private ?array $keyboard = null;

    private function __construct(
        #[\SensitiveParameter]
        private readonly string $botToken,
        private readonly string $defaultChatId,
    ) {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self(
            botToken: \config('telegram.bot_token') ?? '',
            defaultChatId: \config('telegram.chat_id') ?? '',
        );
    }

    /** 수신자 설정 */
    public function to(string $chatId): self
    {
        $c = clone $this;
        $c->chatId = $chatId;
        return $c;
    }

    /** 일반 메시지 */
    public function message(string $text): self
    {
        $c = clone $this;
        $c->text = $text;
        $c->parseMode = null;
        return $c;
    }

    /** HTML 포맷 메시지 */
    public function html(string $text): self
    {
        $c = clone $this;
        $c->text = $text;
        $c->parseMode = 'HTML';
        return $c;
    }

    /** 마크다운 포맷 메시지 */
    public function markdown(string $text): self
    {
        $c = clone $this;
        $c->text = $text;
        $c->parseMode = 'MarkdownV2';
        return $c;
    }

    /** 사진 발송 */
    public function photo(string $url, ?string $caption = null): self
    {
        $c = clone $this;
        $c->photoUrl = $url;
        $c->text = $caption;
        return $c;
    }

    /** 파일 발송 */
    public function file(string $path, ?string $caption = null): self
    {
        $c = clone $this;
        $c->filePath = $path;
        $c->text = $caption;
        return $c;
    }

    /** 인라인 키보드 */
    public function keyboard(array $buttons): self
    {
        $c = clone $this;
        $c->keyboard = $buttons;
        return $c;
    }

    /** 메시지 전송 */
    public function send(): bool
    {
        $chatId = $this->chatId ?? $this->defaultChatId;

        if ($chatId === '' || $this->botToken === '') {
            return false;
        }

        if ($this->filePath !== null) {
            return $this->apiUpload('sendDocument', $chatId, $this->filePath, 'document', $this->text);
        }

        if ($this->photoUrl !== null) {
            return $this->apiCall('sendPhoto', [
                'chat_id' => $chatId,
                'photo'   => $this->photoUrl,
                'caption' => $this->text ?? '',
            ]);
        }

        $params = [
            'chat_id' => $chatId,
            'text'    => $this->text ?? '',
        ];

        if ($this->parseMode !== null) {
            $params['parse_mode'] = $this->parseMode;
        }

        if ($this->keyboard !== null) {
            $inlineKeyboard = [];
            foreach ($this->keyboard as $row) {
                $buttons = [];
                foreach ((array) $row as $btn) {
                    if (is_string($btn)) {
                        $buttons[] = ['text' => $btn, 'callback_data' => $btn];
                    } elseif (is_array($btn)) {
                        $buttons[] = $btn;
                    }
                }
                $inlineKeyboard[] = $buttons;
            }
            $params['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]) ?: '{}';
        }

        return $this->apiCall('sendMessage', $params);
    }

    /** Telegram Bot API 파일 업로드 */
    private function apiUpload(string $method, string $chatId, string $filePath, string $fieldName, ?string $caption): bool
    {
        if (!is_file($filePath)) {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/{$method}";
        $extra = ['chat_id' => $chatId];
        if ($caption !== null && $caption !== '') {
            $extra['caption'] = $caption;
        }

        $response = \http()->upload($url, $filePath, $fieldName, $extra);
        $data = $response->json();

        if ($data === null || !($data['ok'] ?? false)) {
            if (class_exists('Cat\\Log', false)) {
                \logger()->error('Telegram API 업로드 실패', [
                    'method' => $method,
                    'error'  => $data['description'] ?? 'Unknown error',
                ]);
            }
            return false;
        }

        return true;
    }

    /** Telegram Bot API 호출 */
    private function apiCall(string $method, array $params): bool
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/{$method}";

        $response = \http()->post($url, $params);
        $data = $response->json();

        if ($data === null || !($data['ok'] ?? false)) {
            if (class_exists('Cat\\Log', false)) {
                \logger()->error('Telegram API 실패', [
                    'method' => $method,
                    'error'  => $data['description'] ?? 'Unknown error',
                ]);
            }
            return false;
        }

        return true;
    }
}
