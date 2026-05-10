<?php


namespace App\Service;


use Symfony\Contracts\HttpClient\HttpClientInterface;


class SentimentService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {}


    /** @return array{sentiment: string, emoji: string, score: int} */
    public function analyze(string $texte): array
    {
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
                    'content' => 'Analyse le sentiment de ce commentaire et réponds UNIQUEMENT avec un JSON strict sans markdown : {"sentiment": "positif" ou "negatif" ou "neutre", "emoji": "😊" ou "😠" ou "😐", "score": nombre entre 0 et 100}. Commentaire : ' . $texte
                ]],
                'max_tokens'  => 60,
                'temperature' => 0.1
            ]
        ]);


        $data = $response->toArray(false);


        if (isset($data['error'])) {
            return ['sentiment' => 'neutre', 'emoji' => '😐', 'score' => 50];
        }


        $content = $data['choices'][0]['message']['content'] ?? '{}';
       
        // Nettoyer le JSON si l'IA ajoute du markdown
        $content = preg_replace('/```json|```/', '', $content);
        $result  = json_decode(trim($content), true);


        return $result ?? ['sentiment' => 'neutre', 'emoji' => '😐', 'score' => 50];
    }
}

