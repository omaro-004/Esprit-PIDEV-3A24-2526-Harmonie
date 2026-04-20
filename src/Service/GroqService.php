<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GroqService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
        private readonly string $groqChatUrl,
        private readonly string $groqSttUrl,
        private readonly string $groqChatModel,
        private readonly string $groqSttModel,
    ) {}

    public function chat(string $systemPrompt, string $userMessage, float $temperature = 0.7, int $maxTokens = 1024): string
    {
        $response = $this->httpClient->request('POST', $this->groqChatUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => $this->groqChatModel,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
            ],
            'timeout' => 30,
        ]);

        $data = $response->toArray();
        return trim($data['choices'][0]['message']['content'] ?? '');
    }

    public function transcribeAudio(string $audioPath, string $mimeType = 'audio/webm'): string
    {
        $multipart = $this->buildMultipart([
            ['name' => 'model',    'contents' => $this->groqSttModel],
            ['name' => 'language', 'contents' => 'fr'],
            ['name' => 'file',     'contents' => file_get_contents($audioPath), 'filename' => basename($audioPath), 'mime' => $mimeType],
        ]);

        $response = $this->httpClient->request('POST', $this->groqSttUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type'  => $multipart['Content-Type'],
            ],
            'body'    => $multipart['body'],
            'timeout' => 60,
        ]);

        $data = $response->toArray();
        return trim($data['text'] ?? '');
    }

    public function generateMeditation(string $theme): array
    {
        $seed      = rand(1000, 9999);
        $durations = [5, 10, 12, 15, 20, 25, 30, 45];
        $hint      = $durations[array_rand($durations)];

        $system = <<<PROMPT
Tu es un expert en méditation avec une bibliothèque de milliers de sessions différentes.
Chaque génération DOIT être unique et originale — jamais les mêmes noms, durées ou conseils qu'avant.
Réponds UNIQUEMENT avec ce format exact, sans texte supplémentaire :
AUTEUR: [prénom + nom complet fictif, varié et original, pas toujours français]
DUREE: [nombre entier, différent à chaque fois, entre 5 et 60]
QUERY: [termes de recherche YouTube en anglais pour trouver une musique relaxante adaptée au thème, ex: "relaxing sleep music calm piano", "deep focus meditation ambient", "stress relief nature sounds"]
CONSEIL1: [conseil pratique et spécifique au thème, au moins 15 mots, différent des autres générations]
CONSEIL2: [conseil pratique et spécifique au thème, au moins 15 mots, différent de CONSEIL1]
CONSEIL3: [conseil pratique et spécifique au thème, au moins 15 mots, différent des deux précédents]
PROMPT;

        $userMsg = "Génère une session de méditation ORIGINALE et UNIQUE sur le thème : \"$theme\". "
            . "Contexte de variation #{$seed}. Durée suggérée autour de {$hint} minutes. "
            . "Les conseils doivent être concrets, actionnables et propres à ce thème précis.";

        $raw = $this->chat($system, $userMsg, 1.0, 700);

        $result = [
            'auteur'      => '',
            'duree'       => $hint,
            'audioUrl'    => '',
            'searchQuery' => '',
            'conseils'    => [],
        ];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'AUTEUR:'))   $result['auteur']      = trim(substr($line, 7));
            if (str_starts_with($line, 'DUREE:'))    $result['duree']       = max(5, min(60, (int) trim(substr($line, 6))));
            if (str_starts_with($line, 'QUERY:'))    $result['searchQuery'] = trim(substr($line, 6));
            if (str_starts_with($line, 'CONSEIL1:')) $result['conseils'][]  = trim(substr($line, 9));
            if (str_starts_with($line, 'CONSEIL2:')) $result['conseils'][]  = trim(substr($line, 9));
            if (str_starts_with($line, 'CONSEIL3:')) $result['conseils'][]  = trim(substr($line, 9));
        }

        if ($result['searchQuery'] !== '') {
            $result['audioUrl'] = $this->searchYouTubeVideo($result['searchQuery']);
        }

        return $result;
    }

    public function searchYouTubeVideo(string $query): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://www.youtube.com/youtubei/v1/search?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8',
                [
                    'headers' => [
                        'Content-Type'    => 'application/json',
                        'Accept-Language' => 'en-US,en;q=0.9',
                        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    ],
                    'json' => [
                        'context' => [
                            'client' => [
                                'clientName'    => 'WEB',
                                'clientVersion' => '2.20240101.00.00',
                                'hl'            => 'en',
                                'gl'            => 'US',
                            ],
                        ],
                        'query' => $query,
                    ],
                    'timeout' => 12,
                ]
            );

            $data     = $response->toArray(false);
            $sections = $data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];

            foreach ($sections as $section) {
                foreach ($section['itemSectionRenderer']['contents'] ?? [] as $item) {
                    if (!empty($item['videoRenderer']['videoId'])) {
                        return 'https://www.youtube.com/watch?v=' . $item['videoRenderer']['videoId'];
                    }
                }
            }
        } catch (\Throwable) {
        }

        return '';
    }

    public function generateConseils(string $theme, string $excludeHint = ''): array
    {
        $seed   = rand(1000, 9999);
        $system = <<<PROMPT
Tu es un expert en méditation. Génère 3 conseils pratiques et uniques pour une session de méditation.
Chaque conseil DOIT être différent des précédents et spécifique au thème donné.
Réponds UNIQUEMENT avec ce format exact, sans texte supplémentaire :
CONSEIL1: [conseil pratique, au moins 15 mots]
CONSEIL2: [conseil pratique, au moins 15 mots, différent du premier]
CONSEIL3: [conseil pratique, au moins 15 mots, différent des deux précédents]
PROMPT;

        $userMsg  = "Thème de méditation : \"$theme\". Variation #{$seed}."
            . ($excludeHint ? " Évite de répéter : $excludeHint" : '');

        $raw      = $this->chat($system, $userMsg, 1.0, 400);
        $conseils = [];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'CONSEIL1:')) $conseils[] = trim(substr($line, 9));
            if (str_starts_with($line, 'CONSEIL2:')) $conseils[] = trim(substr($line, 9));
            if (str_starts_with($line, 'CONSEIL3:')) $conseils[] = trim(substr($line, 9));
        }

        return $conseils;
    }

    public function parseJournalFromSpeech(string $transcription, string $today): array
    {
        $system = <<<PROMPT
Tu es un assistant qui extrait des informations d'un message vocal pour un journal intime.
Aujourd'hui on est le $today.
Réponds UNIQUEMENT avec ce format, sans texte supplémentaire :
DATE: [YYYY-MM-DD, déduit du contexte ou utilise la date d'aujourd'hui]
HUMEUR: [une seule valeur parmi : TRES_BIEN, BIEN, NEUTRE, MAL, TRES_MAL]
CONTENU: [le contenu du journal, recopié fidèlement depuis la transcription]
PROMPT;

        $raw    = $this->chat($system, $transcription, 0.3, 512);
        $result = ['date' => $today, 'humeur' => 'NEUTRE', 'contenu' => $transcription];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'DATE:'))    $result['date']    = trim(substr($line, 5));
            if (str_starts_with($line, 'HUMEUR:'))  $result['humeur']  = trim(substr($line, 7));
            if (str_starts_with($line, 'CONTENU:')) $result['contenu'] = trim(substr($line, 8));
        }

        $validHumeurs = ['TRES_BIEN', 'BIEN', 'NEUTRE', 'MAL', 'TRES_MAL'];
        if (!in_array($result['humeur'], $validHumeurs)) {
            $result['humeur'] = 'NEUTRE';
        }

        return $result;
    }

    public function generateWellbeingReport(string $studentName, string $journalSummary): string
    {
        $system = <<<PROMPT
Tu es un conseiller en bien-être étudiant. Tu rédiges des rapports professionnels sur l'état émotionnel des étudiants.
Le rapport doit être en français, professionnel et empathique.
Tu ne dois PAS citer le contenu personnel des journaux — uniquement les scores et tendances.
Structure ton rapport avec ces sections :
1. Évaluation générale du bien-être
2. Tendances observées (amélioration, déclin, stabilité)
3. Recommandations pour l'administration
4. Suggestions d'accompagnement concrètes
PROMPT;

        return $this->chat(
            $system,
            "Génère un rapport de bien-être pour l'étudiant(e) : $studentName\n\nDonnées du journal (anonymisées) :\n$journalSummary",
            0.7,
            1024
        );
    }

    private function buildMultipart(array $fields): array
    {
        $boundary = '----HarmonieGroqBoundary' . uniqid();
        $body     = '';

        foreach ($fields as $field) {
            $body .= "--$boundary\r\n";
            if (isset($field['filename'])) {
                $body .= "Content-Disposition: form-data; name=\"{$field['name']}\"; filename=\"{$field['filename']}\"\r\n";
                $body .= "Content-Type: {$field['mime']}\r\n\r\n";
            } else {
                $body .= "Content-Disposition: form-data; name=\"{$field['name']}\"\r\n\r\n";
            }
            $body .= $field['contents'] . "\r\n";
        }
        $body .= "--$boundary--\r\n";

        return [
            'Content-Type' => "multipart/form-data; boundary=$boundary",
            'body'         => $body,
        ];
    }
}
