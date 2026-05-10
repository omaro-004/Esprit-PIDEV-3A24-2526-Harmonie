<?php

namespace App\Repository;

use App\Entity\Calendrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Calendrier>
 */
class CalendrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Calendrier::class);
    }

    /**
     * Calendrier unique « principal » (le plus petit id) — toute l’app l’utilise implicitement.
     */
    public function findPrimary(): ?Calendrier
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
