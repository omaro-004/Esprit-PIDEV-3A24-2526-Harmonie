<?php

namespace App\Entity;

use App\Repository\TacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TacheRepository::class)]
#[ORM\Table(name: 'tache')]
class Tache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $priorite = null;

    #[ORM\Column(name: 'github_issue_number', nullable: true)]
    private ?int $githubIssueNumber = null;

    #[ORM\Column(name: 'github_repo', length: 190, nullable: true)]
    private ?string $githubRepo = null;

    #[ORM\Column(name: 'statut_tache', length: 20, options: ['default' => 'A_FAIRE'])]
    private string $statutTache = 'A_FAIRE';

    #[ORM\ManyToOne(inversedBy: 'taches')]
    #[ORM\JoinColumn(name: 'calendrier_id', referencedColumnName: 'id', nullable: false)]
    private Calendrier $calendrier;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeInterface $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getPriorite(): ?string
    {
        return $this->priorite;
    }

    public function setPriorite(?string $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function getGithubIssueNumber(): ?int
    {
        return $this->githubIssueNumber;
    }

    public function setGithubIssueNumber(?int $githubIssueNumber): static
    {
        $this->githubIssueNumber = $githubIssueNumber;

        return $this;
    }

    public function getGithubRepo(): ?string
    {
        return $this->githubRepo;
    }

    public function setGithubRepo(?string $githubRepo): static
    {
        $this->githubRepo = $githubRepo;

        return $this;
    }

    public function getStatutTache(): string
    {
        return $this->statutTache;
    }

    public function setStatutTache(string $statutTache): static
    {
        $this->statutTache = $statutTache;

        return $this;
    }

    public function getCalendrier(): Calendrier
    {
        return $this->calendrier;
    }

    public function setCalendrier(Calendrier $calendrier): static
    {
        $this->calendrier = $calendrier;

        return $this;
    }
}
