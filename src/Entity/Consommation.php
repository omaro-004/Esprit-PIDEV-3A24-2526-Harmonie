<?php

namespace App\Entity;

use App\Repository\ConsommationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsommationRepository::class)]
#[ORM\Table(name: 'consommation')]
class Consommation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_consommation', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Aliment::class)]
    #[ORM\JoinColumn(name: 'id_aliment', referencedColumnName: 'id_aliment', nullable: true)]
    #[Assert\NotNull(message: 'Veuillez choisir un aliment.')]
    private ?Aliment $aliment = null;

    #[ORM\Column(name: 'date_consommation', type: 'datetime')]
    private ?\DateTime $dateConsommation = null;

    #[ORM\Column(name: 'type_repas', length: 50)]
    #[Assert\NotBlank(message: 'Veuillez choisir un type de repas.')]
    private ?string $typeRepas = null;

    #[ORM\Column(name: 'poids_grammes', type: 'integer', nullable: true)]
    #[Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0.')]
    private ?int $poidsGrammes = null;

    #[ORM\Column(name: 'quantite_eau_ml', type: 'integer', nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0)]
    private ?int $quantiteEauMl = null;

    #[ORM\Column(name: 'user_id', type: 'integer', nullable: true)]
    private ?int $userId = null;

    // ────────────────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAliment(): ?Aliment
    {
        return $this->aliment;
    }

    public function setAliment(?Aliment $aliment): static
    {
        $this->aliment = $aliment;
        return $this;
    }

    public function getDateConsommation(): ?\DateTime
    {
        return $this->dateConsommation;
    }

    public function setDateConsommation(\DateTime $dateConsommation): static
    {
        $this->dateConsommation = $dateConsommation;
        return $this;
    }

    public function getTypeRepas(): ?string
    {
        return $this->typeRepas;
    }

    public function setTypeRepas(string $typeRepas): static
    {
        $this->typeRepas = $typeRepas;
        return $this;
    }

    public function getPoidsGrammes(): ?int
    {
        return $this->poidsGrammes;
    }

    public function setPoidsGrammes(int $poidsGrammes): static
    {
        $this->poidsGrammes = $poidsGrammes;
        return $this;
    }

    public function getQuantiteEauMl(): ?int
    {
        return $this->quantiteEauMl;
    }

    public function setQuantiteEauMl(?int $quantiteEauMl): static
    {
        $this->quantiteEauMl = $quantiteEauMl;
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

    /**
     * Calcule les calories de cette consommation en fonction du poids et de l'aliment.
     */
    public function getCalories(): float
    {
        if ($this->aliment === null || $this->poidsGrammes === null) {
            return 0;
        }
        return ($this->aliment->getCaloriesPour100g() * $this->poidsGrammes) / 100;
    }

    public function getProteines(): float
    {
        if ($this->aliment === null || $this->poidsGrammes === null) return 0;
        return ($this->aliment->getProteines() * $this->poidsGrammes) / 100;
    }

    public function getGlucides(): float
    {
        if ($this->aliment === null || $this->poidsGrammes === null) return 0;
        return ($this->aliment->getGlucides() * $this->poidsGrammes) / 100;
    }

    public function getLipides(): float
    {
        if ($this->aliment === null || $this->poidsGrammes === null) return 0;
        return ($this->aliment->getLipides() * $this->poidsGrammes) / 100;
    }
}