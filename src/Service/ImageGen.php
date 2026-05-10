<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ImageGen
{
    // Pollinations.AI — gratuit, sans clé, sans inscription
    private const API_URL = 'https://image.pollinations.ai/prompt/';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * Génère une image via Pollinations.AI
     * Retourne l'image encodée en base64
     */
    public function generateImageBytes(string $prompt, string $style = ''): ?string
    {
        if (empty(trim($prompt))) {
            return null;
        }

        // Combine prompt + style
        $fullPrompt = $style ? $prompt . ', ' . $style : $prompt;

        // Encode le prompt pour l'URL
        $encodedPrompt = urlencode($fullPrompt);

        // Paramètres optionnels pour améliorer la qualité
        $url = self::API_URL . $encodedPrompt . '?' . http_build_query([
                'width'  => 768,
                'height' => 512,
                'seed'   => rand(1, 999999), // image différente à chaque fois
                'model'  => 'flux',          // meilleur modèle disponible
                'nologo' => 'true',          // sans watermark
            ]);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 60,
                'headers' => [
                    'User-Agent' => 'HarmonyApp/1.0',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                return null;
            }

            $imageBytes = $response->getContent();

            // Vérifie que c'est une vraie image
            if (strlen($imageBytes) < 1000) {
                return null;
            }

            return base64_encode($imageBytes);

        } catch (\Exception $e) {
            return null;
        }
    }
}
