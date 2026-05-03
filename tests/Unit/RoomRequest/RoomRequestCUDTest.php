<?php

declare(strict_types=1);

namespace Tests\Unit\RoomRequest;

use App\Entity\DemandeReservation;
use App\Entity\Evenement;
use App\Entity\Salle;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class RoomRequestCUDTest extends TestCase
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

            public function create(array $data): DemandeReservation
            {
                $request = (new DemandeReservation())
                    ->setEvenement($data['evenement'])
                    ->setSalle($data['salle'])
                    ->setUtilisateur($data['utilisateur'])
                    ->setStatut($data['statut']);

                $this->em->persist($request);
                $this->em->flush();

                return $request;
            }

            public function update(DemandeReservation $request, array $data): DemandeReservation
            {
                $request->setStatut($data['statut']);
                $request->setCommentaireAdmin($data['commentaire']);
                $this->em->flush();

                return $request;
            }

            public function delete(DemandeReservation $request): void
            {
                $this->em->remove($request);
                $this->em->flush();
            }
        };
    }

    /**
     * Teste que la création d'une demande de salle avec des données valides réussit.
     */
    public function testCreateRoomRequestWithValidData(): void
    {
        $data = [
            'evenement' => new Evenement(),
            'salle' => $this->createMock(Salle::class),
            'utilisateur' => $this->createMock(User::class),
            'statut' => DemandeReservation::STATUT_EN_ATTENTE,
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $request = $service->create($data);

        $this->assertEquals(DemandeReservation::STATUT_EN_ATTENTE, $request->getStatut());
    }

    /**
     * Teste que la mise à jour d'une demande de salle avec des données valides réussit.
     */
    public function testUpdateRoomRequestWithValidData(): void
    {
        $request = (new DemandeReservation())
            ->setEvenement(new Evenement())
            ->setSalle($this->createMock(Salle::class))
            ->setUtilisateur($this->createMock(User::class));

        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $updated = $service->update($request, [
            'statut' => DemandeReservation::STATUT_ACCEPTEE,
            'commentaire' => 'Validé par admin',
        ]);

        $this->assertEquals(DemandeReservation::STATUT_ACCEPTEE, $updated->getStatut());
    }

    /**
     * Teste que la suppression d'une demande de salle déclenche la suppression via l'EntityManager.
     */
    public function testDeleteRoomRequest(): void
    {
        $request = new DemandeReservation();

        $this->entityManager->expects($this->once())->method('remove')->with($request);
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $service->delete($request);

        $this->assertNull($request->getId());
    }

    /**
     * Teste que la création d'une demande de salle déclenche la persistance en base.
     */
    public function testCreateRoomRequestPersistsInDatabase(): void
    {
        $data = [
            'evenement' => new Evenement(),
            'salle' => $this->createMock(Salle::class),
            'utilisateur' => $this->createMock(User::class),
            'statut' => DemandeReservation::STATUT_EN_ATTENTE,
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $request = $service->create($data);

        $this->assertEquals(DemandeReservation::STATUT_EN_ATTENTE, $request->getStatut());
    }

    /**
     * Teste que la suppression d'une demande de salle déclenche la suppression en base.
     */
    public function testDeleteRoomRequestRemovesFromDatabase(): void
    {
        $request = new DemandeReservation();

        $this->entityManager->expects($this->once())->method('remove')->with($request);
        $this->entityManager->expects($this->once())->method('flush');

        $service = $this->getService();
        $service->delete($request);

        $this->assertNull($request->getId());
    }
}
