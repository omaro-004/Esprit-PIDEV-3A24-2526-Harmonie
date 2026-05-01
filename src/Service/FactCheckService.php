<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class FactCheckService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {}

    public function checkPost(string $titre, string $contenu): array
    {
        $prompt = <<<PROMPT
Tu es un fact-checker expert. Analyse ce post de forum et détermine s'il contient des fake news.

Titre: {$titre}
Contenu: {$contenu}

Réponds UNIQUEMENT avec ce JSON strict:
{
  "verdict": "vrai" ou "fake" ou "incertain",
  "score_fiabilite": nombre entre 0 et 100,
  "explication": "2-3 phrases d'explication",
  "points_suspects": ["point 1", "point 2"],
  "sources_suggérées": ["source 1", "source 2"]
}
PROMPT;

        $response = $this->httpClient->request('POST',
            'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 500,
                'temperature' => 0.1
            ]
        ]);

        $data = $response->toArray(false);
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        $content = preg_replace('/```json|```/', '', $content);
        $result = json_decode(trim($content), true);

        return $result ?? [
            'verdict' => 'incertain',
            'score_fiabilite' => 50,
            'explication' => 'Analyse indisponible.',
            'points_suspects' => [],
            'sources_suggérées' => []
        ];
    }

    /** @param array<int, \App\Entity\Commentaire> $commentaires */
    public function chatAboutPost(
        string $titre,
        string $contenu,
        string $question,
        array $commentaires
    ): string {
        $contextComments = implode("\n", array_map(
            fn($c) => "- " . ($c->getContenu() ?? ''),
            $commentaires
        ));

        $prompt = <<<PROMPT
Tu es un assistant fact-checker intégré dans un forum bien-être.

Contexte du post:
Titre: {$titre}
Contenu: {$contenu}
Commentaires: {$contextComments}

Question de l'utilisateur: {$question}

Réponds de façon concise (max 3 phrases), cite des sources si possible, 
et indique clairement si l'info est fiable ou non.
PROMPT;

        $response = $this->httpClient->request('POST',
            'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 300,
                'temperature' => 0.3
            ]
        ]);

        $data = $response->toArray(false);
        return $data['choices'][0]['message']['content'] ?? 'Réponse indisponible.';
    }
}