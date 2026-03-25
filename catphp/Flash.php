<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Flash — 플래시 메시지
 *
 * 세션 기반 일회성 메시지 (redirect 후 표시).
 */
final class Flash
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 플래시 메시지 설정 (Guard 살균 적용) */
    public function set(string $type, string $message): void
    {
        $type = preg_replace('/[^a-zA-Z_]/', '', $type) ?: 'info';
        $message = \guard()->clean($message);
        $flashes = \session()->get('_flash', []);
        $flashes[] = ['type' => $type, 'message' => $message];
        \session()->set('_flash', $flashes);
    }

    /** 플래시 메시지 읽기 (읽은 후 삭제) */
    public function get(): array
    {
        $flashes = \session()->get('_flash', []);
        \session()->set('_flash', []);
        return $flashes;
    }

    /** 플래시 메시지 존재 확인 */
    public function has(): bool
    {
        $flashes = \session()->get('_flash', []);
        return !empty($flashes);
    }

    public function success(string $message): void
    {
        $this->set('success', $message);
    }

    public function error(string $message): void
    {
        $this->set('error', $message);
    }

    public function warning(string $message): void
    {
        $this->set('warning', $message);
    }

    public function info(string $message): void
    {
        $this->set('info', $message);
    }
}
