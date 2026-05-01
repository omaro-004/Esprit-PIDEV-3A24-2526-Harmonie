<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service YouTube Data API v3
 * ─────────────────────────────────────────────────────────────────────────────
 * Permet de rechercher des vidéos de démonstration pour les exercices sportifs.
 *
 * Quota gratuit YouTube : 10 000 unités/jour.
 * Une requête search.list coûte 100 unités → ~100 recherches/jour en gratuit.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class YouTubeService
{
    private const SEARCH_URL = 'https://www.googleapis.com/youtube/v3/search';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $youtubeApiKey,
    ) {}

    /**
     * Recherche les N meilleures vidéos YouTube correspondant à la requête.
     *
     * @param  string $query     Terme de recherche (ex : "Pompes exercice musculation")
     * @param  int    $maxResults Nombre de résultats (1–10, défaut 3)
     * @return array  Tableau de résultats normalisés
     *
     * Chaque résultat contient :
     *   - videoId     : identifiant YouTube (ex : "dQw4w9WgXcQ")
     *   - title       : titre de la vidéo
     *   - thumbnail   : URL de la miniature (hqdefault 480×360)
     *   - channelTitle: nom de la chaîne
     *   - url         : URL complète https://www.youtube.com/watch?v=…
     *
     * @throws \RuntimeException Si la clé API est absente ou si l'API retourne une erreur
     */
    public function searchVideos(string $query, int $maxResults = 3): array
    {
        // Vérification préventive de la clé
        if (empty($this->youtubeApiKey) || $this->youtubeApiKey === 'VOTRE_CLE_ICI') {
            throw new \RuntimeException(
                'Clé API YouTube non configurée. Ajoutez YOUTUBE_API_KEY dans votre fichier .env'
            );
        }

        // Appel API — on ajoute "exercice" pour affiner les résultats sportifs
        $response = $this->httpClient->request('GET', self::SEARCH_URL, [
            'query' => [
                'part'       => 'snippet',
                'q'          => $query . ' exercice sport',
                'type'       => 'video',
                'maxResults' => min(max($maxResults, 1), 10),
                'key'        => $this->youtubeApiKey,
                // Filtre sécurité : contenu approprié pour tous
                'safeSearch' => 'moderate',
                // Langue de préférence (résultats français en priorité)
                'relevanceLanguage' => 'fr',
            ],
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $body = $response->toArray(false); // false = ne pas lever d'exception
            $apiMessage = $body['error']['message'] ?? 'Erreur HTTP ' . $statusCode;
            throw new \RuntimeException('Erreur API YouTube : ' . $apiMessage);
        }

        $data  = $response->toArray();
        $items = $data['items'] ?? [];

        // Normalisation des résultats
        return array_map(static function (array $item): array {
            $videoId  = $item['id']['videoId'] ?? '';
            $snippet  = $item['snippet'] ?? [];

            return [
                'videoId'      => $videoId,
                'title'        => $snippet['title'] ?? 'Sans titre',
                'channelTitle' => $snippet['channelTitle'] ?? '',
                'thumbnail'    => $snippet['thumbnails']['high']['url']
                               ?? $snippet['thumbnails']['default']['url']
                               ?? '',
                'url'          => $videoId ? 'https://www.youtube.com/watch?v=' . $videoId : '',
            ];
        }, $items);
    }
}