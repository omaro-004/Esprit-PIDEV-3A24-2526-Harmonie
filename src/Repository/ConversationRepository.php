<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findOrCreate(User $a, User $b, EntityManagerInterface $em): Conversation
    {
        [$u1, $u2] = $a->getUserId() < $b->getUserId() ? [$a, $b] : [$b, $a];

        $conv = $this->createQueryBuilder('c')
            ->where('c.user1 = :u1 AND c.user2 = :u2')
            ->setParameter('u1', $u1)
            ->setParameter('u2', $u2)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$conv) {
            $conv = (new Conversation())->setUser1($u1)->setUser2($u2);
            $em->persist($conv);
        }

        return $conv;
    }

    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.user1 = :u OR c.user2 = :u')
            ->setParameter('u', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
