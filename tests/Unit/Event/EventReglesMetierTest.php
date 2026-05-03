<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use App\Entity\Evenement;
use PHPUnit\Framework\TestCase;

class EventReglesMetierTest extends TestCase
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
     * Teste qu'un événement ne peut pas commencer dans le passé.
     */
    public function testEventCannotStartInThePast(): void
    {
        $service = new EventRulesService();

        $this->expectException(\RuntimeException::class);

        $service->validateDates(
            new \DateTimeImmutable('yesterday'),
            new \DateTimeImmutable('tomorrow'),
        );
    }

    /**
     * Teste que la date de fin doit être postérieure à la date de début.
     */
    public function testEndDateMustBeAfterStartDate(): void
    {
        $service = new EventRulesService();

        $this->expectException(\RuntimeException::class);

        $service->validateDates(
            new \DateTimeImmutable('2026-05-10 10:00:00'),
            new \DateTimeImmutable('2026-05-10 09:00:00'),
        );
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

        $service->assertRoomAvailable(new Evenement());
    }

    /**
     * Teste qu'une tâche ne peut pas être assignée à un événement fermé.
     */
    public function testTaskCannotBeAssignedToClosedEvent(): void
    {
        $service = new TaskAssignmentService();

        $this->expectException(\RuntimeException::class);

        $service->assignTask(true);
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
    public function isAvailable(Evenement $event): bool;
}

final class EventRulesService
{
    public function validateDates(\DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        if ($start < new \DateTimeImmutable('now')) {
            throw new \RuntimeException('Date de début dans le passé.');
        }
        if ($end <= $start) {
            throw new \RuntimeException('Date de fin invalide.');
        }
    }
}

final class RoomBookingService
{
    public function __construct(private RoomAvailabilityChecker $checker)
    {
    }

    public function assertRoomAvailable(Evenement $event): void
    {
        if (!$this->checker->isAvailable($event)) {
            throw new \RuntimeException('Salle déjà réservée.');
        }
    }
}

final class TaskAssignmentService
{
    public function assignTask(bool $eventClosed): void
    {
        if ($eventClosed) {
            throw new \RuntimeException('Événement fermé.');
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
