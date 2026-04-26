<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: "categorie")]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_categorie", type: "integer")]
    private ?int $idCategorie = null;

    #[ORM\Column(name: "nom_categorie", length: 255)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(
    min: 3, minMessage: "Le nom doit contenir au moins {{ limit }} caractères.",
    max: 100, maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $nomCategorie = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: "La description ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $description = null;

    #[ORM\Column(name: "date_creation", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation;

    public function getIdCategorie(): ?int { return $this->idCategorie; }

    public function getNomCategorie(): ?string { return $this->nomCategorie; }
    public function setNomCategorie(string $v): static { $this->nomCategorie = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }

    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    protected function setDateCreation(\DateTimeInterface $v): static { $this->dateCreation = $v; return $this; }
}