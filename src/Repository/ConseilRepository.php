<?php

namespace App\Repository;

use App\Entity\Conseil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conseil>
 */
class ConseilRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conseil::class);
    }

    /**
     * @return Conseil[]
     */
    public function findBySession(int $sessionId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.session = :sid')
            ->setParameter('sid', $sessionId)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
