<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert; 

#[ORM\Entity]
#[ORM\Table(name: "post")]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_post", type: "integer")]
    private ?int $idPost = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(
    min: 3, minMessage: "Le titre doit contenir au moins {{ limit }} caractères.",
    max: 150, maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu est obligatoire.")]
    #[Assert\Length(min: 10, minMessage: "Le contenu doit contenir au moins {{ limit }} caractères.")]
    private ?string $contenu = null;

    #[ORM\Column(name: "date_creation", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: "user_id", type: "integer")]
    private ?int $userId = null;

    #[ORM\Column(name: "id_categorie", type: "integer")]
    private ?int $idCategorie = null;

    #[ORM\Column(name: "image_path", length: 255, nullable: true)]
    private ?string $imagePath = null;

    public function getIdPost(): ?int { return $this->idPost; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $d): static { $this->dateCreation = $d; return $this; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(int $userId): static { $this->userId = $userId; return $this; }
    public function getIdCategorie(): ?int { return $this->idCategorie; }
    public function setIdCategorie(int $id): static { $this->idCategorie = $id; return $this; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $path): static { $this->imagePath = $path; return $this; }
}