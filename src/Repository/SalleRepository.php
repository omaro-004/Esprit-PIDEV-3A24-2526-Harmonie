<?php

namespace App\Repository;

use App\Entity\Salle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Salle>
 */
class SalleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Salle::class);
    }

    /**
     * @return list<Salle>
     */
    public function findDisponiblesOrderedByNom(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.disponible = :d')
            ->setParameter('d', true)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Salle>
     */
    public function findAdminPaginated(int $page, int $limit, ?string $search): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.nom', 'ASC');

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('s.nom LIKE :q OR s.description LIKE :q OR s.equipements LIKE :q')
                ->setParameter('q', '%'.trim($search).'%');
        }

        return $qb->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
    }

    public function countAdminFiltered(?string $search): int
    {
        $qb = $this->createQueryBuilder('s')->select('COUNT(s.id)');
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('s.nom LIKE :q OR s.description LIKE :q OR s.equipements LIKE :q')
                ->setParameter('q', '%'.trim($search).'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
