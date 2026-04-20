<?php

namespace App\Entity;

use App\Repository\SeanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\Table(name: 'seance')]
class Seance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateFin;

    #[ORM\ManyToOne(inversedBy: 'seances')]
    #[ORM\JoinColumn(name: 'salle_id', referencedColumnName: 'id', nullable: false)]
    private ?Salle $salle = null;

    #[ORM\Column]
    private bool $confirmee = false;

    #[ORM\Column(name: 'type_seance', length: 50, options: ['default' => 'COURS'])]
    private string $typeSeance = 'COURS';

    #[ORM\Column(name: 'nombre_participants', options: ['default' => 0])]
    private int $nombreParticipants = 0;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    public function __construct()
    {
        $this->dateDebut = new \DateTimeImmutable();
        $this->dateFin = new \DateTimeImmutable('@0');
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): \DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getSalle(): ?Salle
    {
        return $this->salle;
    }

    public function setSalle(?Salle $salle): static
    {
        $this->salle = $salle;

        return $this;
    }

    public function isConfirmee(): bool
    {
        return $this->confirmee;
    }

    public function setConfirmee(bool $confirmee): static
    {
        $this->confirmee = $confirmee;

        return $this;
    }

    public function getTypeSeance(): string
    {
        return $this->typeSeance;
    }

    public function setTypeSeance(string $typeSeance): static
    {
        $this->typeSeance = $typeSeance;

        return $this;
    }

    public function getNombreParticipants(): int
    {
        return $this->nombreParticipants;
    }

    public function setNombreParticipants(int $nombreParticipants): static
    {
        $this->nombreParticipants = $nombreParticipants;

        return $this;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }
}
