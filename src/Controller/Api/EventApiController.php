<?php

namespace App\Controller\Api;

use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\EvenementRepository;
use App\Repository\CalendrierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\GoogleCalendarService;
use App\Service\Telegram\TelegramNotifier;

#[Route('/api/events')]
final class EventApiController extends AbstractController
{
    #[Route('', name: 'api_events_list', methods: ['GET'])]
    public function list(EvenementRepository $evenementRepository): JsonResponse
    {
        $events = $evenementRepository->findBy([], ['id' => 'DESC'], 100); // Limit to 100 results
        $data = [];
        foreach ($events as $event) {
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitre(),
                'description' => $event->getDescription(),
                'startTime' => $event->getDateDebut() ? $event->getDateDebut()->format('Y-m-d H:i:s') : null,
                'endTime' => $event->getDateFin() ? $event->getDateFin()->format('Y-m-d H:i:s') : null,
                'location' => $event->getLieu(),
                'priority' => $event->getPriorite(),
                'eventType' => $event->getEventType(),
                'lieuType' => $event->getLieuType(),
                'lieuAdresse' => $event->getLieuAdresse(),
            ];
        }
        return $this->json($data);
    }

    #[Route('', name: 'api_events_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, CalendrierRepository $calendrierRepository, GoogleCalendarService $googleService, TelegramNotifier $telegramNotifier): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $event = new Evenement();
            $event->setTitre($data['title'] ?? 'Nouvel événement');
            $event->setDescription($data['description'] ?? null);
            $event->setLieu($data['location'] ?? null);
            $event->setEventType($data['eventType'] ?? 'autre');
            $event->setLieuType($data['lieuType'] ?? 'en_ligne');
            $event->setLieuAdresse($data['lieuAdresse'] ?? null);
            $event->setPriorite($data['priority'] ?? 1);
            $event->setRappelActif((bool) ($data['rappelActif'] ?? true));
            $event->setReminderMinutes(max(1, (int) ($data['reminderMinutes'] ?? 15)));
            $event->setReminderSent(false);

            $currentUser = $this->getUser();
            if ($currentUser instanceof User) {
                $event->setProprietaire($currentUser);
            }

            if (!empty($data['startTime'])) {
                $event->setDateDebut(new \DateTime($data['startTime']));
            }
            if (!empty($data['endTime'])) {
                $event->setDateFin(new \DateTime($data['endTime']));
            }

            if ($cal = $calendrierRepository->findPrimary()) {
                $event->setCalendrier($cal);
            }

            $em->persist($event);
            $em->flush();
            $googleService->syncEventToGoogle($event);
            $telegramNotifier->notifyEventCreated($event);

            return $this->json([
                'id' => $event->getId(),
                'title' => $event->getTitre(),
                'startTime' => $event->getDateDebut() ? $event->getDateDebut()->format('Y-m-d H:i:s') : null,
                'location' => $event->getLieu(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'api_events_update', methods: ['PUT'])]
    public function update(Evenement $event, Request $request, EntityManagerInterface $em, GoogleCalendarService $googleService, TelegramNotifier $telegramNotifier): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['title'])) {
                $event->setTitre($data['title']);
            }
            if (isset($data['description'])) {
                $event->setDescription($data['description']);
            }
            if (isset($data['location'])) {
                $event->setLieu($data['location']);
            }
            if (isset($data['priority'])) {
                $event->setPriorite($data['priority']);
            }
            if (isset($data['startTime'])) {
                $event->setDateDebut(new \DateTime($data['startTime']));
            }
            if (isset($data['endTime'])) {
                $event->setDateFin(new \DateTime($data['endTime']));
            }
            if (array_key_exists('rappelActif', $data)) {
                $event->setRappelActif((bool) $data['rappelActif']);
            }
            if (array_key_exists('reminderMinutes', $data)) {
                $event->setReminderMinutes(max(1, (int) $data['reminderMinutes']));
            }

            $event->setReminderSent(false);

            $em->flush();
            $googleService->syncEventToGoogle($event);
            $telegramNotifier->notifyEventUpdated($event);

            return $this->json([
                'id' => $event->getId(),
                'title' => $event->getTitre(),
                'startTime' => $event->getDateDebut() ? $event->getDateDebut()->format('Y-m-d H:i:s') : null,
                'location' => $event->getLieu(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'api_events_delete', methods: ['DELETE'])]
    public function delete(Evenement $event, EntityManagerInterface $em, GoogleCalendarService $googleService, TelegramNotifier $telegramNotifier): JsonResponse
    {
        try {
            $title = (string) ($event->getTitre() ?? 'Événement');
            $startAt = $event->getDateDebut();
            $googleService->deleteEventFromGoogle($event);
            $em->remove($event);
            $em->flush();
            $telegramNotifier->notifyEventDeleted($title, $startAt);
            return $this->json(null, 204);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
