<?php

namespace App\Controller\Api;

use App\Entity\Tache;
use App\Repository\CalendrierRepository;
use App\Repository\TacheRepository;
use App\Service\Github\GithubIssueService;
use App\Service\Kanban\KanbanRealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class GithubWebhookController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(string:GITHUB_WEBHOOK_SECRET)%')]
        private readonly ?string $webhookSecret,
    ) {
    }

    #[Route('/api/webhooks/github', name: 'api_webhooks_github', methods: ['POST'])]
    public function __invoke(
        Request $request,
        TacheRepository $tacheRepository,
        CalendrierRepository $calendrierRepository,
        GithubIssueService $githubService,
        EntityManagerInterface $em,
        KanbanRealtimeNotifier $notifier,
    ): JsonResponse {
        if (!$this->isValidSignature($request)) {
            return $this->json(['ok' => false, 'error' => 'Signature invalide'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['ok' => true]);
        }

        $eventType = strtolower((string) $request->headers->get('X-GitHub-Event', ''));
        if ('projects_v2_item' === $eventType) {
            return $this->handleProjectV2ItemWebhook($payload, $tacheRepository, $calendrierRepository, $githubService, $em, $notifier);
        }

        if (!isset($payload['issue'])) {
            return $this->json(['ok' => true]);
        }

        $issue = $payload['issue'];
        $repo = (string) ($payload['repository']['full_name'] ?? '');
        $number = (int) ($issue['number'] ?? 0);
        if ('' === $repo || 0 === $number) {
            return $this->json(['ok' => true]);
        }

        $task = $tacheRepository->findOneBy([
            'githubIssueNumber' => $number,
            'githubRepo' => $repo,
        ]);

        if (!$task instanceof Tache) {
            $task = new Tache();
            $task->setGithubIssueNumber($number);
            $task->setGithubRepo($repo);
            if ($cal = $calendrierRepository->findPrimary()) {
                $task->setCalendrier($cal);
            } else {
                return $this->json(['ok' => false, 'error' => 'Calendrier principal absent'], 500);
            }
            $em->persist($task);
        }

        $task->setNom((string) ($issue['title'] ?? $task->getNom()));
        $task->setNotes((string) ($issue['body'] ?? $task->getNotes()));
        $task->setStatutTache($this->mapIssueToStatus($issue));

        $em->flush();

        $notifier->dispatch('github.webhook', [
            'action' => (string) ($payload['action'] ?? 'updated'),
            'taskId' => $task->getId(),
            'issue' => $number,
            'repo' => $repo,
        ]);

        return $this->json(['ok' => true]);
    }

    /**
     * @param array<mixed> $payload
     */
    private function handleProjectV2ItemWebhook(
        array $payload,
        TacheRepository $tacheRepository,
        CalendrierRepository $calendrierRepository,
        GithubIssueService $githubService,
        EntityManagerInterface $em,
        KanbanRealtimeNotifier $notifier,
    ): JsonResponse {
        $statusName = $this->extractProjectStatusName($payload);
        if (null === $statusName) {
            return $this->json(['ok' => true]);
        }

        $appStatus = $this->mapProjectStatusToApp($statusName);

        $repo = (string) ($payload['repository']['full_name'] ?? '');
        $number = (int) (($payload['projects_v2_item']['content']['number'] ?? $payload['content']['number']) ?? 0);
        $title = (string) (($payload['projects_v2_item']['content']['title'] ?? $payload['content']['title']) ?? '');

        if ('' === $repo || 0 === $number) {
            $nodeId = (string) (($payload['projects_v2_item']['content_node_id'] ?? $payload['projects_v2_item']['content']['node_id']) ?? '');
            if ('' !== $nodeId) {
                $resolved = $githubService->resolveIssueByNodeId($nodeId);
                if (is_array($resolved)) {
                    $repo = (string) ($resolved['repo'] ?? $repo);
                    $number = (int) ($resolved['number'] ?? $number);
                    if ('' === $title) {
                        $title = (string) ($resolved['title'] ?? '');
                    }
                }
            }
        }

        if ('' === $repo || 0 === $number) {
            return $this->json(['ok' => true]);
        }

        $task = $tacheRepository->findOneBy([
            'githubIssueNumber' => $number,
            'githubRepo' => $repo,
        ]);

        if (!$task instanceof Tache) {
            $task = new Tache();
            $task->setGithubIssueNumber($number);
            $task->setGithubRepo($repo);
            if ($cal = $calendrierRepository->findPrimary()) {
                $task->setCalendrier($cal);
            } else {
                return $this->json(['ok' => false, 'error' => 'Calendrier principal absent'], 500);
            }
            $em->persist($task);
        }

        if ('' !== trim($title)) {
            $task->setNom($title);
        }
        $task->setStatutTache($appStatus);
        $em->flush();

        $notifier->dispatch('github.webhook', [
            'action' => (string) ($payload['action'] ?? 'projects_v2_item'),
            'taskId' => $task->getId(),
            'issue' => $number,
            'repo' => $repo,
            'status' => $appStatus,
        ]);

        return $this->json(['ok' => true]);
    }

    /** @param array<mixed> $issue */ private function mapIssueToStatus(array $issue): string
    {
        $state = (string) ($issue['state'] ?? 'open');
        $labels = array_map(
            static fn (array $l): string => strtolower((string) ($l['name'] ?? '')),
            is_array($issue['labels'] ?? null) ? $issue['labels'] : []
        );

        if ('closed' === $state) {
            return 'TERMINEE';
        }
        if (in_array('doing', $labels, true)) {
            return 'EN_COURS';
        }

        return 'A_FAIRE';
    }

    /**
     * @param array<mixed> $payload
     */
    private function extractProjectStatusName(array $payload): ?string
    {
        $candidates = [
            $payload['projects_v2_item']['field_value']['name'] ?? null,
            $payload['projects_v2_item']['changes']['field_value']['to']['name'] ?? null,
            $payload['changes']['field_value']['to']['name'] ?? null,
            $payload['field_value']['name'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }

        return null;
    }

    private function mapProjectStatusToApp(string $statusName): string
    {
        return match (strtolower(trim($statusName))) {
            'in progress' => 'EN_COURS',
            'done' => 'TERMINEE',
            default => 'A_FAIRE',
        };
    }

    private function isValidSignature(Request $request): bool
    {
        $secret = trim((string) ($this->webhookSecret ?? ''));
        if ('' === $secret) {
            return true;
        }

        $signature = (string) $request->headers->get('X-Hub-Signature-256', '');
        if (!str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $computed = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }
}
