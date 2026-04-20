<?php

namespace App\Service\Kanban;

use Psr\Cache\CacheItemPoolInterface;

final class KanbanRealtimeNotifier
{
    private const KEY = 'kanban.last_event';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function dispatch(string $type, array $payload = []): void
    {
        $event = [
            'id' => time(),
            'type' => $type,
            'payload' => $payload,
            'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $item = $this->cache->getItem(self::KEY);
        $item->set($event);
        $this->cache->save($item);
    }

    public function getLastEvent(): ?array
    {
        $item = $this->cache->getItem(self::KEY);
        $value = $item->isHit() ? $item->get() : null;

        return \is_array($value) ? $value : null;
    }
}
