<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * Traduit un texte d'une langue vers une autre
     * @param string $text    Le texte à traduire
     * @param string $from    Langue source (ex: 'fr')
     * @param string $to      Langue cible  (ex: 'en')
     * @return string         Le texte traduit
     */
    public function translate(string $text, string $from = 'fr', string $to = 'en'): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        try {
            $response = $this->httpClient->request('GET',
                'https://api.mymemory.translated.net/get',
                [
                    'query' => [
                        'q'        => $text,
                        'langpair' => $from . '|' . $to,
                    ],
                    'timeout' => 10,
                ]
            );

            $data = $response->toArray();

            // MyMemory retourne responseStatus 200 si succès
            if ($data['responseStatus'] === 200) {
                return $data['responseData']['translatedText'];
            }

            return $text; // retourne l'original si erreur

        } catch (\Exception $e) {
            return $text; // retourne l'original si API indisponible
        }
    }
}