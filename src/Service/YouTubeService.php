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
     * @param  string $query       Terme de recherche (ex : "Pompes exercice musculation")
     * @param  int    $maxResults  Nombre de résultats (1–10, défaut 3)
     *
     * @return array<int, array{videoId: string, title: string, channelTitle: string, thumbnail: string, url: string}>
     *
     * @throws \RuntimeException Si la clé API est absente ou si l'API retourne une erreur
     */
    public function searchVideos(string $query, int $maxResults = 3): array
    {
        if (empty($this->youtubeApiKey) || $this->youtubeApiKey === 'VOTRE_CLE_ICI') {
            throw new \RuntimeException(
                'Clé API YouTube non configurée. Ajoutez YOUTUBE_API_KEY dans votre fichier .env'
            );
        }

        $response = $this->httpClient->request('GET', self::SEARCH_URL, [
            'query' => [
                'part'              => 'snippet',
                'q'                 => $query . ' exercice sport',
                'type'              => 'video',
                'maxResults'        => min(max($maxResults, 1), 10),
                'key'               => $this->youtubeApiKey,
                'safeSearch'        => 'moderate',
                'relevanceLanguage' => 'fr',
            ],
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $body       = $response->toArray(false);
            $apiMessage = $body['error']['message'] ?? 'Erreur HTTP ' . $statusCode;
            throw new \RuntimeException('Erreur API YouTube : ' . $apiMessage);
        }

        $data  = $response->toArray();
        $items = $data['items'] ?? [];

        // Normalisation — la closure retourne un tableau au shape précis
        return array_values(array_map(
            static function (mixed $item): array {
                if (!is_array($item)) {
                    return ['videoId' => '', 'title' => '', 'channelTitle' => '', 'thumbnail' => '', 'url' => ''];
                }

                $videoId = (string) ($item['id']['videoId'] ?? '');
                /** @var array<string, mixed> $snippet */
                $snippet = is_array($item['snippet']) ? $item['snippet'] : [];

                /** @var array<string, mixed> $thumbnails */
                $thumbnails = is_array($snippet['thumbnails'] ?? null) ? $snippet['thumbnails'] : [];
                /** @var array<string, mixed> $high */
                $high    = is_array($thumbnails['high']    ?? null) ? $thumbnails['high']    : [];
                /** @var array<string, mixed> $default */
                $default = is_array($thumbnails['default'] ?? null) ? $thumbnails['default'] : [];

                $thumbnail = (string) ($high['url'] ?? $default['url'] ?? '');

                return [
                    'videoId'      => $videoId,
                    'title'        => (string) ($snippet['title']        ?? 'Sans titre'),
                    'channelTitle' => (string) ($snippet['channelTitle'] ?? ''),
                    'thumbnail'    => $thumbnail,
                    'url'          => $videoId !== '' ? 'https://www.youtube.com/watch?v=' . $videoId : '',
                ];
            },
            is_array($items) ? $items : []
        ));
    }
}