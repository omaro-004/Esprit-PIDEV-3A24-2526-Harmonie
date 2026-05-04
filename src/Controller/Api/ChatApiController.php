<?php

namespace App\Controller\Api;

use App\Entity\Evenement;
use App\Entity\Tache;
use App\Entity\User;
use App\Repository\CalendrierRepository;
use App\Repository\EvenementRepository;
use App\Repository\TacheRepository;
use App\Service\Kanban\KanbanRealtimeNotifier;
use App\Service\Telegram\TelegramNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/chat')]
final class ChatApiController extends AbstractController
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TacheRepository $tacheRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly CalendrierRepository $calendrierRepository,
        private readonly EntityManagerInterface $em,
        private readonly KanbanRealtimeNotifier $realtimeNotifier,
        private readonly TelegramNotifier $telegramNotifier,
        #[Autowire('%env(string:GROQ_API_KEY)%')]
        private readonly string $groqApiKey,
        #[Autowire('%env(string:GROQ_CHAT_MODEL)%')]
        private readonly string $groqModel,
    ) {
    }

    #[Route('', name: 'api_chat', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'JSON invalide'], 400);
        }

        $userMessage = trim((string) ($payload['userMessage']));
        $history     = $payload['history'] ?? [];
        $model       = (string) ($payload['model'] ?? 'gemini-2.5-flash-lite');
        $confirmed   = (bool) ($payload['confirmed'] ?? false);
        $confirmedData = is_array($payload['confirmedData'] ?? null) ? $payload['confirmedData'] : null;

        if ('' === $userMessage) {
            return $this->json(['error' => 'Message utilisateur requis'], 422);
        }

        // If user confirmed a pending action → execute directly without calling AI again
        if ($confirmed && isset($payload['pendingAction'])) {
            $pendingAction = $payload['pendingAction'];
            $action     = strtoupper((string) ($pendingAction['action'] ?? 'NONE'));
            $actionData = $confirmedData ?? (is_array($pendingAction['data'] ?? null) ? $pendingAction['data'] : []);
            if ('NONE' !== $action) {
                $this->executeAction($action, $actionData);
                $this->realtimeNotifier->dispatch('chat.mutation', ['action' => $action]);
            }
            return $this->json(['message' => '✅ Fait !', 'dataChanged' => true]);
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis'));
        $tasks  = $this->serializeTasks();
        $events = $this->serializeEvents();

        $tomorrow   = $now->modify('+1 day')->format('Y-m-d');
        $nextMonday = $now->modify('next monday')->format('Y-m-d');

        $systemPrompt = <<<PROMPT
Tu es Harmonie Assistant, un assistant de planification conversationnel et intelligent. Réponds toujours en français, de façon naturelle et décontractée.

━━━ CONTEXTE TEMPOREL ━━━
Maintenant   : {$now->format('Y-m-d H:i')} ({$now->format('l')})
Demain       : {$tomorrow}
Lundi prochain: {$nextMonday}
Interprète toujours "aujourd'hui", "demain", "vendredi", "ce soir", "cette semaine", "le 3 mai", etc. par rapport à cette date.

━━━ TON RÔLE ━━━
Tu dois comprendre l'intention de l'utilisateur et converser naturellement pour obtenir les informations manquantes.
- Pose UNE SEULE question à la fois, naturellement, comme dans une vraie conversation.
- Déduis le maximum depuis ce que l'utilisateur dit : titre = sujet principal, heure mentionnée = début, ville = lieu, etc.
- Pour les heures : "8am" = 08:00, "6pm" = 18:00, "14h" = 14:00, "8 to 17" = 08:00 à 17:00, "endst 18" = fin à 18:00.
- Si une heure est donnée sans date → prend la prochaine occurrence de cette heure (aujourd'hui si pas encore passée, demain sinon).
- Utilise des valeurs par défaut intelligentes pour tout ce qui n'est pas mentionné :
  • durée non précisée → 1 heure
  • lieu non précisé → null
  • priorité → moyenne
  • statut tâche → A_FAIRE
  • type événement → autre
  • mode → en_ligne

━━━ QUAND DÉCLENCHER UNE ACTION ━━━
Tu retournes le JSON d'action UNIQUEMENT quand tu as TOUTES ces infos :
• Événement : titre + date + heure début + heure fin + lieu (si l'utilisateur n'a pas mentionné le lieu après que tu l'aies demandé, mets null et déclenche quand même)
• Tâche : titre (tout le reste peut être déduit)

Ordre de collecte pour un événement — une question à la fois :
1. Date → si manquante, demande : "C'est pour quel jour ?"
2. Heure début → si manquante, demande : "À quelle heure ça commence ?"
3. Heure fin → si manquante, demande : "Ça se termine à quelle heure ?"
4. Lieu → si manquant, demande : "C'est où ? (ou en ligne ?)" — si l'utilisateur répond vaguement ou dit "pas de lieu", mets null et déclenche l'action.

Ne pose JAMAIS deux questions en même temps.

━━━ FORMAT JSON D'ACTION ━━━
Quand tu as tout, réponds UNIQUEMENT avec ce JSON (aucun texte avant ou après) :
{"action":"ADD_EVENT|UPDATE_EVENT|DELETE_EVENT|ADD_TASK|UPDATE_TASK|DELETE_TASK|NONE","data":{...},"message":"Ce que tu as compris en une phrase"}

Champs événement : title, startTime (YYYY-MM-DD HH:mm:ss), endTime (YYYY-MM-DD HH:mm:ss), location, description, eventType (cours|reunion|loisir|autre), lieuType (presentiel|en_ligne)
Champs tâche    : title, dueDate (YYYY-MM-DD), priority (haute|moyenne|basse), statut (A_FAIRE|EN_COURS|TERMINEE), notes

━━━ DONNÉES ACTUELLES ━━━
ÉVÉNEMENTS : {$this->encodeJson($events)}
TÂCHES     : {$this->encodeJson($tasks)}

━━━ LECTURE / RÉPONSES SANS ACTION ━━━
action=NONE + Markdown. Événements : 📅 **Date** 🕐 **Heure** 📌 **Titre**. Tâches : `[TODO]` **Titre** (Échéance: Date).
Ne demande JAMAIS l'ID technique. Retrouve toujours par nom ou contexte.
PROMPT;

        if ('' === trim($this->groqApiKey)) {
            return $this->json(['error' => 'Clé API Groq manquante côté serveur.'], 500);
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($this->normalizeHistory($history) as $h) {
            $messages[] = [
                'role'    => 'model' === $h['role'] ? 'assistant' : 'user',
                'content' => $h['parts'][0]['text'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $attempt = 0;
        $delayUs = 500_000;
        do {
            ++$attempt;
            try {
                $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer '.$this->groqApiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'model'       => $this->groqModel,
                        'messages'    => $messages,
                        'temperature' => 0.3,
                        'max_tokens'  => 1024,
                    ],
                    'timeout' => 30,
                ]);

                $status = $response->getStatusCode();
                $data   = $response->toArray(false);

                if (429 === $status) {
                    if ($attempt < self::MAX_RETRIES) { usleep($delayUs); $delayUs *= 2; continue; }
                    return $this->json(['error' => 'Limite de requêtes Groq atteinte, réessayez dans quelques secondes.'], 429);
                }

                if ($status < 200 || $status >= 300) {
                    $errMsg = (string) ($data['error']['message'] ?? 'Erreur API Groq');
                    if ($attempt < self::MAX_RETRIES) { usleep($delayUs); $delayUs *= 2; continue; }
                    return $this->json(['error' => $errMsg], $status);
                }

                $replyText = (string) ($data['choices'][0]['message']['content']);
                if ('' === trim($replyText)) {
                    return $this->json(['error' => 'Réponse Groq vide'], 502);
                }

                $parsed = $this->extractActionJson($replyText);
                $dataChanged = false;
                $message = $replyText;
                $pendingAction = null;

                if (is_array($parsed)) {
                    $message = (string) ($parsed['message'] ?? $message);
                    $action = strtoupper((string) ($parsed['action'] ?? 'NONE'));
                    $actionData = is_array($parsed['data'] ?? null) ? $parsed['data'] : [];

                    if ('NONE' !== $action) {
                        // Return pending action for frontend confirmation — do NOT execute yet
                        $pendingAction = ['action' => $action, 'data' => $actionData];
                    }
                }

                return $this->json([
                    'message'       => $message,
                    'dataChanged'   => $dataChanged,
                    'pendingAction' => $pendingAction,
                ]);
            } catch (\Throwable $e) {
                if ($attempt >= self::MAX_RETRIES) {
                    return $this->json(['error' => 'Erreur API Gemini: '.$e->getMessage()], 500);
                }
                usleep($delayUs);
                $delayUs *= 2;
            }
        } while (true);
    }

    /**
     * @return array<mixed>
     */
    private function normalizeHistory(mixed $history): array
    {
        if (!is_array($history)) {
            return [];
        }

        $normalized = [];
        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = (string) ($item['role']);
            if (!in_array($role, ['user', 'model'], true)) {
                continue;
            }
            $parts = $item['parts'] ?? null;
            if (!is_array($parts) || !isset($parts[0]['text'])) {
                continue;
            }
            $text = trim((string) $parts[0]['text']);
            if ('' === $text) {
                continue;
            }
            $normalized[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
        }

        if (count($normalized) > 20) {
            $normalized = array_slice($normalized, -20);
        }

        return $normalized;
    }

    /**
     * @return array<mixed>
     */
    private function serializeTasks(): array
    {
        $tasks = $this->tacheRepository->findBy([], ['id' => 'DESC'], 100); // Limit to 100

        return array_map(static function (Tache $t): array {
            return [
                'id' => $t->getId(),
                'title' => $t->getNom(),
                'priority' => $t->getPriorite() ?? 'moyenne',
                'dueDate' => $t->getDeadline()?->format('Y-m-d'),
                'notes' => $t->getNotes(),
                'statut' => $t->getStatutTache(),
            ];
        }, $tasks);
    }

    /**
     * @return array<mixed>
     */
    private function serializeEvents(): array
    {
        $events = $this->evenementRepository->findBy([], ['id' => 'DESC'], 100); // Limit to 100

        return array_map(static function (Evenement $e): array {
            return [
                'id' => $e->getId(),
                'title' => $e->getTitre(),
                'description' => $e->getDescription(),
                'startTime' => $e->getDateDebut()?->format('Y-m-d H:i:s'),
                'endTime' => $e->getDateFin()?->format('Y-m-d H:i:s'),
                'location' => $e->getLieu(),
            ];
        }, $events);
    }

    /**
     * @return array<mixed>|null
     */
    private function extractActionJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded) && isset($decoded['action'])) {
            return $decoded;
        }

        if (preg_match('/```json\s*([\s\S]*?)```/i', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded) && isset($decoded['action'])) {
                return $decoded;
            }
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && isset($decoded['action'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     */
    private function executeAction(string $action, array $data): void
    {
        switch ($action) {
            case 'ADD_TASK':
                $task = new Tache();
                $task->setNom((string) ($data['title'] ?? 'Nouvelle tâche'));
                $task->setNotes((string) ($data['notes'] ?? null));
                $task->setPriorite((string) ($data['priority'] ?? 'moyenne'));
                if (!empty($data['dueDate'])) {
                    $task->setDeadline(new \DateTime((string) $data['dueDate']));
                }
                $task->setStatutTache($this->mapColumnToStatus((string) ($data['column'] ?? $data['statut'] ?? 'TODO')));
                if ($cal = $this->calendrierRepository->findPrimary()) {
                    $task->setCalendrier($cal);
                }
                $this->em->persist($task);
                $this->em->flush();
                $this->telegramNotifier->notifyTaskCreated($task, true);

                return;

            case 'UPDATE_TASK':
                $task = $this->findTask($data);
                if (!$task) {
                    return;
                }
                $oldStatus = (string) $task->getStatutTache();
                if (isset($data['title'])) $task->setNom((string) $data['title']);
                if (array_key_exists('notes', $data)) $task->setNotes((string) $data['notes']);
                if (isset($data['priority'])) $task->setPriorite((string) $data['priority']);
                if (isset($data['dueDate']) && '' !== (string) $data['dueDate']) $task->setDeadline(new \DateTime((string) $data['dueDate']));
                if (isset($data['column']) || isset($data['statut'])) {
                    $task->setStatutTache($this->mapColumnToStatus((string) ($data['column'] ?? $data['statut'])));
                }
                $this->em->flush();

                $newStatus = (string) $task->getStatutTache();
                if ($oldStatus !== $newStatus) {
                    if ('TERMINEE' === strtoupper($newStatus)) {
                        $this->telegramNotifier->notifyTaskDone($task, true);
                    } else {
                        $this->telegramNotifier->notifyTaskMoved($task, $oldStatus, $newStatus, true);
                    }
                } else {
                    $this->telegramNotifier->notifyTaskUpdated($task, true);
                }

                return;

            case 'DELETE_TASK':
                $task = $this->findTask($data);
                if (!$task) {
                    return;
                }
                $taskTitle = (string) ($task->getNom());
                $this->em->remove($task);
                $this->em->flush();
                $this->telegramNotifier->notifyTaskDeleted($taskTitle, true);

                return;

            case 'ADD_EVENT':
                $event = new Evenement();
                $event->setTitre((string) ($data['title'] ?? 'Nouvel événement'));
                $event->setDescription((string) ($data['description'] ?? null));
                $event->setLieu((string) ($data['location'] ?? null));
                $event->setRappelActif((bool) ($data['rappelActif'] ?? true));
                $event->setReminderMinutes(max(1, (int) ($data['reminderMinutes'] ?? 15)));
                $event->setReminderSent(false);
                if (!empty($data['startTime'])) $event->setDateDebut(new \DateTime((string) $data['startTime']));
                if (!empty($data['endTime'])) $event->setDateFin(new \DateTime((string) $data['endTime']));
                if ($cal = $this->calendrierRepository->findPrimary()) {
                    $event->setCalendrier($cal);
                }
                $currentUser = $this->getUser();
                if ($currentUser instanceof User) {
                    $event->setProprietaire($currentUser);
                }
                $this->em->persist($event);
                $this->em->flush();
                $this->telegramNotifier->notifyEventCreated($event, true);

                return;

            case 'UPDATE_EVENT':
                $event = $this->findEvent($data);
                if (!$event) {
                    return;
                }
                if (isset($data['title'])) $event->setTitre((string) $data['title']);
                if (array_key_exists('description', $data)) $event->setDescription((string) $data['description']);
                if (isset($data['location'])) $event->setLieu((string) $data['location']);
                if (!empty($data['startTime'])) $event->setDateDebut(new \DateTime((string) $data['startTime']));
                if (!empty($data['endTime'])) $event->setDateFin(new \DateTime((string) $data['endTime']));
                if (array_key_exists('rappelActif', $data)) $event->setRappelActif((bool) $data['rappelActif']);
                if (array_key_exists('reminderMinutes', $data)) $event->setReminderMinutes(max(1, (int) $data['reminderMinutes']));
                $event->setReminderSent(false);
                $this->em->flush();
                $this->telegramNotifier->notifyEventUpdated($event, true);

                return;

            case 'DELETE_EVENT':
                $event = $this->findEvent($data);
                if (!$event) {
                    return;
                }
                $eventTitle = (string) ($event->getTitre() ?? 'Événement');
                $eventStartAt = $event->getDateDebut();
                $this->em->remove($event);
                $this->em->flush();
                $this->telegramNotifier->notifyEventDeleted($eventTitle, $eventStartAt, true);

                return;
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function findTask(array $data): ?Tache
    {
        if (isset($data['id']) && is_numeric($data['id'])) {
            return $this->tacheRepository->find((int) $data['id']);
        }

        $title = trim((string) ($data['title']));
        if ('' === $title) {
            return null;
        }

        $needle = $this->normalize($title);
        foreach ($this->tacheRepository->findBy([], [], 1000) as $task) { // Limit search to 1000
            if (str_contains($this->normalize((string) $task->getNom()), $needle)) {
                return $task;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     */
    private function findEvent(array $data): ?Evenement
    {
        if (isset($data['id']) && is_numeric($data['id'])) {
            return $this->evenementRepository->find((int) $data['id']);
        }

        $title = trim((string) ($data['title']));
        if ('' === $title) {
            return null;
        }

        $needle = $this->normalize($title);
        foreach ($this->evenementRepository->findBy([], [], 1000) as $event) { // Limit search to 1000
            if (str_contains($this->normalize((string) ($event->getTitre())), $needle)) {
                return $event;
            }
        }

        return null;
    }

    private function mapColumnToStatus(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'DOING', 'EN_COURS' => 'EN_COURS',
            'DONE', 'TERMINEE', 'TERMINE' => 'TERMINEE',
            default => 'A_FAIRE',
        };
    }

    /**
     * @param array<mixed> $data
     */
    private function encodeJson(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (false !== $trans) {
            $value = $trans;
        }

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
