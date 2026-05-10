<?php

namespace App\Controller;

use App\Service\YouTubeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller dédié à la recherche YouTube pour l'interface sport admin.
 *
 * Route unique : GET /admin/sport/api/youtube-search?q=Pompes
 * Retourne un tableau JSON de vidéos ou un objet d'erreur.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/sport')]
class AdminSportYouTubeController extends AbstractController
{
    public function __construct(
        private readonly YouTubeService $youTubeService,
    ) {}

    /**
     * Recherche les 3 meilleures vidéos YouTube pour un exercice donné.
     *
     * Paramètres GET :
     *   - q (string, obligatoire) : terme de recherche (ex : "Pompes")
     *
     * Réponse succès (200) :
     * [
     *   {
     *     "videoId":      "abc123",
     *     "title":        "Comment faire des pompes parfaites",
     *     "channelTitle": "MuscuPro",
     *     "thumbnail":    "https://i.ytimg.com/vi/abc123/hqdefault.jpg",
     *     "url":          "https://www.youtube.com/watch?v=abc123"
     *   },
     *   ...
     * ]
     *
     * Réponse erreur (400 / 500) :
     * { "error": "Message d'erreur explicite" }
     */
    #[Route('/api/youtube-search', name: 'admin_sport_youtube_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        // Fix PHPStan :50 — query->get() retourne bool|float|int|string|null, cast explicite en string avant trim
        $query = trim((string) $request->query->get('q', ''));

        // ── Validation de l'entrée ──────────────────────────────────────────
        if ($query === '') {
            return $this->json(['error' => 'Le paramètre "q" (terme de recherche) est obligatoire.'], 400);
        }

        if (mb_strlen($query) > 200) {
            return $this->json(['error' => 'Le terme de recherche est trop long (200 caractères max).'], 400);
        }

        // ── Appel au service YouTube ────────────────────────────────────────
        try {
            $videos = $this->youTubeService->searchVideos($query, 3);

            if (empty($videos)) {
                return $this->json([], 200); // tableau vide = aucun résultat
            }

            return $this->json($videos, 200);

        } catch (\RuntimeException $e) {
            // Erreurs gérées (clé manquante, quota dépassé, etc.)
            return $this->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            // Erreur inattendue — on ne remonte pas les détails en production
            return $this->json(['error' => 'Une erreur inattendue s\'est produite lors de la recherche YouTube.'], 500);
        }
    }
}