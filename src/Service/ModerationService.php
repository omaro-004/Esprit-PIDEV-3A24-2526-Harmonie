<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ModerationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * Vérifie si le texte contient des gros mots
     * Retourne true si contenu inapproprié détecté
     */
    public function containsProfanity(string $text): bool
    {
        if (empty(trim($text))) {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET',
                'https://www.purgomalum.com/service/containsprofanity',
                [
                    'query'   => ['text' => $text],
                    'timeout' => 5, // 5 secondes max
                ]
            );

            return $response->getContent() === 'true';

        } catch (\Exception $e) {
            // Si l'API est indisponible, on laisse passer
            return false;
        }
    }

    /**
     * Retourne le texte censuré avec des ***
     */
    public function censorText(string $text): string
    {
        try {
            $response = $this->httpClient->request('GET',
                'https://www.purgomalum.com/service/plain',
                [
                    'query'   => ['text' => $text],
                    'timeout' => 5,
                ]
            );

            return $response->getContent();

        } catch (\Exception $e) {
            return $text;
        }
    }
}