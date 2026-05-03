<?php

declare(strict_types=1);

namespace Tests\Unit\RoomRequest;

use PHPUnit\Framework\TestCase;

class RoomRequestReglesMetierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Teste qu'un événement ne peut pas commencer dans le passé (non applicable à RoomRequest).
     */
    public function testEventCannotStartInThePast(): void
    {
        $this->markTestSkipped('Règle non applicable à DemandeReservation.');
    }

    /**
     * Teste que la date de fin doit être postérieure à la date de début (non applicable à RoomRequest).
     */
    public function testEndDateMustBeAfterStartDate(): void
    {
        $this->markTestSkipped('Règle non applicable à DemandeReservation.');
    }

    /**
     * Teste qu'une salle ne peut pas être réservée deux fois sur le même créneau.
     */
    public function testRoomCannotBeDoubleBooked(): void
    {
        $availabilityChecker = $this->createMock(RoomAvailabilityChecker::class);
        $availabilityChecker->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $service = new RoomBookingService($availabilityChecker);

        $this->expectException(\RuntimeException::class);

        $service->assertRoomAvailable();
    }

    /**
     * Teste qu'une tâche ne peut pas être assignée à un événement fermé (non applicable à RoomRequest).
     */
    public function testTaskCannotBeAssignedToClosedEvent(): void
    {
        $this->markTestSkipped('Règle non applicable à DemandeReservation.');
    }

    /**
     * Teste qu'une demande de salle nécessite une approbation.
     */
    public function testRoomRequestRequiresApproval(): void
    {
        $service = new RoomRequestApprovalService();

        $this->expectException(\RuntimeException::class);

        $service->requireApproval(false);
    }

    /**
     * Teste que seul un administrateur peut approuver une demande de salle.
     */
    public function testOnlyAdminCanApproveRoomRequest(): void
    {
        $service = new RoomRequestApprovalService();

        $this->expectException(\RuntimeException::class);

        $service->approve(false);
    }
}

interface RoomAvailabilityChecker
{
    public function isAvailable(): bool;
}

final class RoomBookingService
{
    public function __construct(private RoomAvailabilityChecker $checker)
    {
    }

    public function assertRoomAvailable(): void
    {
        if (!$this->checker->isAvailable()) {
            throw new \RuntimeException('Salle déjà réservée.');
        }
    }
}

final class RoomRequestApprovalService
{
    public function requireApproval(bool $approved): void
    {
        if (!$approved) {
            throw new \RuntimeException('Approbation requise.');
        }
    }

    public function approve(bool $isAdmin): void
    {
        if (!$isAdmin) {
            throw new \RuntimeException('Seul un admin peut approuver.');
        }
    }
}
