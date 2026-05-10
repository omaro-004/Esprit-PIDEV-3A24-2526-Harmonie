<?php

namespace App\Repository;

use App\Entity\JournalHumeur;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalHumeur>
 */
class JournalHumeurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalHumeur::class);
    }

    /**
     * @return JournalHumeur[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.user = :user')
            ->setParameter('user', $user)
            ->orderBy('j.dateJournal', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return JournalHumeur[]
     */
    public function searchByUser(User $user, string $q = '', string $humeur = ''): array
    {
        $qb = $this->createQueryBuilder('j')
            ->where('j.user = :user')
            ->setParameter('user', $user);

        if ($q !== '') {
            $qb->andWhere('j.contenu LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        if ($humeur !== '') {
            $qb->andWhere('j.humeur = :humeur')
               ->setParameter('humeur', $humeur);
        }

        return $qb->orderBy('j.dateJournal', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByAdmin(): int
    {
        return (int) $this->createQueryBuilder('j')
            ->select('COUNT(j.id)')
            ->where('j.isReadByAdmin = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return JournalHumeur[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('j')
            ->leftJoin('j.user', 'u')
            ->addSelect('u')
            ->orderBy('j.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function markUnreadAsRead(): void
    {
        $this->createQueryBuilder('j')
            ->update()
            ->set('j.isReadByAdmin', 'true')
            ->where('j.isReadByAdmin = false')
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<mixed>
     */
    public function moodDistribution(User $user): array
    {
        return $this->createQueryBuilder('j')
            ->select('j.humeur, COUNT(j.id) AS cnt')
            ->where('j.user = :user')
            ->setParameter('user', $user)
            ->groupBy('j.humeur')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array<mixed>
     */
    public function moodStats(User $user): array
    {
        $row = $this->createQueryBuilder('j')
            ->select('AVG(j.score) AS avgScore, COUNT(j.id) AS total')
            ->where('j.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return [
            'avgScore' => round((float)($row['avgScore'] ?? 0), 2),
            'total'    => (int)($row['total'] ?? 0),
        ];
    }

    /**
     * @return array<mixed>
     */
    public function scoreTrend(User $user, int $limit = 30): array
    {
        $entries = $this->createQueryBuilder('j')
            ->select('j.dateJournal, j.score, j.humeur')
            ->where('j.user = :user')
            ->setParameter('user', $user)
            ->orderBy('j.dateJournal', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(fn($row) => [
            'date'   => $row['dateJournal'] instanceof \DateTimeInterface
                ? $row['dateJournal']->format('d/m')
                : $row['dateJournal'],
            'score'  => $row['score'],
            'humeur' => $row['humeur'],
        ], $entries);
    }

    /**
     * @return JournalHumeur[]
     */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.user = :user')
            ->setParameter('user', $user)
            ->orderBy('j.dateJournal', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
