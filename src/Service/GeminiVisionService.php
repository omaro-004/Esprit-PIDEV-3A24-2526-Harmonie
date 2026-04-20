<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service Google Gemini Vision — Analyse de photos de repas par IA (Avril 2026)
 * Modèle utilisé : gemini-2.5-flash-lite → beaucoup plus stable et rapide
 */
class GeminiVisionService
{
    // MODÈLE LÉGER ET STABLE (recommandé quand le flash normal est surchargé)
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $geminiApiKey
    ) {}

    public function analyzeMealPhoto(
        string $base64Image,
        string $mimeType = 'image/jpeg',
        string $repasType = 'Déjeuner'
    ): array {
        if (empty($this->geminiApiKey) || !str_starts_with($this->geminiApiKey, 'AIza')) {
            throw new \RuntimeException('Clé API Gemini invalide ou manquante. Vérifie GEMINI_API_KEY dans .env');
        }

        if (empty($base64Image)) {
            throw new \RuntimeException('Image vide ou manquante.');
        }

        $prompt = <<<PROMPT
Tu es un expert en nutrition spécialisé dans l'alimentation des étudiants.
Analyse cette photo de repas ({$repasType}) et fournis une estimation nutritionnelle précise.

Réponds UNIQUEMENT avec un objet JSON valide (sans markdown, sans ```json) :

{
  "plats_detectes": ["nom du plat 1", "nom du plat 2"],
  "calories_totales": 450,
  "proteines_g": 25.5,
  "glucides_g": 40.0,
  "lipides_g": 15.0,
  "score_equilibre": 7,
  "suggestions": ["suggestion courte et actionnable 1", "suggestion courte et actionnable 2"],
  "note_nutritionnelle": "Commentaire bref et bienveillant pour un étudiant"
}

Règles strictes :
- score_equilibre entre 1 et 10
- suggestions : maximum 3, très courtes, pratiques et adaptées budget étudiant
- Si l'image n'est pas un repas → calories_totales: 0 et note claire
PROMPT;

        try {
            $response = $this->client->request('POST', self::BASE_URL, [
                'query'   => ['key' => $this->geminiApiKey],
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => [
                    'contents' => [[
                        'parts' => [
                            ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64Image]],
                            ['text' => $prompt]
                        ]
                    ]],
                    'generationConfig' => [
                        'temperature'     => 0.3,
                        'topP'            => 0.95,
                        'maxOutputTokens' => 1024,
                    ],
                ],
                'timeout' => 45,
            ]);

            $statusCode = $response->getStatusCode();
            $bodyRaw    = $response->getContent(false);

            if ($statusCode !== 200) {
                $err = json_decode($bodyRaw, true);
                $msg = $err['error']['message'] ?? 'Erreur inconnue';
                throw new \RuntimeException("Erreur Gemini (HTTP {$statusCode}) : {$msg}");
            }

            $data = json_decode($bodyRaw, true);
            $textContent = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return $this->parseGeminiResponse($textContent);

        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Impossible de contacter l’API Gemini (erreur réseau)');
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }

    private function parseGeminiResponse(string $text): array
    {
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```\s*$/m', '', $text);
        $text = trim($text);

        if (!str_starts_with($text, '{')) {
            $start = strpos($text, '{');
            $end   = strrpos($text, '}');
            if ($start !== false && $end !== false) {
                $text = substr($text, $start, $end - $start + 1);
            }
        }

        $result = json_decode($text, true);

        if (!is_array($result)) {
            throw new \RuntimeException('Gemini n’a pas renvoyé un JSON valide. Essaie avec une photo plus nette.');
        }

        return [
            'plats_detectes'      => (array)  ($result['plats_detectes']      ?? ['Plat non identifié']),
            'calories_totales'    => (int)    ($result['calories_totales']     ?? 0),
            'proteines_g'         => (float)  ($result['proteines_g']          ?? 0.0),
            'glucides_g'          => (float)  ($result['glucides_g']           ?? 0.0),
            'lipides_g'           => (float)  ($result['lipides_g']            ?? 0.0),
            'score_equilibre'     => max(1, min(10, (int) ($result['score_equilibre'] ?? 5))),
            'suggestions'         => array_slice((array) ($result['suggestions'] ?? []), 0, 3),
            'note_nutritionnelle' => (string) ($result['note_nutritionnelle']  ?? 'Analyse terminée.'),
        ];
    }
}