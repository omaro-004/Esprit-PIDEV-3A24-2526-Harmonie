<?php

declare(strict_types=1);

namespace Tests\Unit\RoomRequest;

use App\Entity\DemandeReservation;
use App\Entity\Evenement;
use App\Entity\Salle;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class RoomRequestPerformanceTest extends TestCase
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
     * Teste la création d'un grand nombre de demandes de salle en un temps limité.
     */
    public function testCreateLargeNumberOfEvents(): void
    {
        $start = microtime(true);

        $requests = [];
        for ($i = 0; $i < 1000; $i++) {
            $requests[] = (new DemandeReservation())
                ->setEvenement(new Evenement())
                ->setSalle($this->createMock(Salle::class))
                ->setUtilisateur($this->createMock(User::class));
        }

        $elapsed = microtime(true) - $start;

        $this->assertCount(1000, $requests);
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une requête simulée sur un grand dataset.
     */
    public function testQueryLargeDataset(): void
    {
        $requests = [];
        for ($i = 0; $i < 2000; $i++) {
            $requests[] = (new DemandeReservation())
                ->setEvenement(new Evenement())
                ->setSalle($this->createMock(Salle::class))
                ->setUtilisateur($this->createMock(User::class))
                ->setStatut($i % 2 === 0 ? DemandeReservation::STATUT_EN_ATTENTE : DemandeReservation::STATUT_ACCEPTEE);
        }

        $start = microtime(true);

        $filtered = array_filter(
            $requests,
            static fn (DemandeReservation $r): bool => $r->getStatut() === DemandeReservation::STATUT_EN_ATTENTE,
        );

        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0, count($filtered));
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une mise à jour en masse simulée.
     */
    public function testBulkUpdatePerformance(): void
    {
        $requests = [];
        for ($i = 0; $i < 1500; $i++) {
            $requests[] = (new DemandeReservation())
                ->setEvenement(new Evenement())
                ->setSalle($this->createMock(Salle::class))
                ->setUtilisateur($this->createMock(User::class));
        }

        $start = microtime(true);

        foreach ($requests as $request) {
            $request->setStatut(DemandeReservation::STATUT_ACCEPTEE);
        }

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }

    /**
     * Teste la performance d'une recherche simulée sur un dataset.
     */
    public function testSearchPerformance(): void
    {
        $requests = [];
        for ($i = 0; $i < 1500; $i++) {
            $requests[] = (new DemandeReservation())
                ->setEvenement(new Evenement())
                ->setSalle($this->createMock(Salle::class))
                ->setUtilisateur($this->createMock(User::class))
                ->setStatut($i % 2 === 0 ? DemandeReservation::STATUT_EN_ATTENTE : DemandeReservation::STATUT_REFUSEE);
        }

        $start = microtime(true);

        $matches = array_filter(
            $requests,
            static fn (DemandeReservation $r): bool => $r->getStatut() === DemandeReservation::STATUT_REFUSEE,
        );

        $elapsed = microtime(true) - $start;

        $this->assertGreaterThan(0, count($matches));
        $this->assertLessThan(self::TIME_LIMIT_SECONDS, $elapsed);
    }
}
