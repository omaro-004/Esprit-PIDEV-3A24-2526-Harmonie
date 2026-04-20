<?php

namespace App\Entity;

use App\Repository\DemandeReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeReservationRepository::class)]
#[ORM\Table(name: 'demande_reservation')]
class DemandeReservation
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';

    public const STATUT_ACCEPTEE = 'ACCEPTEE';

    public const STATUT_REFUSEE = 'REFUSEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'demandeReservations')]
    #[ORM\JoinColumn(name: 'evenement_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'salle_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Salle $salle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $utilisateur = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(name: 'date_demande', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDemande = null;

    #[ORM\Column(name: 'commentaire_admin', length: 500, nullable: true)]
    private ?string $commentaireAdmin = null;

    public function __construct()
    {
        $this->dateDemande = new \DateTimeImmutable('now');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;

        return $this;
    }

    public function getSalle(): ?Salle
    {
        return $this->salle;
    }

    public function setSalle(?Salle $salle): static
    {
        $this->salle = $salle;

        return $this;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateDemande(): ?\DateTimeImmutable
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeImmutable $dateDemande): static
    {
        $this->dateDemande = $dateDemande;

        return $this;
    }

    public function getCommentaireAdmin(): ?string
    {
        return $this->commentaireAdmin;
    }

    public function setCommentaireAdmin(?string $commentaireAdmin): static
    {
        $this->commentaireAdmin = $commentaireAdmin;

        return $this;
    }
}
