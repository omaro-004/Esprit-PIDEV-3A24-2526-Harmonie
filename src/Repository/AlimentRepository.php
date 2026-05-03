<?php

namespace App\Repository;

use App\Entity\Aliment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Aliment>
 */
class AlimentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Aliment::class);
    }

    /**
     * Tous les aliments triés par nom.
     *
     * @return Aliment[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.nomAliment', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par nom (insensible à la casse).
     *
     * @return Aliment[]
     */
    public function search(string $q): array
    {
        return $this->createQueryBuilder('a')
            ->where('LOWER(a.nomAliment) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('a.nomAliment', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find aliment by exact name.
     */
    public function findByName(string $name): ?Aliment
    {
        return $this->createQueryBuilder('a')
            ->where('LOWER(a.nomAliment) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Construit un QueryBuilder filtré pour la pagination admin.
     *
     * Filtres supportés :
     *   - search      : recherche textuelle sur nom_aliment
     *   - cal_min     : calories minimales (pour 100g)
     *   - cal_max     : calories maximales (pour 100g)
     *   - prot_min    : protéines minimales (g/100g)
     *   - prot_max    : protéines maximales (g/100g)
     *   - id_from     : id_aliment >= X  (proxy « ajouté après »)
     *   - id_to       : id_aliment <= X  (proxy « ajouté avant »)
     *   - sort        : champ de tri  (nomAliment | caloriesPour100g | proteines | glucides | lipides)
     *   - direction   : ASC | DESC
     *
     * @param array<string, mixed> $filters
     */
    public function createFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a');

        // ── Recherche textuelle ─────────────────────────────────────
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $qb->andWhere('LOWER(a.nomAliment) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        // ── Filtre calories ─────────────────────────────────────────
        $calMin = $filters['cal_min'] ?? null;
        $calMax = $filters['cal_max'] ?? null;

        if ($calMin !== null && $calMin !== '' && is_numeric($calMin)) {
            $qb->andWhere('a.caloriesPour100g >= :calMin')
               ->setParameter('calMin', (int) $calMin);
        }
        if ($calMax !== null && $calMax !== '' && is_numeric($calMax)) {
            $qb->andWhere('a.caloriesPour100g <= :calMax')
               ->setParameter('calMax', (int) $calMax);
        }

        // ── Filtre protéines ────────────────────────────────────────
        $protMin = $filters['prot_min'] ?? null;
        $protMax = $filters['prot_max'] ?? null;

        if ($protMin !== null && $protMin !== '' && is_numeric($protMin)) {
            $qb->andWhere('a.proteines >= :protMin')
               ->setParameter('protMin', (float) $protMin);
        }
        if ($protMax !== null && $protMax !== '' && is_numeric($protMax)) {
            $qb->andWhere('a.proteines <= :protMax')
               ->setParameter('protMax', (float) $protMax);
        }

        // ── Filtre par plage d'ID (proxy date d'ajout) ──────────────
        $idFrom = $filters['id_from'] ?? null;
        $idTo   = $filters['id_to']   ?? null;

        if ($idFrom !== null && $idFrom !== '' && is_numeric($idFrom)) {
            $qb->andWhere('a.id >= :idFrom')
               ->setParameter('idFrom', (int) $idFrom);
        }
        if ($idTo !== null && $idTo !== '' && is_numeric($idTo)) {
            $qb->andWhere('a.id <= :idTo')
               ->setParameter('idTo', (int) $idTo);
        }

        // ── Tri ─────────────────────────────────────────────────────
        $allowedSorts = [
            'nomAliment'       => 'a.nomAliment',
            'caloriesPour100g' => 'a.caloriesPour100g',
            'proteines'        => 'a.proteines',
            'glucides'         => 'a.glucides',
            'lipides'          => 'a.lipides',
            'id'               => 'a.id',
        ];

        $sortField = (string) ($filters['sort'] ?? 'nomAliment');
        $direction = strtoupper((string) ($filters['direction'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $sortDql = $allowedSorts[$sortField] ?? 'a.nomAliment';
        $qb->orderBy($sortDql, $direction);

        // Tri secondaire stable par ID pour éviter les doublons de page
        if ($sortDql !== 'a.id') {
            $qb->addOrderBy('a.id', 'ASC');
        }

        return $qb;
    }

    /**
     * Compte total (pour affichage stats) avec les mêmes filtres.
     *
     * @param array<string, mixed> $filters
     */
    public function countFiltered(array $filters = []): int
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        $qb->select('COUNT(a.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statistiques globales rapides (min/max/avg) — utiles pour les sliders du filtre.
     *
     * @return array<string, mixed>
     */
    public function getGlobalStats(): array
    {
        $result = $this->createQueryBuilder('a')
            ->select(
                'MIN(a.caloriesPour100g) AS calMin',
                'MAX(a.caloriesPour100g) AS calMax',
                'MIN(a.proteines) AS protMin',
                'MAX(a.proteines) AS protMax',
                'MIN(a.id) AS idMin',
                'MAX(a.id) AS idMax'
            )
            ->getQuery()
            ->getSingleResult();

        return $result;
    }
}