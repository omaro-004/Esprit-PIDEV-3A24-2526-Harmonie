<?php

namespace App\Controller\Api;

use App\Repository\TacheRepository;
use App\Service\Github\GithubIssueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/github/settings')]
final class GithubSettingsController extends AbstractController
{
    #[Route('', name: 'api_github_settings_get', methods: ['GET'])]
    public function getSettings(GithubIssueService $github): JsonResponse
    {
        $cfg = $github->getConfig();

        return $this->json([
            'configured' => $github->hasConfig(),
            'repo' => $cfg['repo'] ?? '',
            'branch' => $cfg['branch'] ?? 'main',
            'tokenValid' => $github->validateToken(),
        ]);
    }

    #[Route('', name: 'api_github_settings_put', methods: ['PUT'])]
    public function saveSettings(Request $request, GithubIssueService $github): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['ok' => false, 'error' => 'JSON invalide'], 400);
        }

        $token = trim((string) ($data['token'] ?? ''));
        $repo = trim((string) ($data['repo'] ?? ''));
        $branch = trim((string) ($data['branch'] ?? ''));
        if ('' === $token || '' === $repo || !str_contains($repo, '/')) {
            return $this->json(['ok' => false, 'error' => 'Token/repo invalides'], 422);
        }

        $github->saveConfig($token, $repo, $branch);

        return $this->json([
            'ok' => true,
            'repo' => $repo,
            'branch' => '' === $branch ? 'main' : $branch,
            'tokenValid' => $github->validateToken($token),
        ]);
    }

    #[Route('/sync-doing', name: 'api_github_settings_sync_doing', methods: ['POST'])]
    public function syncDoing(GithubIssueService $github, TacheRepository $tacheRepository): JsonResponse
    {
        $doingTasks = $tacheRepository->findBy(['statutTache' => 'EN_COURS']);
        $summary = $github->forceResyncDoingTasks($doingTasks);

        return $this->json([
            'ok' => true,
            'checked' => $summary['checked'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
            'errors' => $summary['errors'],
        ]);
    }
}
