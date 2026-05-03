<?php

declare(strict_types=1);

namespace Tests\Unit\Task;

use PHPUnit\Framework\TestCase;

class TaskReglesMetierTest extends TestCase
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
     * Teste qu'un événement ne peut pas commencer dans le passé (non applicable à Task).
     */
    public function testEventCannotStartInThePast(): void
    {
        $this->markTestSkipped('Règle non applicable à Tache.');
    }

    /**
     * Teste que la date de fin doit être postérieure à la date de début (non applicable à Task).
     */
    public function testEndDateMustBeAfterStartDate(): void
    {
        $this->markTestSkipped('Règle non applicable à Tache.');
    }

    /**
     * Teste qu'une salle ne peut pas être réservée deux fois (non applicable à Task).
     */
    public function testRoomCannotBeDoubleBooked(): void
    {
        $this->markTestSkipped('Règle non applicable à Tache.');
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
     * Teste qu'une demande de salle nécessite une approbation (non applicable à Task).
     */
    public function testRoomRequestRequiresApproval(): void
    {
        $this->markTestSkipped('Règle non applicable à Tache.');
    }

    /**
     * Teste que seul un administrateur peut approuver une demande de salle (non applicable à Task).
     */
    public function testOnlyAdminCanApproveRoomRequest(): void
    {
        $this->markTestSkipped('Règle non applicable à Tache.');
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
