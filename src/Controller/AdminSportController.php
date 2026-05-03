<?php

namespace App\Controller;

use App\Entity\Exercice;
use App\Repository\ExerciceRepository;
use App\Service\ExerciceStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/sport')]
class AdminSportController extends AbstractController
{
    public function __construct(
        private readonly ExerciceRepository     $repo,
        private readonly EntityManagerInterface $em,
        private readonly ExerciceStatsService   $statsService,
    ) {}

    // ── Page principale ──────────────────────────────────────────────
    #[Route('', name: 'admin_sport_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/sport.html.twig');
    }

    // ── LIST (JSON) — supporte recherche, filtre type, section, tri côté serveur ──
    #[Route('/api/list', name: 'admin_sport_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        // Fix PHPStan :37-39 — query->get() retourne string|null, trim/strtolower attendent string
        $search  = trim((string) $request->query->get('search',  ''));
        $type    = trim((string) $request->query->get('type',    ''));
        $section = strtolower(trim((string) $request->query->get('section', '')));
        $sort    = (string) $request->query->get('sort', 'nom_asc');

        // Valeurs autorisées pour le tri
        $allowedSorts = ['nom_asc', 'nom_desc', 'type_asc', 'type_desc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'nom_asc';
        }

        // Valeurs autorisées pour la section
        if (!in_array($section, ['homme', 'femme', ''], true)) {
            $section = '';
        }

        $exercices = $this->repo->searchAndFilter($search, $type, $section, $sort);

        return $this->json(array_map([$this, 'serialize'], $exercices));
    }

    // ── TYPES (JSON) — liste des types distincts pour le datalist et le filtre ──
    #[Route('/api/types', name: 'admin_sport_types', methods: ['GET'])]
    public function types(): JsonResponse
    {
        return $this->json($this->repo->findDistinctTypes());
    }

    // ── STATS (JSON) — pour le graphique Chart.js ────────────────────
    #[Route('/api/stats', name: 'admin_sport_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $byType  = $this->statsService->getExercicesByType();
        $labels  = array_keys($byType);
        $values  = array_values($byType);
        $colors  = $this->statsService->getPalette(count($labels));

        return $this->json([
            'labels'           => $labels,
            'values'           => $values,
            'backgroundColors' => $colors,
            'total'            => array_sum($values),
        ]);
    }

    // ── CREATE ───────────────────────────────────────────────────────
    #[Route('/api/create', name: 'admin_sport_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $error = $this->validateData($data);
        if ($error) {
            return $this->json(['error' => $error], 400);
        }

        $exercice = new Exercice();
        $this->hydrate($exercice, $data);
        $this->em->persist($exercice);
        $this->em->flush();

        return $this->json($this->serialize($exercice), 201);
    }

    // ── UPDATE ───────────────────────────────────────────────────────
    #[Route('/api/update/{id}', name: 'admin_sport_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        // Fix PHPStan :116 — repo->find() retourne object|null, on vérifie instanceof Exercice
        $exercice = $this->repo->find($id);
        if (!$exercice instanceof Exercice) {
            return $this->json(['error' => 'Exercice introuvable'], 404);
        }

        $data  = json_decode($request->getContent(), true);
        $error = $this->validateData($data);
        if ($error) {
            return $this->json(['error' => $error], 400);
        }

        $this->hydrate($exercice, $data);
        $this->em->flush();

        return $this->json($this->serialize($exercice));
    }

    // ── DELETE ───────────────────────────────────────────────────────
    #[Route('/api/delete/{id}', name: 'admin_sport_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $exercice = $this->repo->find($id);
        if (!$exercice instanceof Exercice) {
            return $this->json(['error' => 'Exercice introuvable'], 404);
        }
        $this->em->remove($exercice);
        $this->em->flush();
        return $this->json(['deleted' => true, 'id' => $id]);
    }

    // ── HELPERS ─────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(Exercice $exercice, array $data): void
    {
        $exercice->setNomExercice(trim((string) ($data['nomExercice'] ?? '')));
        $exercice->setTypeExercice(!empty($data['typeExercice']) ? trim((string) $data['typeExercice']) : null);
        $exercice->setVideoExercice(!empty($data['videoExercice']) ? trim((string) $data['videoExercice']) : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Exercice $e): array
    {
        return [
            'id'            => $e->getId(),
            'nomExercice'   => $e->getNomExercice(),
            'typeExercice'  => $e->getTypeExercice(),
            'videoExercice' => $e->getVideoExercice(),
        ];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function validateData(?array $data): ?string
    {
        if (!$data) return 'Aucune donnée reçue.';
        if (empty(trim((string) ($data['nomExercice'] ?? '')))) return "Le nom de l'exercice est obligatoire.";
        if (empty(trim((string) ($data['typeExercice'] ?? '')))) return "Le type d'exercice est obligatoire.";
        return null;
    }
}