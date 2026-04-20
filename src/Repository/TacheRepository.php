<?php

namespace App\Repository;

use App\Entity\Tache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tache>
 */
class TacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tache::class);
    }

    /**
     * @return list<Tache>
     */
    public function findAllOrderedForKanban(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.deadline', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Colonnes Kanban : A_FAIRE (TODO), EN_COURS (DOING), TERMINEE (DONE).
     *
     * @return array<string, list<Tache>>
     */
    public function groupedByKanbanStatut(): array
    {
        $columns = [
            'A_FAIRE' => [],
            'EN_COURS' => [],
            'TERMINEE' => [],
        ];
        foreach ($this->findAllOrderedForKanban() as $tache) {
            $key = match ($tache->getStatutTache()) {
                'EN_COURS' => 'EN_COURS',
                'TERMINEE' => 'TERMINEE',
                default => 'A_FAIRE',
            };
            $columns[$key][] = $tache;
        }

        return $columns;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Tache>
     */
    public function findAdminPaginated(int $page, int $limit, ?string $statutFiltre, ?int $calendrierId, ?string $search): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.calendrier', 'c')->addSelect('c')
            ->orderBy('t.deadline', 'DESC')
            ->addOrderBy('t.id', 'DESC');

        if (null !== $statutFiltre && '' !== $statutFiltre) {
            $qb->andWhere('t.statutTache = :st')->setParameter('st', $statutFiltre);
        }
        if (null !== $calendrierId) {
            $qb->andWhere('c.id = :cid')->setParameter('cid', $calendrierId);
        }
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('t.nom LIKE :q OR t.notes LIKE :q')
                ->setParameter('q', '%'.trim($search).'%');
        }

        return $qb->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAdmin(?string $statutFiltre, ?int $calendrierId, ?string $search): int
    {
        $qb = $this->createQueryBuilder('t')->select('COUNT(t.id)')
            ->leftJoin('t.calendrier', 'c');

        if (null !== $statutFiltre && '' !== $statutFiltre) {
            $qb->andWhere('t.statutTache = :st')->setParameter('st', $statutFiltre);
        }
        if (null !== $calendrierId) {
            $qb->andWhere('c.id = :cid')->setParameter('cid', $calendrierId);
        }
        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('t.nom LIKE :q OR t.notes LIKE :q')
                ->setParameter('q', '%'.trim($search).'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Tache>
     */
    public function findRecentForDashboard(int $limit = 3): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.calendrier', 'c')->addSelect('c')
            ->orderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
