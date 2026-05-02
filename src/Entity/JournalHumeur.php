<?php

namespace App\Entity;

use App\Enum\Humeur;
use App\Repository\JournalHumeurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JournalHumeurRepository::class)]
#[ORM\Table(name: 'journal_humeur')]
class JournalHumeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'date', type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date est obligatoire.')]
    private ?\DateTimeInterface $dateJournal = null;

    #[ORM\Column(name: 'humeur', type: Types::STRING, length: 50, enumType: Humeur::class)]
    #[Assert\NotBlank(message: "L'humeur est obligatoire.")]
    private ?Humeur $humeur = null;

    #[ORM\Column(name: 'score', type: Types::INTEGER)]
    private int $score = 3;

    #[ORM\Column(name: 'contenu', type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $contenu = '';

    #[ORM\Column(name: 'avatar_image_url', type: Types::STRING, length: 500, nullable: true)]
    private ?string $avatarImageUrl = null;

    #[ORM\Column(name: 'is_read_by_admin', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isReadByAdmin = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->dateJournal = new \DateTime();
        $this->createdAt   = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getDateJournal(): ?\DateTimeInterface { return $this->dateJournal; }
    public function setDateJournal(?\DateTimeInterface $dateJournal): self { $this->dateJournal = $dateJournal; return $this; }

    public function getHumeur(): ?Humeur { return $this->humeur; }
    public function setHumeur(?Humeur $humeur): self
    {
        $this->humeur = $humeur;
        if ($humeur !== null) {
            $this->score = $humeur->score();
        }
        return $this;
    }

    public function getScore(): int { return $this->score; }

    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(?string $contenu): self { $this->contenu = $contenu ?? ''; return $this; }

    public function getAvatarImageUrl(): ?string { return $this->avatarImageUrl; }
    public function setAvatarImageUrl(?string $avatarImageUrl): self { $this->avatarImageUrl = $avatarImageUrl; return $this; }

    public function isReadByAdmin(): bool { return $this->isReadByAdmin; }
    public function setIsReadByAdmin(bool $v): self { $this->isReadByAdmin = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
