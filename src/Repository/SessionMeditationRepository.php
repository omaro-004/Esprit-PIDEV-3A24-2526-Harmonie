<?php

namespace App\Repository;

use App\Entity\SessionMeditation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SessionMeditationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionMeditation::class);
    }

    public function searchAndSort(string $q = '', string $sort = 'id', string $direction = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($q !== '') {
            $qb->where('s.theme LIKE :q OR s.auteur LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        $allowed = ['theme', 'auteur', 'duree', 'id'];
        $sort = in_array($sort, $allowed) ? $sort : 'id';
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return $qb->orderBy('s.' . $sort, $direction)
            ->getQuery()
            ->getResult();
    }
}
