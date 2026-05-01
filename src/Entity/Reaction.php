<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "reaction")]
class Reaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id_reaction", type: "integer")]
    private ?int $idReaction = null;

    #[ORM\Column(name: "type_reaction", length: 50)]
    private ?string $typeReaction = null;

    #[ORM\Column(name: "date_reaction", type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateReaction = null;

    #[ORM\Column(name: "id_post", type: "integer")]
    private ?int $idPost = null;

    #[ORM\Column(name: "user_id", type: "integer")]
    private ?int $userId = null;

    public function getIdReaction(): ?int { return $this->idReaction; }
    public function getTypeReaction(): ?string { return $this->typeReaction; }
    public function setTypeReaction(string $v): static { $this->typeReaction = $v; return $this; }
    public function getDateReaction(): ?\DateTimeInterface { return $this->dateReaction; }
    public function setDateReaction(\DateTimeInterface $v): static { $this->dateReaction = $v; return $this; }
    public function getIdPost(): ?int { return $this->idPost; }
    public function setIdPost(int $v): static { $this->idPost = $v; return $this; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(int $v): static { $this->userId = $v; return $this; }
}
