<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MistralService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {}

    public function suggestReply(string $postTitre, string $postContenu, array $commentaires): string
    {
        // Construit le contexte de la discussion
        $contexte = "Post intitulé : \"$postTitre\"\n";
        $contexte .= "Contenu : $postContenu\n\n";

        if (!empty($commentaires)) {
            $contexte .= "Commentaires existants :\n";
            foreach (array_slice($commentaires, 0, 5) as $c) {
                $contexte .= "- " . $c->getContenu() . "\n";
            }
        }

        $response = $this->httpClient->request('POST',
            'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [[
                    'role'    => 'user',
                    'content' => "Tu es un assistant forum bienveillant et expert. "
                        . "Génère une réponse pertinente, constructive et en français "
                        . "pour cette discussion. La réponse doit être naturelle, "
                        . "d'environ 2-3 phrases, comme si un vrai utilisateur répondait. "
                        . "Ne commence pas par 'Je' ni par des formules de politesse.\n\n"
                        . $contexte
                ]],
                'max_tokens'  => 200,
                'temperature' => 0.7
            ]
        ]);

        $data = $response->toArray(false);

        if (isset($data['error'])) {
            throw new \Exception('Mistral error: ' . $data['error']['message']);
        }

        return $data['choices'][0]['message']['content'] ?? 'Suggestion indisponible.';
    }
}