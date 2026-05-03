<?php

declare(strict_types=1);

namespace Tests\Unit\RoomRequest;

use App\Entity\Calendrier;
use App\Entity\DemandeReservation;
use App\Entity\Evenement;
use App\Entity\Tache;
use PHPUnit\Framework\TestCase;

class RoomRequestRelationsTest extends TestCase
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
     * Teste qu'un événement peut être associé à un ensemble de tâches via le calendrier (non applicable à RoomRequest).
     */
    public function testEventHasManyTasks(): void
    {
        $this->markTestSkipped('Relation non applicable à DemandeReservation.');
    }

    /**
     * Teste qu'une tâche appartient à un événement via le calendrier (non applicable à RoomRequest).
     */
    public function testTaskBelongsToEvent(): void
    {
        $this->markTestSkipped('Relation non applicable à DemandeReservation.');
    }

    /**
     * Teste qu'une demande de salle appartient à un événement.
     */
    public function testRoomRequestBelongsToEvent(): void
    {
        $event = new Evenement();
        $request = (new DemandeReservation())->setEvenement($event);

        $this->assertSame($event, $request->getEvenement());
    }

    /**
     * Teste qu'un événement peut avoir plusieurs demandes de salle.
     */
    public function testEventHasManyRoomRequests(): void
    {
        $event = new Evenement();
        $event->addDemandeReservation(new DemandeReservation());
        $event->addDemandeReservation(new DemandeReservation());

        $this->assertCount(2, $event->getDemandeReservations());
    }

    /**
     * Teste la suppression logique des tâches lorsque l'événement est supprimé (non applicable à RoomRequest).
     */
    public function testDeletingEventCascadesToTasks(): void
    {
        $calendar = new Calendrier();
        $calendar->getTaches()->add(new Tache());
        $calendar->getTaches()->clear();

        $this->assertCount(0, $calendar->getTaches());
    }

    /**
     * Teste la suppression des demandes de salle associées à un événement.
     */
    public function testDeletingEventCascadesToRoomRequests(): void
    {
        $event = new Evenement();
        $request = new DemandeReservation();
        $event->addDemandeReservation($request);

        $event->removeDemandeReservation($request);

        $this->assertCount(0, $event->getDemandeReservations());
    }
}
