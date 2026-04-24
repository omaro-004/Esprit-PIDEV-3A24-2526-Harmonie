<?php

namespace App\Service\Domain;

use App\Entity\Calendrier;
use App\Entity\DemandeReservation;
use App\Entity\Evenement;
use App\Entity\Salle;
use App\Entity\Seance;
use App\Entity\Tache;
use App\Entity\User;
use App\Repository\CalendrierRepository;
use App\Repository\DemandeReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Github\GithubIssueService;
use App\Service\Kanban\KanbanRealtimeNotifier;
use App\Service\GoogleCalendarService;
use App\Service\Telegram\TelegramNotifier;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PlanningDomainService
{
    use PersistenceHelper;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly DemandeReservationRepository $demandeReservationRepository,
        private readonly CalendrierRepository $calendrierRepository,
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly GithubIssueService $githubIssueService,
        private readonly KanbanRealtimeNotifier $kanbanRealtimeNotifier,
        private readonly TelegramNotifier $telegramNotifier,
    ) {
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    protected function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    public function saveSeance(Seance $seance): void
    {
        $salle = $seance->getSalle();
        if ($salle && !$salle->isDisponible()) {
            throw new \DomainException("La salle choisie n'est pas disponible.");
        }
        if ($seance->getNombreParticipants() < 0) {
            throw new \DomainException('Le nombre de participants ne peut pas être négatif.');
        }
        $this->validateEntity($seance);
        $this->persistAndFlush($seance);
    }

    public function saveEvenement(Evenement $evenement, ?User $demandeur = null): void
    {
        $isCreate = null === $evenement->getId();
        if (!$isCreate) {
            $evenement->setReminderSent(false);
        }
        $debut = $evenement->getDateDebut();
        $fin = $evenement->getDateFin();
        if ($debut && $fin && $fin < $debut) {
            throw new \DomainException('La date de fin doit être postérieure à la date de début.');
        }
        $this->normalizeEvenementCalendrier($evenement);
        $this->syncEvenementChamps($evenement);
        $this->validateEntity($evenement);
        $this->entityManager->persist($evenement);
        $this->entityManager->flush();

        if ($reserver = ($demandeur ?? $evenement->getProprietaire())) {
            $this->googleCalendarService->syncEventToGoogle($evenement);
            $this->entityManager->flush();
        }

        $reserver = $demandeur ?? $evenement->getProprietaire();
        if ($reserver instanceof User
            && 'presentiel' === $evenement->getLieuType()
            && $evenement->getSalle()
        ) {
            $pending = $this->demandeReservationRepository->countPendingForEvenementAndSalle(
                $evenement,
                $evenement->getSalle(),
            );
            if (0 === $pending) {
                $d = new DemandeReservation();
                $d->setEvenement($evenement);
                $d->setSalle($evenement->getSalle());
                $d->setUtilisateur($reserver);
                $d->setStatut(DemandeReservation::STATUT_EN_ATTENTE);
                $d->setDateDemande(new \DateTimeImmutable());
                $evenement->addDemandeReservation($d);
                $evenement->setStatutDemandeSalle('EN_ATTENTE');
                $this->entityManager->persist($d);
                $this->entityManager->flush();
            }
        }

        if ($isCreate) {
            $this->telegramNotifier->notifyEventCreated($evenement);
        } else {
            $this->telegramNotifier->notifyEventUpdated($evenement);
        }
    }

    private function syncEvenementChamps(Evenement $e): void
    {
        $map = ['cours' => 'COURS', 'reunion' => 'REUNION', 'loisir' => 'LOISIR', 'autre' => 'AUTRE'];
        $et = $e->getEventType();
        if ($et && isset($map[$et])) {
            $e->setTypeEvenement($map[$et]);
        }
        if ('en_ligne' === $e->getLieuType()) {
            $e->setSalle(null);
            $e->setLieuAdresse(null);
            $e->setLieu('En ligne');

            return;
        }
        if ('presentiel' === $e->getLieuType()) {
            if ($e->getSalle()) {
                $e->setLieu($e->getSalle()->getNom());
            } elseif ($e->getLieuAdresse()) {
                $e->setLieu($e->getLieuAdresse());
            }
        }
    }

    public function saveTache(Tache $tache): void
    {
        $isCreate = null === $tache->getId();
        $oldStatus = null;
        if (!$isCreate) {
            $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($tache);
            $oldStatus = isset($original['statutTache']) ? (string) $original['statutTache'] : null;
        }

        $this->normalizeTacheCalendrier($tache);
        $this->validateEntity($tache);

        try {
            $this->githubIssueService->syncTask($tache);
        } catch (\RuntimeException $e) {
            throw new \DomainException($e->getMessage(), 0, $e);
        }

        $this->persistAndFlush($tache);
        $this->kanbanRealtimeNotifier->dispatch('task.updated', [
            'id' => $tache->getId(),
            'statut' => $tache->getStatutTache(),
        ]);

        $newStatus = (string) $tache->getStatutTache();
        if ($isCreate) {
            $this->telegramNotifier->notifyTaskCreated($tache);
        } elseif (null !== $oldStatus && $oldStatus !== $newStatus) {
            if ('TERMINEE' === strtoupper($newStatus)) {
                $this->telegramNotifier->notifyTaskDone($tache);
            } else {
                $this->telegramNotifier->notifyTaskMoved($tache, $oldStatus, $newStatus);
            }
        } else {
            $this->telegramNotifier->notifyTaskUpdated($tache);
        }
    }

    public function saveCalendrier(Calendrier $calendrier): void
    {
        $this->validateEntity($calendrier);
        $this->persistAndFlush($calendrier);
    }

    public function saveSalle(Salle $salle): void
    {
        if ($salle->getCapacite() < 1) {
            throw new \DomainException('La capacité doit être au moins 1.');
        }
        $this->validateEntity($salle);
        $this->persistAndFlush($salle);
    }

    public function removeSeance(Seance $seance): void
    {
        $this->removeAndFlush($seance);
    }

    public function removeEvenement(Evenement $evenement): void
    {
        $title = (string) ($evenement->getTitre() ?? 'Événement');
        $startAt = $evenement->getDateDebut();
        $this->googleCalendarService->deleteEventFromGoogle($evenement);
        $this->removeAndFlush($evenement);
        $this->telegramNotifier->notifyEventDeleted($title, $startAt);
    }

    public function removeTache(Tache $tache): void
    {
        $title = (string) ($tache->getNom() ?? 'Tâche');
        try {
            $this->githubIssueService->closeTaskIssueAsCancelled($tache);
        } catch (\RuntimeException $e) {
            throw new \DomainException($e->getMessage(), 0, $e);
        }

        $this->removeAndFlush($tache);
        $this->kanbanRealtimeNotifier->dispatch('task.deleted', [
            'id' => $tache->getId(),
        ]);
        $this->telegramNotifier->notifyTaskDeleted($title);
    }

    public function removeCalendrier(Calendrier $calendrier): void
    {
        $this->removeAndFlush($calendrier);
    }

    public function removeSalle(Salle $salle): void
    {
        $this->removeAndFlush($salle);
    }

    private function normalizeTacheCalendrier(Tache $tache): void
    {
        $cal = $this->calendrierRepository->findPrimary();
        if (null === $cal) {
            throw new \DomainException("Aucun calendrier n'est configuré. Contactez un administrateur.");
        }
        $tache->setCalendrier($cal);
    }

    private function normalizeEvenementCalendrier(Evenement $evenement): void
    {
        $cal = $this->calendrierRepository->findPrimary();
        if (null !== $cal) {
            $evenement->setCalendrier($cal);
        }
    }
}
