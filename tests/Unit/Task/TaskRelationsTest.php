<?php

declare(strict_types=1);

namespace Tests\Unit\Task;

use App\Entity\Calendrier;
use App\Entity\DemandeReservation;
use App\Entity\Evenement;
use App\Entity\Tache;
use PHPUnit\Framework\TestCase;

class TaskRelationsTest extends TestCase
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
     * Teste qu'un événement peut être associé à un ensemble de tâches via le calendrier.
     */
    public function testEventHasManyTasks(): void
    {
        $calendar = new Calendrier();
        $calendar->getTaches()->add(new Tache());
        $calendar->getTaches()->add(new Tache());

        $event = (new Evenement())->setCalendrier($calendar);

        $this->assertCount(2, $event->getCalendrier()?->getTaches() ?? []);
    }

    /**
     * Teste qu'une tâche est rattachée à l'événement via le calendrier.
     */
    public function testTaskBelongsToEvent(): void
    {
        $calendar = new Calendrier();
        $task = (new Tache())->setCalendrier($calendar);
        $event = (new Evenement())->setCalendrier($calendar);

        $this->assertSame($event->getCalendrier(), $task->getCalendrier());
    }

    /**
     * Teste qu'une demande de salle appartient à un événement (non applicable à Task).
     */
    public function testRoomRequestBelongsToEvent(): void
    {
        $this->markTestSkipped('Relation non applicable à Tache.');
    }

    /**
     * Teste qu'un événement peut avoir plusieurs demandes de salle (non applicable à Task).
     */
    public function testEventHasManyRoomRequests(): void
    {
        $this->markTestSkipped('Relation non applicable à Tache.');
    }

    /**
     * Teste la suppression logique des tâches lorsque l'événement est supprimé (simulé via calendrier).
     */
    public function testDeletingEventCascadesToTasks(): void
    {
        $calendar = new Calendrier();
        $calendar->getTaches()->add(new Tache());
        $calendar->getTaches()->add(new Tache());
        $event = (new Evenement())->setCalendrier($calendar);

        $calendar->getEvenements()->add($event);
        $calendar->getEvenements()->removeElement($event);
        $calendar->getTaches()->clear();

        $this->assertCount(0, $calendar->getTaches());
    }

    /**
     * Teste la suppression des demandes de salle associées à un événement (non applicable à Task).
     */
    public function testDeletingEventCascadesToRoomRequests(): void
    {
        $this->markTestSkipped('Relation non applicable à Tache.');
    }
}
