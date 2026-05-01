<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * Événements dont la date de début tombe dans le mois donné (calendrier agenda).
     *
     * @return list<Evenement>
     */
    public function findWithDateDebutInMonth(int $year, int $month): array
    {
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $end = $start->modify('first day of next month');

        return $this->createQueryBuilder('e')
            ->andWhere('e.dateDebut IS NOT NULL')
            ->andWhere('e.dateDebut >= :start')
            ->andWhere('e.dateDebut < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.dateDebut', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Evenement>
     */
    public function findAllWithDateDebutOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.dateDebut IS NOT NULL')
            ->orderBy('e.dateDebut', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('e')->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Evenement>
     */
    public function findAdminPaginated(
        int $page,
        int $limit,
        ?string $typeFiltre,
        ?int $proprietaireId,
        ?\DateTimeInterface $dateDebut,
        ?\DateTimeInterface $dateFin,
        ?string $search,
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.salle', 's')->addSelect('s')
            ->leftJoin('e.proprietaire', 'p')->addSelect('p')
            ->orderBy('e.dateDebut', 'DESC')
            ->addOrderBy('e.id', 'DESC');

        if (null !== $typeFiltre && '' !== $typeFiltre) {
            $qb->andWhere('e.eventType = :tp')->setParameter('tp', $typeFiltre);
        }
        if (null !== $proprietaireId) {
            $qb->andWhere('p.userId = :uid')->setParameter('uid', $proprietaireId);
        }
        if ($dateDebut instanceof \DateTimeInterface) {
            $qb->andWhere('e.dateDebut >= :d0')->setParameter('d0', $dateDebut);
        }
        if ($dateFin instanceof \DateTimeInterface) {
            $qb->andWhere('e.dateDebut <= :d1')->setParameter('d1', $dateFin);
        }
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere(
                'e.titre LIKE :q OR e.description LIKE :q OR e.lieu LIKE :q OR e.lieuAdresse LIKE :q',
            )->setParameter('q', '%'.trim($search).'%');
        }

        return $qb->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAdmin(
        ?string $typeFiltre,
        ?int $proprietaireId,
        ?\DateTimeInterface $dateDebut,
        ?\DateTimeInterface $dateFin,
        ?string $search,
    ): int {
        $qb = $this->createQueryBuilder('e')->select('COUNT(e.id)')
            ->leftJoin('e.proprietaire', 'p');

        if (null !== $typeFiltre && '' !== $typeFiltre) {
            $qb->andWhere('e.eventType = :tp')->setParameter('tp', $typeFiltre);
        }
        if (null !== $proprietaireId) {
            $qb->andWhere('p.userId = :uid')->setParameter('uid', $proprietaireId);
        }
        if ($dateDebut instanceof \DateTimeInterface) {
            $qb->andWhere('e.dateDebut >= :d0')->setParameter('d0', $dateDebut);
        }
        if ($dateFin instanceof \DateTimeInterface) {
            $qb->andWhere('e.dateDebut <= :d1')->setParameter('d1', $dateFin);
        }
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere(
                'e.titre LIKE :q OR e.description LIKE :q OR e.lieu LIKE :q OR e.lieuAdresse LIKE :q',
            )->setParameter('q', '%'.trim($search).'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Evenement>
     */
    public function findRecentForDashboard(int $limit = 3): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.proprietaire', 'p')->addSelect('p')
            ->leftJoin('e.salle', 's')->addSelect('s')
            ->orderBy('e.dateDebut', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Evenement>
     */
    public function findUpcomingEventsNotReminded(\DateTimeImmutable $nowInTunis, int $maxMinutesAhead = 180): array
    {
        $start = $nowInTunis;
        $end = $nowInTunis->modify('+'.$maxMinutesAhead.' minutes');

        return $this->createQueryBuilder('e')
            ->leftJoin('e.proprietaire', 'p')->addSelect('p')
            ->andWhere('e.dateDebut IS NOT NULL')
            ->andWhere('e.dateDebut >= :start')
            ->andWhere('e.dateDebut <= :end')
            ->andWhere('e.rappelActif = :rappelActif OR e.rappelActif IS NULL')
            ->andWhere('e.reminderSent = :sent')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('rappelActif', true)
            ->setParameter('sent', false)
            ->orderBy('e.dateDebut', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
