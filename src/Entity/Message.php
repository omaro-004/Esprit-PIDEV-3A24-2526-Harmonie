<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false)]
    private Conversation $conversation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'user_id', nullable: false)]
    private User $sender;

    #[ORM\Column(name: 'content', type: 'text')]
    private string $content;

    #[ORM\Column(name: 'sent_at', type: 'datetime')]
    private \DateTime $sentAt;

    #[ORM\Column(name: 'is_read', type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    public function __construct()
    {
        $this->sentAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getConversation(): Conversation { return $this->conversation; }
    public function setConversation(Conversation $c): self { $this->conversation = $c; return $this; }

    public function getSender(): User { return $this->sender; }
    public function setSender(User $u): self { $this->sender = $u; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $c): self { $this->content = $c; return $this; }

    public function getSentAt(): \DateTime { return $this->sentAt; }

    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): self { $this->isRead = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'conversationId' => $this->conversation->getId(),
            'senderId'       => $this->sender->getUserId(),
            'senderName'     => $this->sender->getUserPrenom() . ' ' . $this->sender->getUserNom(),
            'senderAvatar'   => $this->sender->getUserImagePath(),
            'senderInitial'  => strtoupper(mb_substr($this->sender->getUserPrenom(), 0, 1)),
            'content'        => $this->content,
            'sentAt'         => $this->sentAt->format('H:i'),
            'isRead'         => $this->isRead,
        ];
    }
}
