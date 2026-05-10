<?php

namespace App\Entity;

use App\Repository\SessionMeditationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SessionMeditationRepository::class)]
#[ORM\Table(name: 'session_meditation')]
class SessionMeditation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(name: 'auteur', type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: "L'auteur est obligatoire.")]
    #[Assert\Length(
        min: 2, max: 100,
        minMessage: "L'auteur doit contenir au moins {{ limit }} caractères.",
        maxMessage: "L'auteur ne peut pas dépasser {{ limit }} caractères."
    )]
    private string $auteur = '';

    #[ORM\Column(name: 'duree', type: Types::INTEGER, nullable: true)]
    #[Assert\NotBlank(message: 'La durée est obligatoire.')]
    #[Assert\Positive(message: 'La durée doit être positive.')]
    #[Assert\Range(
        min: 5, max: 60,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes.'
    )]
    private ?int $duree = null;

    #[ORM\Column(name: 'theme', type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le thème est obligatoire.')]
    #[Assert\Length(
        min: 3, max: 100,
        minMessage: 'Le thème doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le thème ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $theme = '';

    #[ORM\Column(name: 'audio_url', type: Types::STRING, length: 500, nullable: true)]
    #[Assert\NotBlank(message: "Le lien YouTube est obligatoire.")]
    #[Assert\Url(message: "L'URL doit être valide.")]
    private ?string $audioUrl = null;

    /** @var Collection<int, Conseil> */
    #[ORM\OneToMany(targetEntity: Conseil::class, mappedBy: 'session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $conseils;

    public function __construct()
    {
        $this->conseils = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getAuteur(): ?string { return $this->auteur; }
    public function setAuteur(?string $auteur): self { $this->auteur = $auteur ?? ''; return $this; }

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(?int $duree): self { $this->duree = $duree; return $this; }

    public function getTheme(): ?string { return $this->theme; }
    public function setTheme(?string $theme): self { $this->theme = $theme ?? ''; return $this; }

    public function getAudioUrl(): ?string { return $this->audioUrl; }
    public function setAudioUrl(?string $audioUrl): self { $this->audioUrl = ($audioUrl !== null && $audioUrl !== '') ? $audioUrl : null; return $this; }

    /** @return Collection<int, Conseil> */
    public function getConseils(): Collection { return $this->conseils; }

    public function addConseil(Conseil $conseil): self
    {
        if (!$this->conseils->contains($conseil)) {
            $this->conseils->add($conseil);
            $conseil->setSession($this);
        }
        return $this;
    }

    public function removeConseil(Conseil $conseil): self
    {
        if ($this->conseils->removeElement($conseil)) {
            if ($conseil->getSession() === $this) {
                $conseil->setSession(null);
            }
        }
        return $this;
    }
}
