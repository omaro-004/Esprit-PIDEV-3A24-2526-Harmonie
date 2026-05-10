<?php

namespace App\Repository;

use App\Entity\Consommation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consommation>
 */
class ConsommationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consommation::class);
    }

    /**
     * Toutes les consommations d'un utilisateur pour une date donnée.
     *
     * @return Consommation[]
     */
    public function findByUserAndDate(int $userId, \DateTime $date): array
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end   = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('c')
            ->join('c.aliment', 'a')
            ->where('c.userId = :uid')
            ->andWhere('c.dateConsommation BETWEEN :start AND :end')
            ->setParameter('uid', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('c.dateConsommation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Somme des calories pour un utilisateur et une date.
     */
    public function sumCaloriesByUserAndDate(int $userId, \DateTime $date): float
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end   = (clone $date)->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('c')
            ->select('SUM(a.caloriesPour100g * c.poidsGrammes / 100.0)')
            ->join('c.aliment', 'a')
            ->where('c.userId = :uid')
            ->andWhere('c.dateConsommation BETWEEN :start AND :end')
            ->andWhere('c.poidsGrammes IS NOT NULL')
            ->andWhere('a.caloriesPour100g IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)($result ?? 0), 1);
    }

    /**
     * Somme des protéines.
     */
    public function sumProtByUserAndDate(int $userId, \DateTime $date): float
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end   = (clone $date)->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('c')
            ->select('SUM(a.proteines * c.poidsGrammes / 100.0)')
            ->join('c.aliment', 'a')
            ->where('c.userId = :uid')
            ->andWhere('c.dateConsommation BETWEEN :start AND :end')
            ->andWhere('c.poidsGrammes IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)($result ?? 0), 1);
    }

    /**
     * Somme des glucides.
     */
    public function sumGlucByUserAndDate(int $userId, \DateTime $date): float
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end   = (clone $date)->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('c')
            ->select('SUM(a.glucides * c.poidsGrammes / 100.0)')
            ->join('c.aliment', 'a')
            ->where('c.userId = :uid')
            ->andWhere('c.dateConsommation BETWEEN :start AND :end')
            ->andWhere('c.poidsGrammes IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)($result ?? 0), 1);
    }

    /**
     * Somme des lipides.
     */
    public function sumLipByUserAndDate(int $userId, \DateTime $date): float
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end   = (clone $date)->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('c')
            ->select('SUM(a.lipides * c.poidsGrammes / 100.0)')
            ->join('c.aliment', 'a')
            ->where('c.userId = :uid')
            ->andWhere('c.dateConsommation BETWEEN :start AND :end')
            ->andWhere('c.poidsGrammes IS NOT NULL')
            ->setParameter('uid', $userId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)($result ?? 0), 1);
    }

    /**
     * Historique des 7 derniers jours (pour graphique).
     *
     * @return Consommation[]
     */
    public function findLast7Days(int $userId): array
    {
        $end   = new \DateTime();
        $start = (clone $end)->modify('-6 days')->setTime(0, 0, 0);

        return $this->createQueryBuilder('c')
            ->join('c.aliment', 'a')
            ->where('c.userId = :uid')
            ->andWhere('c.dateConsommation >= :start')
            ->setParameter('uid', $userId)
            ->setParameter('start', $start)
            ->orderBy('c.dateConsommation', 'ASC')
            ->getQuery()
            ->getResult();
    }
}