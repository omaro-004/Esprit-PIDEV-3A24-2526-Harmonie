<?php

declare(strict_types=1);

namespace Tests\Unit\Task;

use App\Entity\Calendrier;
use App\Entity\Tache;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TaskCUDTest extends TestCase
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

            public function create(array $data): Tache
            {
                $task = (new Tache())
                    ->setNom($data['nom'])
                    ->setDeadline($data['deadline'])
                    ->setCalendrier($data['calendrier']);

                $this->em->persist($task);
                $this->em->flush();

                return $task;
            }

            public function update(Tache $task, array $data): Tache
            {
                $task->setNom($data['nom']);
                $task->setDeadline($data['deadline']);
                $this->em->flush();

                return $task;
            }

            public function delete(Tache $task): void
            {
                $this->em->remove($task);
                $this->em->flush();
            }
        };
    }

    /**
     * Teste que la création d'une tâche avec des données valides réussit.
     */
    public function testCreateTaskWithValidData(): void
    {
        $data = [
            'nom' => 'Préparer le compte-rendu',
            'deadline' => new \DateTimeImmutable('2026-05-12'),
            'calendrier' => new Calendrier(),
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $task = $service->create($data);

        $this->assertEquals('Préparer le compte-rendu', $task->getNom());
    }

    /**
     * Teste que la mise à jour d'une tâche avec des données valides réussit.
     */
    public function testUpdateTaskWithValidData(): void
    {
        $task = (new Tache())
            ->setNom('Ancienne tâche')
            ->setDeadline(new \DateTimeImmutable('2026-05-12'))
            ->setCalendrier(new Calendrier());

        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $updated = $service->update($task, [
            'nom' => 'Nouvelle tâche',
            'deadline' => new \DateTimeImmutable('2026-05-13'),
        ]);

        $this->assertEquals('Nouvelle tâche', $updated->getNom());
    }

    /**
     * Teste que la suppression d'une tâche déclenche la suppression via l'EntityManager.
     */
    public function testDeleteTask(): void
    {
        $task = (new Tache())->setNom('À supprimer')->setCalendrier(new Calendrier());

        $this->entityManager->expects($this->once())->method('remove')->with($task);
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $service->delete($task);

        $this->assertNull($task->getId());
    }

    /**
     * Teste que la création d'une tâche déclenche la persistance en base.
     */
    public function testCreateTaskPersistsInDatabase(): void
    {
        $data = [
            'nom' => 'Tâche prioritaire',
            'deadline' => new \DateTimeImmutable('2026-05-14'),
            'calendrier' => new Calendrier(),
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $task = $service->create($data);

        $this->assertEquals('Tâche prioritaire', $task->getNom());
    }

    /**
     * Teste que la suppression d'une tâche déclenche la suppression en base.
     */
    public function testDeleteTaskRemovesFromDatabase(): void
    {
        $task = (new Tache())->setNom('Tâche à supprimer')->setCalendrier(new Calendrier());

        $this->entityManager->expects($this->once())->method('remove')->with($task);
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $service->delete($task);

        $this->assertNull($task->getId());
    }
}
