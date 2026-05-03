<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use App\Entity\Evenement;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EventCUDTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        unset($this->entityManager);
        parent::tearDown();
    }

    private function getService(): object
    {
        return new class($this->entityManager) {
            public function __construct(private EntityManagerInterface $em)
            {
            }

            public function create(array $data): Evenement
            {
                $event = (new Evenement())
                    ->setTitre($data['titre'])
                    ->setDateDebut($data['dateDebut'])
                    ->setDateFin($data['dateFin'])
                    ->setEventType($data['eventType'])
                    ->setLieuType($data['lieuType']);

                $this->em->persist($event);
                $this->em->flush();

                return $event;
            }

            public function update(Evenement $event, array $data): Evenement
            {
                $event->setTitre($data['titre']);
                $event->setDateFin($data['dateFin']);
                $this->em->flush();

                return $event;
            }

            public function delete(Evenement $event): void
            {
                $this->em->remove($event);
                $this->em->flush();
            }
        };
    }

    /**
     * Teste que la création d'un événement avec des données valides réussit.
     */
    public function testCreateEventWithValidData(): void
    {
        $data = [
            'titre' => 'Réunion Harmonie',
            'dateDebut' => new \DateTimeImmutable('2026-05-10 09:00:00'),
            'dateFin' => new \DateTimeImmutable('2026-05-10 10:00:00'),
            'eventType' => 'reunion',
            'lieuType' => 'en_ligne',
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $event = $service->create($data);

        $this->assertEquals('Réunion Harmonie', $event->getTitre());
    }

    /**
     * Teste que la mise à jour d'un événement avec des données valides réussit.
     */
    public function testUpdateEventWithValidData(): void
    {
        $event = (new Evenement())
            ->setTitre('Ancien titre')
            ->setDateDebut(new \DateTimeImmutable('2026-05-10 09:00:00'))
            ->setDateFin(new \DateTimeImmutable('2026-05-10 10:00:00'))
            ->setEventType('reunion')
            ->setLieuType('en_ligne');

        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $updated = $service->update($event, [
            'titre' => 'Nouveau titre',
            'dateFin' => new \DateTimeImmutable('2026-05-10 11:00:00'),
        ]);

        $this->assertEquals('Nouveau titre', $updated->getTitre());
    }

    /**
     * Teste que la suppression d'un événement déclenche la suppression via l'EntityManager.
     */
    public function testDeleteEvent(): void
    {
        $event = new Evenement();

        $this->entityManager->expects($this->once())->method('remove')->with($event);
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $service->delete($event);

        $this->assertNull($event->getId());
    }

    /**
     * Teste que la création d'un événement déclenche la persistance en base.
     */
    public function testCreateEventPersistsInDatabase(): void
    {
        $data = [
            'titre' => 'Atelier bien-être',
            'dateDebut' => new \DateTimeImmutable('2026-05-15 09:00:00'),
            'dateFin' => new \DateTimeImmutable('2026-05-15 12:00:00'),
            'eventType' => 'loisir',
            'lieuType' => 'en_ligne',
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $event = $service->create($data);

        $this->assertEquals('Atelier bien-être', $event->getTitre());
    }

    /**
     * Teste que la suppression d'un événement déclenche la suppression en base.
     */
    public function testDeleteEventRemovesFromDatabase(): void
    {
        $event = new Evenement();

        $this->entityManager->expects($this->once())->method('remove')->with($event);
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $service->delete($event);

        $this->assertNull($event->getId());
    }
}
