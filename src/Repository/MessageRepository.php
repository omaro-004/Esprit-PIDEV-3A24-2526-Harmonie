<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findByConversation(Conversation $conv): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.conversation = :c')
            ->setParameter('c', $conv)
            ->orderBy('m.sentAt', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function markAsRead(Conversation $conv, User $reader): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', 1)
            ->where('m.conversation = :c AND m.sender != :r AND m.isRead = 0')
            ->setParameter('c', $conv)
            ->setParameter('r', $reader)
            ->getQuery()
            ->execute();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->join('m.conversation', 'c')
            ->where('(c.user1 = :u OR c.user2 = :u) AND m.sender != :u AND m.isRead = 0')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
