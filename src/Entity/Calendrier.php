<?php

namespace App\Entity;

use App\Repository\CalendrierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendrierRepository::class)]
#[ORM\Table(name: 'calendrier')]
class Calendrier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'vue_calendrier', length: 20, options: ['default' => 'MOIS'])]
    private string $vueCalendrier = 'MOIS';

    /** @var Collection<int, Tache> */
    #[ORM\OneToMany(targetEntity: Tache::class, mappedBy: 'calendrier')]
    private Collection $taches;

    /** @var Collection<int, Evenement> */
    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'calendrier')]
    private Collection $evenements;

    public function __construct()
    {
        $this->taches = new ArrayCollection();
        $this->evenements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVueCalendrier(): string
    {
        return $this->vueCalendrier;
    }

    public function setVueCalendrier(string $vueCalendrier): static
    {
        $this->vueCalendrier = $vueCalendrier;

        return $this;
    }

    /** @return Collection<int, Tache> */
    public function getTaches(): Collection
    {
        return $this->taches;
    }

    /** @return Collection<int, Evenement> */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function __toString(): string
    {
        return 'Harmony';
    }
}
