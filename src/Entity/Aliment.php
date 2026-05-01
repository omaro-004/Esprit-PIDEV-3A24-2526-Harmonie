<?php

namespace App\Entity;

use App\Repository\AlimentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlimentRepository::class)]
#[ORM\Table(name: 'aliment')]
class Aliment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_aliment', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom_aliment', length: 100)]
    private ?string $nomAliment = null;

    #[ORM\Column(name: 'calories_pour_100g', type: 'integer')]
    private ?int $caloriesPour100g = null;

    #[ORM\Column(type: 'float')]
    private ?float $proteines = null;

    #[ORM\Column(type: 'float')]
    private ?float $glucides = null;

    #[ORM\Column(type: 'float')]
    private ?float $lipides = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomAliment(): ?string
    {
        return $this->nomAliment;
    }

    public function setNomAliment(string $nomAliment): static
    {
        $this->nomAliment = $nomAliment;
        return $this;
    }

    public function getCaloriesPour100g(): ?int
    {
        return $this->caloriesPour100g;
    }

    public function setCaloriesPour100g(int $caloriesPour100g): static
    {
        $this->caloriesPour100g = $caloriesPour100g;
        return $this;
    }

    public function getProteines(): ?float
    {
        return $this->proteines;
    }

    public function setProteines(float $proteines): static
    {
        $this->proteines = $proteines;
        return $this;
    }

    public function getGlucides(): ?float
    {
        return $this->glucides;
    }

    public function setGlucides(float $glucides): static
    {
        $this->glucides = $glucides;
        return $this;
    }

    public function getLipides(): ?float
    {
        return $this->lipides;
    }

    public function setLipides(float $lipides): static
    {
        $this->lipides = $lipides;
        return $this;
    }
}