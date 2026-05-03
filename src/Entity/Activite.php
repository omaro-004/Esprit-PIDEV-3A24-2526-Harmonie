<?php

namespace App\Entity;

use App\Repository\ActiviteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Table(name: 'activite')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_activite')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Exercice::class)]
    #[ORM\JoinColumn(name: 'id_exercice', referencedColumnName: 'id_exercice', nullable: true)]
    private ?Exercice $exercice = null;

    #[ORM\Column(name: 'date_activite', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateActivite = null;

    #[ORM\Column(name: 'duree_minutes', nullable: true)]
    private ?int $dureeMinutes = null;

    #[ORM\Column(name: 'calories_brulees', nullable: true)]
    private ?int $caloriesBrulees = null;

    #[ORM\Column(name: 'nb_series', nullable: true)]
    private ?int $nbSeries = null;

    #[ORM\Column(name: 'nb_repetitions', nullable: true)]
    private ?int $nbRepetitions = null;

    #[ORM\Column(nullable: true)]
    private ?float $poids = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'user_id', nullable: true)]
    private ?int $userId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExercice(): ?Exercice
    {
        return $this->exercice;
    }

    public function setExercice(?Exercice $exercice): static
    {
        $this->exercice = $exercice;
        return $this;
    }

    public function getDateActivite(): ?\DateTimeInterface
    {
        return $this->dateActivite;
    }

    public function setDateActivite(\DateTimeInterface $dateActivite): static
    {
        $this->dateActivite = $dateActivite;
        return $this;
    }

    public function getDureeMinutes(): ?int
    {
        return $this->dureeMinutes;
    }

    public function setDureeMinutes(?int $dureeMinutes): static
    {
        $this->dureeMinutes = $dureeMinutes;
        return $this;
    }

    public function getCaloriesBrulees(): ?int
    {
        return $this->caloriesBrulees;
    }

    public function setCaloriesBrulees(?int $caloriesBrulees): static
    {
        $this->caloriesBrulees = $caloriesBrulees;
        return $this;
    }

    public function getNbSeries(): ?int
    {
        return $this->nbSeries;
    }

    public function setNbSeries(?int $nbSeries): static
    {
        $this->nbSeries = $nbSeries;
        return $this;
    }

    public function getNbRepetitions(): ?int
    {
        return $this->nbRepetitions;
    }

    public function setNbRepetitions(?int $nbRepetitions): static
    {
        $this->nbRepetitions = $nbRepetitions;
        return $this;
    }

    public function getPoids(): ?float
    {
        return $this->poids;
    }

    public function setPoids(?float $poids): static
    {
        $this->poids = $poids;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }
}