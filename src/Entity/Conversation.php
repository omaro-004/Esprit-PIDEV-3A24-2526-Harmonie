<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
#[ORM\UniqueConstraint(name: 'uq_conv_pair', columns: ['user1_id', 'user2_id'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    /** @phpstan-ignore-next-line ORM assigns id at runtime */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user1_id', referencedColumnName: 'user_id', nullable: false)]
    private User $user1;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user2_id', referencedColumnName: 'user_id', nullable: false)]
    private User $user2;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private \DateTime $updatedAt;

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: Message::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sentAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
        $this->messages  = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser1(): User { return $this->user1; }
    public function setUser1(User $u): self { $this->user1 = $u; return $this; }

    public function getUser2(): User { return $this->user2; }
    public function setUser2(User $u): self { $this->user2 = $u; return $this; }

    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $d): self { $this->updatedAt = $d; return $this; }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection { return $this->messages; }

    public function getOtherUser(User $me): User
    {
        return $this->user1->getUserId() === $me->getUserId()
            ? $this->user2
            : $this->user1;
    }

    public function countUnread(User $me): int
    {
        $count = 0;
        foreach ($this->messages as $msg) {
            if ($msg->getSender()->getUserId() !== $me->getUserId() && !$msg->isRead()) {
                $count++;
            }
        }
        return $count;
    }
}
