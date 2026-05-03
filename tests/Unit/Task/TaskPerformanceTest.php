<?php

declare(strict_types=1);

namespace Tests\Unit\Task;

use App\Entity\Calendrier;
use App\Entity\Tache;
use PHPUnit\Framework\TestCase;

class TaskPerformanceTest extends TestCase
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
     * Teste la création d'un grand nombre de tâches en un temps limité.
     */
    public function testCreateLargeNumberOfEvents(): void
    {
        $start = microtime(true);

        $tasks = [];
        for ($i = 0; $i < 1000; $i++) {
            $tasks[] = (new Tache())
                ->setNom('Task '.$i)
                ->setCalendrier(new Calendrier());
        }

        $elapsed = microtime(true) - $start;

        $this->assertCount(1000, $tasks);
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une requête simulée sur un grand dataset.
     */
    public function testQueryLargeDataset(): void
    {
        $tasks = [];
        for ($i = 0; $i < 2000; $i++) {
            $tasks[] = (new Tache())
                ->setNom('Task '.$i)
                ->setCalendrier(new Calendrier());
        }

        $start = microtime(true);

        $filtered = array_filter($tasks, static fn (Tache $t): bool => str_contains($t->getNom(), 'Task 1'));

        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0, count($filtered));
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une mise à jour en masse simulée.
     */
    public function testBulkUpdatePerformance(): void
    {
        $tasks = [];
        for ($i = 0; $i < 1500; $i++) {
            $tasks[] = (new Tache())
                ->setNom('Task '.$i)
                ->setCalendrier(new Calendrier());
        }

        $start = microtime(true);

        foreach ($tasks as $task) {
            $task->setNom($task->getNom().' - updated');
        }

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une recherche simulée sur un dataset.
     */
    public function testSearchPerformance(): void
    {
        $tasks = [];
        for ($i = 0; $i < 1500; $i++) {
            $tasks[] = (new Tache())
                ->setNom('Task '.$i)
                ->setCalendrier(new Calendrier());
        }

        $start = microtime(true);

        $matches = array_filter($tasks, static fn (Tache $t): bool => str_contains($t->getNom(), 'Task 12'));

        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0, count($matches));
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }
}
