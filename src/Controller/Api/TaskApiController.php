<?php

namespace App\Controller\Api;

use App\Entity\Tache;
use App\Repository\TacheRepository;
use App\Service\Domain\PlanningDomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks')]
final class TaskApiController extends AbstractController
{
    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    public function list(TacheRepository $tacheRepository): JsonResponse
    {
        $tasks = $tacheRepository->findBy([], ['id' => 'DESC'], 100); // Limit to 100 results
        $data = [];
        foreach ($tasks as $task) {
            $data[] = [
                'id' => $task->getId(),
                'title' => $task->getNom(),
                'priority' => $task->getPriorite() ?? 'moyenne',
                'dueDate' => $task->getDeadline() ? $task->getDeadline()->format('Y-m-d') : null,
                'notes' => $task->getNotes(),
                'completed' => $task->getStatutTache() === 'TERMINEE',
                'statut' => $task->getStatutTache(),
                'githubIssueNumber' => $task->getGithubIssueNumber(),
                'githubRepo' => $task->getGithubRepo(),
            ];
        }
        return $this->json($data);
    }

    #[Route('', name: 'api_tasks_create', methods: ['POST'])]
    public function create(Request $request, PlanningDomainService $domainService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $task = new Tache();
            $task->setNom($data['title'] ?? 'Nouvelle tâche');
            $task->setNotes($data['notes'] ?? null);
            $task->setPriorite($data['priority'] ?? 'moyenne');

            if (!empty($data['dueDate'])) {
                $task->setDeadline(new \DateTime($data['dueDate']));
            }

            $task->setStatutTache(!empty($data['completed']) ? 'TERMINEE' : ($data['statut'] ?? 'A_FAIRE'));

            $domainService->saveTache($task);

            return $this->json([
                'id' => $task->getId(),
                'title' => $task->getNom(),
                'priority' => $task->getPriorite() ?? 'moyenne',
                'dueDate' => $task->getDeadline() ? $task->getDeadline()->format('Y-m-d') : null,
                'completed' => $task->getStatutTache() === 'TERMINEE',
                'githubIssueNumber' => $task->getGithubIssueNumber(),
                'githubRepo' => $task->getGithubRepo(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'api_tasks_update', methods: ['PUT'])]
    public function update(Tache $task, Request $request, PlanningDomainService $domainService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['title'])) {
                $task->setNom($data['title']);
            }
            if (isset($data['notes'])) {
                $task->setNotes($data['notes']);
            }
            if (isset($data['priority'])) {
                $task->setPriorite($data['priority']);
            }
            if (isset($data['dueDate'])) {
                $task->setDeadline(new \DateTime($data['dueDate']));
            }
            if (isset($data['completed'])) {
                $task->setStatutTache($data['completed'] ? 'TERMINEE' : 'A_FAIRE');
            }
            if (isset($data['statut'])) {
                $task->setStatutTache($data['statut']);
            }

            $domainService->saveTache($task);

            return $this->json([
                'id' => $task->getId(),
                'title' => $task->getNom(),
                'priority' => $task->getPriorite() ?? 'moyenne',
                'dueDate' => $task->getDeadline() ? $task->getDeadline()->format('Y-m-d') : null,
                'completed' => $task->getStatutTache() === 'TERMINEE',
                'githubIssueNumber' => $task->getGithubIssueNumber(),
                'githubRepo' => $task->getGithubRepo(),
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}', name: 'api_tasks_delete', methods: ['DELETE'])]
    public function delete(Tache $task, PlanningDomainService $domainService): JsonResponse
    {
        try {
            $domainService->removeTache($task);
            return $this->json(null, 204);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
