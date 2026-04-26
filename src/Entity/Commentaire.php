<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: "commentaire")]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_commentaire", type: "integer")]
    private ?int $idCommentaire = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le commentaire est obligatoire.")]
    #[Assert\Length(min: 3, minMessage: "Le commentaire doit contenir au moins {{ limit }} caractères.")]
    private ?string $contenu = '';

    #[ORM\Column(name: "date_commentaire", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCommentaire;

    #[ORM\Column(name: "id_post", type: "integer")]
    private ?int $idPost = 0;

    #[ORM\Column(name: "user_id", type: "integer")]
    private ?int $userId = 0;

    public function getIdCommentaire(): ?int { return $this->idCommentaire; }
    public function getContenu(): ?string { return $this->contenu; }
    
    public function setContenu(?string $contenu): static 
{ 
    $this->contenu = $contenu; 
    return $this; 
}
    
    public function getDateCommentaire(): ?\DateTimeInterface { return $this->dateCommentaire; }
    public function setDateCommentaire(\DateTimeInterface $d): static { $this->dateCommentaire = $d; return $this; }
    public function getIdPost(): ?int { return $this->idPost; }
    public function setIdPost(int $idPost): static { $this->idPost = $idPost; return $this; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(int $userId): static { $this->userId = $userId; return $this; }
}