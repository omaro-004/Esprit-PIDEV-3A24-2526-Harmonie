<?php

namespace App\Service\LibraryServices;

/**
 * Fetches suggestions from:
 *  - Open Library Subject API + Search API (free, no key)
 *  - YouTube Data API v3 (requires key)
 */
class SuggestionsService
{
    private const YOUTUBE_API_KEY = 'AIzaSyBx0_999HltFSdko33yurEBI18w61LpPAE';
    private const TIMEOUT         = 6;

    // ── Open Library ──────────────────────────────────────────────────────────

    /**
     * Smart two-strategy book search:
     * 1. Open Library Subject API  — books officially tagged with this subject
     * 2. Fallback: search for "<subject> textbook" to fill remaining slots
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchBooks(string $subject, int $limit = 10): array
    {
        $results = [];
        $subject = trim($subject);
        if ($subject === '') return $results;

        // ── Strategy 1: Subject API ───────────────────────────────────────────
        try {
            $slug = strtolower($subject);
            $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
            $slug = trim($slug, '_');

            $json = $this->get("https://openlibrary.org/subjects/{$slug}.json?limit={$limit}");

            if ($json && str_contains($json, '"works"')) {
                $data = json_decode($json, true);
                foreach ($data['works'] ?? [] as $work) {
                    if (count($results) >= $limit) break;
                    $title = $work['title'] ?? null;
                    if (!$title) continue;

                    $author  = $work['authors'][0]['name'] ?? null;
                    $coverId = $work['cover_id'] ?? null;
                    $key     = $work['key'] ?? null;

                    $results[] = [
                        'title'      => $title,
                        'author'     => $author,
                        'coverUrl'   => $coverId ? "https://covers.openlibrary.org/b/id/{$coverId}-L.jpg" : null,
                        'openLibUrl' => $key ? "https://openlibrary.org{$key}" : 'https://openlibrary.org',
                    ];
                }
            }
        } catch (\Throwable) {}

        // ── Strategy 2: Textbook search to fill remaining ─────────────────────
        if (count($results) < $limit) {
            try {
                $needed   = $limit - count($results);
                $query    = urlencode($subject . ' textbook');
                $json     = $this->get(
                    "https://openlibrary.org/search.json?q={$query}&limit=" . ($needed + 5) . "&fields=key,title,author_name,cover_i"
                );
                $data     = json_decode((string) $json, true);
                $existing = array_column($results, 'title');

                foreach ($data['docs'] ?? [] as $doc) {
                    if (count($results) >= $limit) break;
                    $title = $doc['title'] ?? null;
                    if (!$title || in_array($title, $existing, true)) continue;

                    $coverId = $doc['cover_i'] ?? null;
                    $key     = $doc['key'] ?? null;

                    $results[] = [
                        'title'      => $title,
                        'author'     => $doc['author_name'][0] ?? null,
                        'coverUrl'   => $coverId ? "https://covers.openlibrary.org/b/id/{$coverId}-L.jpg" : null,
                        'openLibUrl' => $key
                            ? "https://openlibrary.org{$key}"
                            : "https://openlibrary.org/search?q={$query}",
                    ];
                }
            } catch (\Throwable) {}
        }

        return $results;
    }

    // ── YouTube ───────────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchVideos(string $query, int $limit = 10): array
    {
        $results = [];
        if (trim($query) === '') return $results;

        try {
            $encoded = urlencode(trim($query) . ' tutorial');
            $url     = "https://www.googleapis.com/youtube/v3/search"
                     . "?part=snippet&type=video&maxResults={$limit}&q={$encoded}&key=" . self::YOUTUBE_API_KEY;

            $json = $this->get($url);
            $data = json_decode((string) $json, true);

            foreach ($data['items'] ?? [] as $item) {
                if (count($results) >= $limit) break;
                $videoId = $item['id']['videoId'] ?? null;
                $snippet = $item['snippet'] ?? [];
                if (!$videoId) continue;

                $thumb = $snippet['thumbnails']['maxres']['url']
                      ?? $snippet['thumbnails']['high']['url']
                      ?? $snippet['thumbnails']['medium']['url']
                      ?? null;

                $results[] = [
                    'title'        => html_entity_decode($snippet['title'] ?? '', ENT_QUOTES | ENT_HTML5),
                    'channelName'  => $snippet['channelTitle'] ?? null,
                    'thumbnailUrl' => $thumb,
                    'videoUrl'     => "https://www.youtube.com/watch?v={$videoId}",
                ];
            }
        } catch (\Throwable) {}

        return $results;
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    private function get(string $url): ?string
    {
        $ctx = stream_context_create(['http' => [
            'method'     => 'GET',
            'timeout'    => self::TIMEOUT,
            'user_agent' => 'HarmonyApp/1.0',
            'header'     => "Accept: application/json\r\n",
        ]]);
        $result = @file_get_contents($url, false, $ctx);
        return $result !== false ? $result : null;
    }
}
