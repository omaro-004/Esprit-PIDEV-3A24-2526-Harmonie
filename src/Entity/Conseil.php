<?php

namespace App\Entity;

use App\Repository\ConseilRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConseilRepository::class)]
#[ORM\Table(name: 'conseil')]
class Conseil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SessionMeditation::class, inversedBy: 'conseils')]
    #[ORM\JoinColumn(name: 'session_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SessionMeditation $session = null;

    #[ORM\Column(name: 'contenu', type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu du conseil est obligatoire.')]
    #[Assert\Length(
        min: 5,
        minMessage: 'Le conseil doit contenir au moins {{ limit }} caractères.'
    )]
    private string $contenu = '';

    public function getId(): ?int { return $this->id; }

    public function getSession(): ?SessionMeditation { return $this->session; }
    public function setSession(?SessionMeditation $session): self { $this->session = $session; return $this; }

    public function getContenu(): ?string { return $this->contenu; }
    public function setContenu(?string $contenu): self { $this->contenu = $contenu ?? ''; return $this; }
}
