<?php


namespace App\Service;


use Symfony\Contracts\HttpClient\HttpClientInterface;


class SummaryService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;


    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
    }


   public function summarizeDiscussion(string $titre, array $commentaires): string
    {
        $texte = implode("\n", array_map(fn($c) => $c->getContenu() ?? '', $commentaires));
       
        // Sécurité : texte minimum requis
        if (strlen(trim($texte)) < 5) {
            return 'Pas assez de contenu pour générer un résumé.';
        }


        $url = 'https://api.groq.com/openai/v1/chat/completions';


        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'json' => [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Résume en 3 phrases en français : ' . mb_substr($texte, 0, 2000)
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.7
            ]
        ]);


        $data = $response->toArray(false); // false = ne pas lever d'exception sur 4xx
       
        if (isset($data['error'])) {
            throw new \Exception('Groq error: ' . $data['error']['message']);
        }


        return $data['choices'][0]['message']['content'] ?? 'Résumé indisponible.';
    }
}

