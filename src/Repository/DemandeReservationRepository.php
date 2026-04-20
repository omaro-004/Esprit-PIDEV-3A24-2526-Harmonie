<?php

namespace App\Repository;

use App\Entity\DemandeReservation;
use App\Entity\Evenement;
use App\Entity\Salle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeReservation>
 */
class DemandeReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeReservation::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('d')->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<DemandeReservation>
     */
    public function findAllByStatutWithJoins(string $statut): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :st')
            ->setParameter('st', $statut)
            ->leftJoin('d.evenement', 'e')->addSelect('e')
            ->leftJoin('d.salle', 's')->addSelect('s')
            ->leftJoin('d.utilisateur', 'u')->addSelect('u')
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatut(string $statut): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.statut = :s')
            ->setParameter('s', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPendingForEvenementAndSalle(Evenement $evenement, Salle $salle): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.evenement = :e')
            ->andWhere('d.salle = :s')
            ->andWhere('d.statut = :st')
            ->setParameter('e', $evenement)
            ->setParameter('s', $salle)
            ->setParameter('st', DemandeReservation::STATUT_EN_ATTENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<DemandeReservation>
     */
    public function findAdminPaginated(int $page, int $limit, ?string $statutFiltre, ?string $search): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.evenement', 'e')->addSelect('e')
            ->leftJoin('d.salle', 's')->addSelect('s')
            ->leftJoin('d.utilisateur', 'u')->addSelect('u')
            ->orderBy('d.dateDemande', 'DESC');

        if (null !== $statutFiltre && '' !== $statutFiltre) {
            $qb->andWhere('d.statut = :st')->setParameter('st', $statutFiltre);
        }
        if (null !== $search && '' !== trim($search)) {
            $sq = '%'.trim($search).'%';
            $qb->andWhere(
                'e.titre LIKE :q OR s.nom LIKE :q OR u.userNom LIKE :q OR u.userPrenom LIKE :q OR u.userEmail LIKE :q',
            )->setParameter('q', $sq);
        }

        return $qb->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAdmin(?string $statutFiltre, ?string $search): int
    {
        $qb = $this->createQueryBuilder('d')->select('COUNT(d.id)')
            ->leftJoin('d.evenement', 'e')
            ->leftJoin('d.salle', 's')
            ->leftJoin('d.utilisateur', 'u');
        if (null !== $statutFiltre && '' !== $statutFiltre) {
            $qb->andWhere('d.statut = :st')->setParameter('st', $statutFiltre);
        }
        if (null !== $search && '' !== trim($search)) {
            $sq = '%'.trim($search).'%';
            $qb->andWhere(
                'e.titre LIKE :q OR s.nom LIKE :q OR u.userNom LIKE :q OR u.userPrenom LIKE :q OR u.userEmail LIKE :q',
            )->setParameter('q', $sq);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
