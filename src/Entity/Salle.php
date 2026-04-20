<?php

namespace App\Entity;

use App\Repository\SalleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalleRepository::class)]
#[ORM\Table(name: 'salle')]
class Salle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column(options: ['default' => 20])]
    private int $capacite = 20;

    #[ORM\Column(name: 'equipements', type: Types::TEXT, nullable: true)]
    private ?string $equipements = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $disponible = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    /** @var Collection<int, Seance> */
    #[ORM\OneToMany(targetEntity: Seance::class, mappedBy: 'salle')]
    private Collection $seances;

    /** @var Collection<int, Evenement> */
    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'salle')]
    private Collection $evenements;

    public function __construct()
    {
        $this->seances = new ArrayCollection();
        $this->evenements = new ArrayCollection();
        $this->dateCreation = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCapacite(): int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): static
    {
        $this->capacite = $capacite;

        return $this;
    }

    public function getEquipements(): ?string
    {
        return $this->equipements;
    }

    public function setEquipements(?string $equipements): static
    {
        $this->equipements = $equipements;

        return $this;
    }

    public function isDisponible(): bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): static
    {
        $this->disponible = $disponible;

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

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /** @return Collection<int, Seance> */
    public function getSeances(): Collection
    {
        return $this->seances;
    }

    /** @return Collection<int, Evenement> */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
