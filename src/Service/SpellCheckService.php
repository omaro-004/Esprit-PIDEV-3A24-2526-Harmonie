<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpellCheckService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * Vérifie les fautes dans un texte via LanguageTool API
     * @param string $text     Le texte à vérifier
     * @param string $language La langue (fr, en, ar...)
     * @return array           Liste des erreurs avec suggestions
     */
    public function check(string $text, string $language = 'fr'): array
    {
        if (strlen(trim($text)) < 3) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST',
                'https://api.languagetool.org/v2/check',
                [
                    'body' => [
                        'text'     => $text,
                        'language' => $language,
                    ],
                    'timeout' => 10,
                ]
            );

            $data   = $response->toArray();
            $errors = [];

            // Transforme chaque match en tableau simple pour le frontend
            foreach ($data['matches'] as $match) {
                $suggestions = array_slice(
                    array_map(fn($s) => $s['value'], $match['replacements']),
                    0, 3  // Max 3 suggestions par erreur
                );

                $errors[] = [
                    'message'     => $match['message'],       // Description de l'erreur
                    'offset'      => $match['offset'],        // Position dans le texte
                    'length'      => $match['length'],        // Longueur du mot erroné
                    'word'        => substr($text, $match['offset'], $match['length']), // Le mot erroné
                    'suggestions' => $suggestions,            // Corrections proposées
                    'rule'        => $match['rule']['id'],    // Type d'erreur
                ];
            }

            return $errors;

        } catch (\Exception $e) {
            return []; // Si API indisponible, on ne bloque pas
        }
    }
}