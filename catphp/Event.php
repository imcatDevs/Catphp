<?php declare(strict_types=1);

namespace Cat;

/**
 * Cat\Event — 이벤트 디스패처
 *
 * 이벤트 등록/발산, 우선순위, 전파 중단.
 */
final class Event
{
    private static ?self $instance = null;

    /** @var array<string, array<int, array{id: int, callback: callable, priority: int, once: bool}>> */
    private array $listeners = [];

    /** @var int 리스너 ID 카운터 */
    private int $nextId = 0;

    /** @var array<string, bool> 정렬 필요 플래그 */
    private array $dirty = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** 이벤트 리스너 등록 (리스너 ID 반환) */
    public function on(string $event, callable $callback, int $priority = 0): int
    {
        $id = $this->nextId++;
        $this->listeners[$event][] = [
            'id'       => $id,
            'callback' => $callback,
            'priority' => $priority,
            'once'     => false,
        ];
        $this->dirty[$event] = true;
        return $id;
    }

    /** 일회성 리스너 등록 (리스너 ID 반환) */
    public function once(string $event, callable $callback, int $priority = 0): int
    {
        $id = $this->nextId++;
        $this->listeners[$event][] = [
            'id'       => $id,
            'callback' => $callback,
            'priority' => $priority,
            'once'     => true,
        ];
        $this->dirty[$event] = true;
        return $id;
    }

    /** 이벤트 발산 */
    public function emit(string $event, mixed ...$args): self
    {
        if (!isset($this->listeners[$event])) {
            return $this;
        }

        if (!empty($this->dirty[$event])) {
            $this->sortListeners($event);
            unset($this->dirty[$event]);
        }

        $toRemove = [];
        foreach ($this->listeners[$event] as $index => $listener) {
            $result = ($listener['callback'])(...$args);

            if ($listener['once']) {
                $toRemove[] = $index;
            }

            // false 반환 시 전파 중단
            if ($result === false) {
                break;
            }
        }

        // once 리스너 제거
        foreach (array_reverse($toRemove) as $index) {
            array_splice($this->listeners[$event], $index, 1);
        }

        return $this;
    }

    /** 리스너 존재 확인 */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /** 리스너 제거 (ID 또는 이벤트 전체) */
    public function off(string $event, ?int $listenerId = null): self
    {
        if ($listenerId === null) {
            unset($this->listeners[$event]);
        } else {
            $this->listeners[$event] = array_values(array_filter(
                $this->listeners[$event] ?? [],
                fn(array $l) => $l['id'] !== $listenerId
            ));
        }
        return $this;
    }

    /** 우선순위 정렬 (높은 값 먼저) */
    private function sortListeners(string $event): void
    {
        usort(
            $this->listeners[$event],
            fn(array $a, array $b) => $b['priority'] <=> $a['priority']
        );
    }
}
