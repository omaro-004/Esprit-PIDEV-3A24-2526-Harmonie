<?php

namespace App\Repository;

use App\Entity\Exercice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercice>
 */
class ExerciceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercice::class);
    }

    /**
     * Find all exercises ordered by type then name.
     *
     * @return Exercice[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.typeExercice', 'ASC')
            ->addOrderBy('e.nomExercice', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find exercises by type.
     *
     * @return Exercice[]
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.typeExercice LIKE :type')
            ->setParameter('type', '%' . $type . '%')
            ->orderBy('e.nomExercice', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search and filter exercises server-side with sort.
     *
     * @param string $search   Recherche sur nom ou type (LIKE)
     * @param string $type     Filtre exact sur type_exercice ('' = tous)
     * @param string $section  'homme', 'femme', '' = tous
     * @param string $sort     'nom_asc' | 'nom_desc' | 'type_asc' | 'type_desc'
     * @return Exercice[]
     */
    public function searchAndFilter(
        string $search  = '',
        string $type    = '',
        string $section = '',
        string $sort    = 'nom_asc'
    ): array {
        $qb = $this->createQueryBuilder('e');

        // ── Filtre recherche texte ──────────────────────────────────────────
        if ($search !== '') {
            $qb->andWhere('e.nomExercice LIKE :search OR e.typeExercice LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // ── Filtre type exact ───────────────────────────────────────────────
        if ($type !== '') {
            $qb->andWhere('e.typeExercice = :type')
               ->setParameter('type', $type);
        }

        // ── Filtre section (homme / femme) ──────────────────────────────────
        if ($section === 'homme') {
            $qb->andWhere('LOWER(e.typeExercice) LIKE :section')
               ->setParameter('section', '%_homme%');
        } elseif ($section === 'femme') {
            $qb->andWhere('LOWER(e.typeExercice) LIKE :section')
               ->setParameter('section', '%_femme%');
        }

        // ── Tri ─────────────────────────────────────────────────────────────
        match ($sort) {
            'nom_desc'  => $qb->orderBy('e.nomExercice', 'DESC'),
            'type_asc'  => $qb->orderBy('e.typeExercice', 'ASC')->addOrderBy('e.nomExercice', 'ASC'),
            'type_desc' => $qb->orderBy('e.typeExercice', 'DESC')->addOrderBy('e.nomExercice', 'ASC'),
            default     => $qb->orderBy('e.nomExercice', 'ASC'),  // nom_asc
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Find distinct exercise types (for the form datalist suggestions).
     *
     * @return string[]
     */
    public function findDistinctTypes(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.typeExercice')
            ->where('e.typeExercice IS NOT NULL')
            ->orderBy('e.typeExercice', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}