<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(nullable: true)]
    private ?int $priorite = null;

    #[ORM\Column(name: 'rappel_actif', nullable: true)]
    private ?bool $rappelActif = null;

    #[ORM\Column(name: 'type_evenement', length: 50, nullable: true)]
    private ?string $typeEvenement = null;

    #[ORM\ManyToOne(inversedBy: 'evenements')]
    #[ORM\JoinColumn(name: 'calendrier_id', referencedColumnName: 'id', nullable: true)]
    private ?Calendrier $calendrier = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $approuve = false;

    #[ORM\ManyToOne(inversedBy: 'evenements')]
    #[ORM\JoinColumn(name: 'salle_id', referencedColumnName: 'id', nullable: true)]
    private ?Salle $salle = null;

    #[ORM\Column(name: 'statut_demande_salle', length: 20, nullable: true)]
    private ?string $statutDemandeSalle = null;

    /** Type normalisé : cours, reunion, loisir, autre (colonne event_type). */
    #[Assert\NotBlank(message: "Choisissez un type d'événement.")]
    #[ORM\Column(name: 'event_type', length: 20, nullable: true)]
    private ?string $eventType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleEventId = null;

    /** presentiel | en_ligne */
    #[Assert\NotBlank(message: 'Indiquez le mode de lieu.')]
    #[ORM\Column(name: 'lieu_type', length: 20, nullable: true)]
    private ?string $lieuType = 'en_ligne';

    #[ORM\Column(name: 'lieu_adresse', length: 255, nullable: true)]
    private ?string $lieuAdresse = null;

    #[ORM\Column(name: 'reminder_minutes', options: ['default' => 15])]
    private int $reminderMinutes = 15;

    #[ORM\Column(name: 'reminder_sent', options: ['default' => false])]
    private bool $reminderSent = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'proprietaire_id', referencedColumnName: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $proprietaire = null;

    /** @var Collection<int, DemandeReservation> */
    #[ORM\OneToMany(targetEntity: DemandeReservation::class, mappedBy: 'evenement', orphanRemoval: true, cascade: ['persist'])]
    private Collection $demandeReservations;

    public function __construct()
    {
        $this->demandeReservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    public function getPriorite(): ?int
    {
        return $this->priorite;
    }

    public function setPriorite(?int $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function getRappelActif(): ?bool
    {
        return $this->rappelActif;
    }

    public function setRappelActif(?bool $rappelActif): static
    {
        $this->rappelActif = $rappelActif;

        return $this;
    }

    public function getTypeEvenement(): ?string
    {
        return $this->typeEvenement;
    }

    public function setTypeEvenement(?string $typeEvenement): static
    {
        $this->typeEvenement = $typeEvenement;

        return $this;
    }

    public function getCalendrier(): ?Calendrier
    {
        return $this->calendrier;
    }

    public function setCalendrier(?Calendrier $calendrier): static
    {
        $this->calendrier = $calendrier;

        return $this;
    }

    public function isApprouve(): bool
    {
        return $this->approuve;
    }

    public function setApprouve(bool $approuve): static
    {
        $this->approuve = $approuve;

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

    public function getStatutDemandeSalle(): ?string
    {
        return $this->statutDemandeSalle;
    }

    public function setStatutDemandeSalle(?string $statutDemandeSalle): static
    {
        $this->statutDemandeSalle = $statutDemandeSalle;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getLieuType(): ?string
    {
        return $this->lieuType;
    }

    public function setLieuType(?string $lieuType): static
    {
        $this->lieuType = $lieuType;

        return $this;
    }

    public function getLieuAdresse(): ?string
    {
        return $this->lieuAdresse;
    }

    public function setLieuAdresse(?string $lieuAdresse): static
    {
        $this->lieuAdresse = $lieuAdresse;

        return $this;
    }

    public function getGoogleEventId(): ?string
    {
        return $this->googleEventId;
    }

    public function setGoogleEventId(?string $googleEventId): static
    {
        $this->googleEventId = $googleEventId;

        return $this;
    }

    public function getReminderMinutes(): int
    {
        return $this->reminderMinutes;
    }

    public function setReminderMinutes(int $reminderMinutes): static
    {
        $this->reminderMinutes = $reminderMinutes;

        return $this;
    }

    public function isReminderSent(): bool
    {
        return $this->reminderSent;
    }

    public function getReminderSent(): bool
    {
        return $this->reminderSent;
    }

    public function setReminderSent(bool $reminderSent): static
    {
        $this->reminderSent = $reminderSent;

        return $this;
    }

    public function getProprietaire(): ?User
    {
        return $this->proprietaire;
    }

    public function setProprietaire(?User $proprietaire): static
    {
        $this->proprietaire = $proprietaire;

        return $this;
    }

    /** @return Collection<int, DemandeReservation> */
    public function getDemandeReservations(): Collection
    {
        return $this->demandeReservations;
    }

    public function addDemandeReservation(DemandeReservation $demandeReservation): static
    {
        if (!$this->demandeReservations->contains($demandeReservation)) {
            $this->demandeReservations->add($demandeReservation);
            $demandeReservation->setEvenement($this);
        }

        return $this;
    }

    public function removeDemandeReservation(DemandeReservation $demandeReservation): static
    {
        $this->demandeReservations->removeElement($demandeReservation);

        return $this;
    }

    #[Assert\Callback]
    public function validatePresentiel(ExecutionContextInterface $context): void
    {
        if ('presentiel' !== $this->lieuType) {
            return;
        }
        $addr = $this->lieuAdresse ? trim($this->lieuAdresse) : '';
        if ('' === $addr && null === $this->salle) {
            $context->buildViolation("En présentiel, indiquez où se déroule l'événement ou choisissez une salle Esprit.")
                ->atPath('lieuAdresse')
                ->addViolation();
        }
    }
}
