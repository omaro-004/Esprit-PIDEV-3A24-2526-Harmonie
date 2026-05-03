<?php

namespace App\Service\Github;

use App\Entity\Tache;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GithubIssueService
{
    private const SETTINGS_FILE = 'var/share/github_settings.json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly ?string $defaultToken = null,
        private readonly ?string $defaultRepo = null,
        private readonly ?string $defaultBranch = 'main',
    ) {
    }

    public function hasConfig(): bool
    {
        $cfg = $this->getConfig();

        return !empty($cfg['token']) && !empty($cfg['repo']);
    }

    /**
     * @return array<string, string>
     */
    public function getConfig(): array
    {
        $file = $this->projectDir.'/'.self::SETTINGS_FILE;
        if (is_file($file)) {
            $raw = file_get_contents($file);
            $json = json_decode((string) $raw, true);
            if (is_array($json)) {
                return [
                    'token' => (string) ($json['token'] ?? ''),
                    'repo' => (string) ($json['repo'] ?? ''),
                    'branch' => $this->normalizeBranch((string) ($json['branch'] ?? '')),
                ];
            }
        }

        return [
            'token' => (string) ($this->defaultToken ?? ''),
            'repo' => (string) ($this->defaultRepo ?? ''),
            'branch' => $this->normalizeBranch((string) ($this->defaultBranch ?? 'main')),
        ];
    }

    public function saveConfig(string $token, string $repo, ?string $branch = null): void
    {
        $dir = dirname($this->projectDir.'/'.self::SETTINGS_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->projectDir.'/'.self::SETTINGS_FILE,
            json_encode([
                'token' => trim($token),
                'repo' => trim($repo),
                'branch' => $this->normalizeBranch((string) ($branch ?? '')),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function validateToken(?string $token = null): bool
    {
        $cfg = $this->getConfig();
        $tokenToUse = trim((string) ($token ?? $cfg['token']));
        if ($tokenToUse === '') {
            return false;
        }

        try {
            $res = $this->httpClient->request('GET', 'https://api.github.com/user', [
                'headers' => $this->headers($tokenToUse),
                'timeout' => 8,
            ]);

            return 200 === $res->getStatusCode();
        } catch (\Throwable) {
            return false;
        }
    }

    public function syncTask(Tache $tache): void
    {
        if (!$this->hasConfig()) {
            return;
        }

        if (null === $tache->getGithubIssueNumber()) {
            $created = $this->createIssue($tache);
            $tache->setGithubIssueNumber((int) $created['number']);
            $tache->setGithubRepo($this->resolveRepo($tache));

            try {
                $this->syncProjectStatusForIssue($this->resolveRepo($tache), (int) $created['number'], (string) $tache->getStatutTache());
            } catch (\Throwable $e) {
                $this->logger->warning('GitHub Project status sync failed on create.', ['error' => $e->getMessage()]);
            }

            return;
        }

        $this->updateIssue($tache, false);
    }

    public function closeTaskIssueAsCancelled(Tache $tache): void
    {
        if (!$this->hasConfig() || null === $tache->getGithubIssueNumber()) {
            return;
        }

        $this->request(
            'PATCH',
            sprintf('/repos/%s/issues/%d', $this->resolveRepo($tache), $tache->getGithubIssueNumber()),
            [
                'state' => 'closed',
                'labels' => ['cancelled'],
            ]
        );
    }

    /**
     * @param Tache[] $tasks
     * @return array<string, mixed>
     */
    public function forceResyncDoingTasks(array $tasks): array
    {
        $result = ['checked' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (!$this->hasConfig()) {
            $result['errors'][] = 'GitHub non configuré.';

            return $result;
        }

        foreach ($tasks as $task) {
            if ('EN_COURS' !== (string) $task->getStatutTache()) {
                continue;
            }

            ++$result['checked'];
            if (null === $task->getGithubIssueNumber() || !$task->getGithubRepo()) {
                ++$result['skipped'];
                continue;
            }

            try {
                $snapshot = $this->fetchIssueSnapshot($task->getGithubRepo(), (int) $task->getGithubIssueNumber());
                if (null !== $snapshot && $this->isAlignedWithStatus($snapshot, 'EN_COURS')) {
                    ++$result['skipped'];
                    continue;
                }

                $this->updateIssue($task, false);
                ++$result['updated'];
            } catch (\Throwable $e) {
                $result['errors'][] = sprintf('Task #%d: %s', (int) $task->getId(), $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveIssueByNodeId(string $nodeId): ?array
    {
        if ('' === trim($nodeId) || !$this->hasConfig()) {
            return null;
        }

        $query = <<<'GQL'
query($id: ID!) {
  node(id: $id) {
    ... on Issue {
      number
      title
      repository {
        name
        owner { login }
      }
    }
  }
}
GQL;

        $res = $this->graphQlRequest($query, ['id' => $nodeId]);
        $issue = $res['data']['node'] ?? null;
        if (!is_array($issue)) {
            return null;
        }

        $owner = (string) ($issue['repository']['owner']['login'] ?? '');
        $name = (string) ($issue['repository']['name'] ?? '');
        $number = (int) ($issue['number'] ?? 0);
        if ('' === $owner || '' === $name || 0 === $number) {
            return null;
        }

        return [
            'repo' => $owner.'/'.$name,
            'number' => $number,
            'title' => (string) ($issue['title'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createIssue(Tache $tache): array
    {
        [$state, $labels] = $this->mapStatus($tache->getStatutTache());

        $issue = $this->request('POST', sprintf('/repos/%s/issues', $this->resolveRepo($tache)), [
            'title' => $tache->getNom(),
            'body' => $this->buildIssueBody($tache),
            'labels' => $labels,
        ]);

        if ('closed' === $state) {
            $this->request('PATCH', sprintf('/repos/%s/issues/%d', $this->resolveRepo($tache), (int) $issue['number']), [
                'state' => 'closed',
                'labels' => $labels,
            ]);
        }

        return $issue;
    }

    private function updateIssue(Tache $tache, bool $forceOpen = false): void
    {
        [$state, $labels] = $this->mapStatus($tache->getStatutTache());
        if ($forceOpen) {
            $state = 'open';
        }

        $this->request(
            'PATCH',
            sprintf('/repos/%s/issues/%d', $this->resolveRepo($tache), $tache->getGithubIssueNumber()),
            [
                'title' => $tache->getNom(),
                'body' => $this->buildIssueBody($tache),
                'state' => $state,
                'labels' => $labels,
            ]
        );

        $this->syncProjectStatusForIssue($this->resolveRepo($tache), (int) $tache->getGithubIssueNumber(), (string) $tache->getStatutTache());
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private function mapStatus(string $statut): array
    {
        return match ($statut) {
            'EN_COURS' => ['open', ['doing']],
            'TERMINEE' => ['closed', []],
            default => ['open', ['todo']],
        };
    }

    private function statusToProjectName(string $statut): string
    {
        return match ($statut) {
            'EN_COURS' => 'In Progress',
            'TERMINEE' => 'Done',
            default => 'Todo',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchIssueSnapshot(string $repoFullName, int $issueNumber): ?array
    {
        [$owner, $repo] = $this->splitRepo($repoFullName);

        $query = <<<'GQL'
query($owner: String!, $repo: String!, $number: Int!) {
    repository(owner: $owner, name: $repo) {
        issue(number: $number) {
            state
            labels(first: 50) {
                nodes { name }
            }
            projectItems(first: 20) {
                nodes {
                    fieldValueByName(name: "Status") {
                        ... on ProjectV2ItemFieldSingleSelectValue {
                            name
                        }
                    }
                }
            }
        }
    }
}
GQL;

        $res = $this->graphQlRequest($query, [
            'owner' => $owner,
            'repo' => $repo,
            'number' => $issueNumber,
        ]);

        $issue = $res['data']['repository']['issue'] ?? null;
        if (!is_array($issue)) {
            return null;
        }

        $labels = [];
        foreach (($issue['labels']['nodes'] ?? []) as $node) {
            if (is_array($node) && isset($node['name'])) {
                $labels[] = strtolower((string) $node['name']);
            }
        }

        $projectStatus = null;
        foreach (($issue['projectItems']['nodes'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $status = (string) ($item['fieldValueByName']['name'] ?? '');
            if ('' !== $status) {
                $projectStatus = $status;
                break;
            }
        }

        return [
            'state' => strtolower((string) ($issue['state'] ?? 'open')),
            'labels' => $labels,
            'projectStatus' => $projectStatus,
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function isAlignedWithStatus(array $snapshot, string $appStatus): bool
    {
        $state = strtolower((string) ($snapshot['state'] ?? 'open'));
        $labels = $snapshot['labels'] ?? [];
        $projectStatus = strtolower((string) ($snapshot['projectStatus'] ?? ''));

        return match ($appStatus) {
            'EN_COURS' => 'open' === $state
                && in_array('doing', $labels, true)
                && 'in progress' === $projectStatus,
            'TERMINEE' => 'closed' === $state
                && ('done' === $projectStatus || '' === $projectStatus),
            default => 'open' === $state
                && in_array('todo', $labels, true)
                && ('todo' === $projectStatus || '' === $projectStatus),
        };
    }

    private function syncProjectStatusForIssue(string $repoFullName, int $issueNumber, string $appStatus): void
    {
        if (0 === $issueNumber) {
            return;
        }

        [$owner, $repo] = $this->splitRepo($repoFullName);
        $targetStatus = $this->statusToProjectName($appStatus);

        $query = <<<'GQL'
query($owner: String!, $repo: String!, $number: Int!) {
    repository(owner: $owner, name: $repo) {
        issue(number: $number) {
            projectItems(first: 20) {
                nodes {
                    id
                    fieldValueByName(name: "Status") {
                        ... on ProjectV2ItemFieldSingleSelectValue {
                            name
                        }
                    }
                    project {
                        id
                        fields(first: 50) {
                            nodes {
                                ... on ProjectV2SingleSelectField {
                                    id
                                    name
                                    options {
                                        id
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
GQL;

        $res = $this->graphQlRequest($query, [
            'owner' => $owner,
            'repo' => $repo,
            'number' => $issueNumber,
        ]);

        $items = $res['data']['repository']['issue']['projectItems']['nodes'] ?? [];
        if (!is_array($items) || [] === $items) {
            return;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $currentStatus = strtolower((string) ($item['fieldValueByName']['name'] ?? ''));
            if ($currentStatus === strtolower($targetStatus)) {
                continue;
            }

            $projectId = (string) ($item['project']['id'] ?? '');
            $itemId = (string) ($item['id'] ?? '');
            if ('' === $projectId || '' === $itemId) {
                continue;
            }

            $statusFieldId = '';
            $targetOptionId = '';
            foreach (($item['project']['fields']['nodes'] ?? []) as $field) {
                if (!is_array($field)) {
                    continue;
                }
                if ('Status' !== (string) ($field['name'] ?? '')) {
                    continue;
                }
                $statusFieldId = (string) ($field['id'] ?? '');
                foreach (($field['options'] ?? []) as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    if (strtolower((string) ($option['name'] ?? '')) === strtolower($targetStatus)) {
                        $targetOptionId = (string) ($option['id'] ?? '');
                        break;
                    }
                }
                break;
            }

            if ('' === $statusFieldId || '' === $targetOptionId) {
                continue;
            }

            $mutation = <<<'GQL'
mutation($projectId: ID!, $itemId: ID!, $fieldId: ID!, $optionId: String!) {
    updateProjectV2ItemFieldValue(input: {
        projectId: $projectId,
        itemId: $itemId,
        fieldId: $fieldId,
        value: { singleSelectOptionId: $optionId }
    }) {
        projectV2Item { id }
    }
}
GQL;

            $this->graphQlRequest($mutation, [
                'projectId' => $projectId,
                'itemId' => $itemId,
                'fieldId' => $statusFieldId,
                'optionId' => $targetOptionId,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRepo(string $repoFullName): array
    {
        $parts = explode('/', trim($repoFullName), 2);
        if (2 !== count($parts) || '' === $parts[0] || '' === $parts[1]) {
            throw new \RuntimeException('Repo GitHub invalide: '.$repoFullName);
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function graphQlRequest(string $query, array $variables = []): array
    {
        $cfg = $this->getConfig();
        $token = (string) ($cfg['token'] ?? '');
        if ('' === $token) {
            throw new \RuntimeException('GitHub non configuré.');
        }

        $response = $this->httpClient->request('POST', 'https://api.github.com/graphql', [
            'headers' => $this->headers($token),
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
            'timeout' => 12,
        ]);

        $status = $response->getStatusCode();
        $data = $response->toArray(false);

        if ($status < 200 || $status >= 300) {
            $message = (string) ($data['message'] ?? 'Erreur GraphQL GitHub');
            throw new \RuntimeException($message);
        }

        if (!empty($data['errors'])) {
            $first = $data['errors'][0];
            $msg = is_array($first) ? (string) ($first['message'] ?? 'Erreur GraphQL GitHub') : 'Erreur GraphQL GitHub';
            throw new \RuntimeException($msg);
        }

        return $data;
    }

    private function resolveRepo(Tache $tache): string
    {
        if ($tache->getGithubRepo()) {
            return $tache->getGithubRepo();
        }

        $cfg = $this->getConfig();

        return (string) $cfg['repo'];
    }

    private function resolveBranch(): string
    {
        $cfg = $this->getConfig();

        return $this->normalizeBranch((string) ($cfg['branch'] ?? 'main'));
    }

    private function normalizeBranch(string $branch): string
    {
        $normalized = trim($branch);

        return '' === $normalized ? 'main' : $normalized;
    }

    private function buildIssueBody(Tache $tache): string
    {
        $notes = trim((string) ($tache->getNotes() ?? ''));
        $branch = $this->resolveBranch();

        if ('' === $notes) {
            return "Branche cible: `{$branch}`";
        }

        return $notes."\n\n---\nBranche cible: `{$branch}`";
    }

    /**
     * @param array<string, mixed> $json
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $json = []): array
    {
        $cfg = $this->getConfig();
        $token = (string) ($cfg['token'] ?? '');
        if ('' === $token) {
            throw new \RuntimeException('GitHub non configuré.');
        }

        $attempt = 0;
        $delayUs = 200_000;
        do {
            ++$attempt;
            try {
                $response = $this->httpClient->request($method, 'https://api.github.com'.$path, [
                    'headers' => $this->headers($token),
                    'json' => $json,
                    'timeout' => 10,
                ]);
                $code = $response->getStatusCode();
                if ($code >= 200 && $code < 300) {
                    $content = $response->getContent(false);
                    if ('' === $content) {
                        return [];
                    }

                    $decoded = json_decode($content, true);

                    return is_array($decoded) ? $decoded : [];
                }

                if (401 === $code || 403 === $code) {
                    throw new \RuntimeException('Token GitHub invalide ou non autorisé.');
                }
                throw new \RuntimeException('Erreur GitHub HTTP '.$code);
            } catch (ExceptionInterface|\RuntimeException $e) {
                if ($attempt >= 3) {
                    throw new \RuntimeException('Échec sync GitHub: '.$e->getMessage(), 0, $e);
                }
                usleep($delayUs);
                $delayUs *= 2;
            }
        } while (true);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'Authorization' => 'Bearer '.$token,
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'harmonie-kanban',
        ];
    }
}
