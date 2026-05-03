<?php

namespace App\Entity;

use App\Repository\ExerciceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciceRepository::class)]
#[ORM\Table(name: 'exercice')]
class Exercice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_exercice')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom_exercice', length: 100)]
    private string $nomExercice;

    #[ORM\Column(name: 'type_exercice', length: 100, nullable: true)]
    private ?string $typeExercice = null;

    #[ORM\Column(name: 'video_exercice', length: 255, nullable: true)]
    private ?string $videoExercice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomExercice(): ?string
    {
        return $this->nomExercice;
    }

    public function setNomExercice(string $nomExercice): static
    {
        $this->nomExercice = $nomExercice;
        return $this;
    }

    public function getTypeExercice(): ?string
    {
        return $this->typeExercice;
    }

    public function setTypeExercice(?string $typeExercice): static
    {
        $this->typeExercice = $typeExercice;
        return $this;
    }

    public function getVideoExercice(): ?string
    {
        return $this->videoExercice;
    }

    public function setVideoExercice(?string $videoExercice): static
    {
        $this->videoExercice = $videoExercice;
        return $this;
    }
}