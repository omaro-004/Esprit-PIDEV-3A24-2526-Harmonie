<?php

namespace App\Repository;

use App\Entity\Conseil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ConseilRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conseil::class);
    }

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
