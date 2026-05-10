<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** Recherche live par nom ou prénom ou email
     * @return User[]
     */
    public function searchByName(string $q): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.userNom LIKE :q OR u.userPrenom LIKE :q OR u.userEmail LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->andWhere('u.typeUtilisateur = :type')
            ->setParameter('type', 'ETUDIANT')
            ->orderBy('u.userId', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Tous les étudiants actifs
     * @return User[]
     */
    public function findActiveStudents(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.typeUtilisateur = :type AND u.isActive = 1')
            ->setParameter('type', 'ETUDIANT')
            ->orderBy('u.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Tous les étudiants suspendus
     * @return User[]
     */
    public function findSuspendedStudents(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.typeUtilisateur = :type AND u.isActive = 0')
            ->setParameter('type', 'ETUDIANT')
            ->orderBy('u.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Tous les étudiants (actifs + suspendus)
     * @return User[]
     */
    public function findAllStudents(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.typeUtilisateur = :type')
            ->setParameter('type', 'ETUDIANT')
            ->orderBy('u.userId', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
