<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use App\Entity\Evenement;
use PHPUnit\Framework\TestCase;

class EventPerformanceTest extends TestCase
{
    private const TIME_LIMIT_SECONDS = 1.5;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Teste la création d'un grand nombre d'événements en un temps limité.
     */
    public function testCreateLargeNumberOfEvents(): void
    {
        $start = microtime(true);

        $events = [];
        for ($i = 0; $i < 1000; $i++) {
            $events[] = (new Evenement())
                ->setTitre('Event '.$i)
                ->setEventType('cours')
                ->setLieuType('en_ligne');
        }

        $elapsed = microtime(true) - $start;

        $this->assertCount(1000, $events);
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une requête simulée sur un grand dataset.
     */
    public function testQueryLargeDataset(): void
    {
        $events = [];
        for ($i = 0; $i < 2000; $i++) {
            $events[] = (new Evenement())
                ->setTitre('Event '.$i)
                ->setEventType($i % 2 === 0 ? 'cours' : 'reunion')
                ->setLieuType('en_ligne');
        }

        $start = microtime(true);

        $filtered = array_filter($events, static fn (Evenement $e): bool => $e->getEventType() === 'cours');

        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0, count($filtered));
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une mise à jour en masse simulée.
     */
    public function testBulkUpdatePerformance(): void
    {
        $events = [];
        for ($i = 0; $i < 1500; $i++) {
            $events[] = (new Evenement())
                ->setTitre('Event '.$i)
                ->setEventType('cours')
                ->setLieuType('en_ligne');
        }

        $start = microtime(true);

        foreach ($events as $event) {
            $event->setLieuType('presentiel');
        }

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une recherche simulée sur un dataset.
     */
    public function testSearchPerformance(): void
    {
        $events = [];
        for ($i = 0; $i < 1500; $i++) {
            $events[] = (new Evenement())
                ->setTitre('Event '.$i)
                ->setEventType('cours')
                ->setLieuType('en_ligne');
        }

        $start = microtime(true);

        $matches = array_filter(
            $events,
            static fn (Evenement $e): bool => str_contains($e->getTitre() ?? '', 'Event 12'),
        );

        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0, count($matches));
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }
}
