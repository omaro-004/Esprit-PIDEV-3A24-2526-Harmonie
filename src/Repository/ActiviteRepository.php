<?php

namespace App\Repository;

use App\Entity\Activite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    /**
     * Get all activités for a given user, ordered by date DESC.
     */
    public function findByUserOrderByDate(int $userId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.exercice', 'e')
            ->where('a.userId = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('a.dateActivite', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get activités for a user grouped by date (sessions).
     * Returns an associative array keyed by date string.
     */
    public function findByUserGroupedByDate(int $userId): array
    {
        $activites = $this->findByUserOrderByDate($userId);
        $grouped = [];
        foreach ($activites as $activite) {
            $date = $activite->getDateActivite()->format('Y-m-d');
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $activite;
        }
        return $grouped;
    }

    /**
     * Count sessions (distinct dates) for a user.
     */
    public function countSessionsByUser(int $userId): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT DATE(a.dateActivite)) as cnt')
            ->where('a.userId = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
        return (int) $result;
    }

    /**
     * Sum of dureeMinutes for a user.
     */
    public function sumMinutesByUser(int $userId): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.dureeMinutes)')
            ->where('a.userId = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
        return (int) $result;
    }

    /**
     * Sum of caloriesBrulees for a user.
     */
    public function sumCaloriesByUser(int $userId): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.caloriesBrulees)')
            ->where('a.userId = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getSingleScalarResult();
        return (int) $result;
    }
}