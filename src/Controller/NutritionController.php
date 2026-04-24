<?php

namespace App\Controller;

use App\Entity\Aliment;
use App\Entity\Consommation;
use App\Repository\AlimentRepository;
use App\Repository\ConsommationRepository;
use App\Service\GeminiVisionService;
use App\Service\SpoonacularService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/nutrition')]
class NutritionController extends AbstractController
{
    private const DEMO_USER_ID  = 3;
    private const CAL_GOAL      = 2000;
    private const PROT_GOAL     = 150;
    private const GLUC_GOAL     = 250;
    private const LIP_GOAL      = 70;
    private const WATER_GOAL_ML = 2000;

    // ─── Journal principal ───────────────────────────────────────────
    #[Route('', name: 'nutrition', methods: ['GET'])]
    public function index(
        ConsommationRepository $consRepo,
        Request $request
    ): Response {
        $dateStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));

        try {
            $dt = new \DateTime($dateStr);
        } catch (\Exception $e) {
            $dt      = new \DateTime();
            $dateStr = $dt->format('Y-m-d');
        }

        $userId        = self::DEMO_USER_ID;
        $consommations = $consRepo->findByUserAndDate($userId, $dt);
        $grouped       = $this->groupByRepas($consommations);
        $totalCal      = $consRepo->sumCaloriesByUserAndDate($userId, $dt);
        $totalProt     = $consRepo->sumProtByUserAndDate($userId, $dt);
        $totalGluc     = $consRepo->sumGlucByUserAndDate($userId, $dt);
        $totalLip      = $consRepo->sumLipByUserAndDate($userId, $dt);

        $session = $request->getSession();

        $goalSettings = $session->get('nutrition_goals', [
            'cal'  => self::CAL_GOAL,
            'prot' => self::PROT_GOAL,
            'gluc' => self::GLUC_GOAL,
            'lip'  => self::LIP_GOAL,
        ]);

        $calGoal  = (int) ($goalSettings['cal']  ?? self::CAL_GOAL);
        $protGoal = (int) ($goalSettings['prot'] ?? self::PROT_GOAL);
        $glucGoal = (int) ($goalSettings['gluc'] ?? self::GLUC_GOAL);
        $lipGoal  = (int) ($goalSettings['lip']  ?? self::LIP_GOAL);

        $bmrProfil = $session->get('bmr_profil', null);

        $prevDate = (clone $dt)->modify('-1 day')->format('Y-m-d');
        $nextDate = (clone $dt)->modify('+1 day')->format('Y-m-d');
        $today    = (new \DateTime())->format('Y-m-d');

        return $this->render('nutrition/index.html.twig', [
            'date'       => $dateStr,
            'dateObj'    => $dt,
            'prevDate'   => $prevDate,
            'nextDate'   => $nextDate,
            'isToday'    => ($dateStr === $today),
            'grouped'    => $grouped,
            'totalCal'   => $totalCal,
            'totalProt'  => $totalProt,
            'totalGluc'  => $totalGluc,
            'totalLip'   => $totalLip,
            'calGoal'    => $calGoal,
            'goalProt'   => $protGoal,
            'goalGluc'   => $glucGoal,
            'goalLip'    => $lipGoal,
            'waterGoal'  => self::WATER_GOAL_ML,
            'userId'     => $userId,
            'repasTypes' => $this->repasTypes(),
            'bmrProfil'  => $bmrProfil,
        ]);
    }

    // ─── Page ajouter un aliment ─────────────────────────────────────
    #[Route('/ajouter', name: 'nutrition_ajouter', methods: ['GET'])]
    public function ajouter(
        Request $request,
        AlimentRepository $alimentRepo
    ): Response {
        $repas = $request->query->get('repas', 'Déjeuner');
        $date  = $request->query->get('date', (new \DateTime())->format('Y-m-d'));

        if (!in_array($repas, array_keys($this->repasTypes()))) {
            $repas = 'Déjeuner';
        }

        $aliments = $alimentRepo->findAllOrdered();

        return $this->render('nutrition/ajouter.html.twig', [
            'repas'      => $repas,
            'date'       => $date,
            'aliments'   => $aliments,
            'repasTypes' => $this->repasTypes(),
        ]);
    }

    // ── Page Recettes Spoonacular ─────────────────────────────────────
    #[Route('/recettes', name: 'nutrition_recettes', methods: ['GET'])]
    public function recettes(Request $request): Response
    {
        $date  = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $repas = $request->query->get('repas', 'Déjeuner');

        return $this->render('nutrition/recettes.html.twig', [
            'date'       => $date,
            'repas'      => $repas,
            'repasTypes' => $this->repasTypes(),
        ]);
    }

    // ─── API : objectifs nutritionnels ──────────────────────────────
    #[Route('/api/objectif', name: 'nutrition_api_objectif', methods: ['POST'])]
    public function apiObjectif(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $calGoal  = max(1, (int) ($data['calGoal']  ?? 0));
        $protGoal = max(1, (int) ($data['protGoal'] ?? 0));
        $glucGoal = max(1, (int) ($data['glucGoal'] ?? 0));
        $lipGoal  = max(1, (int) ($data['lipGoal']  ?? 0));

        if ($calGoal <= 0 || $protGoal <= 0 || $glucGoal <= 0 || $lipGoal <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Tous les objectifs doivent être valides.'], Response::HTTP_BAD_REQUEST);
        }

        $request->getSession()->set('nutrition_goals', [
            'cal'  => $calGoal,
            'prot' => $protGoal,
            'gluc' => $glucGoal,
            'lip'  => $lipGoal,
        ]);

        return new JsonResponse(['success' => true, 'message' => 'Objectifs enregistrés.']);
    }

    // ─── API : BMR save ─────────────────────────────────────────────
    #[Route('/api/bmr-profil', name: 'nutrition_api_bmr_save', methods: ['POST'])]
    public function apiBmrSave(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Données JSON invalides.'], 400);
        }

        $sexe     = strtolower(trim($data['sexe']     ?? ''));
        $age      = (int)   ($data['age']      ?? 0);
        $poids    = (float) ($data['poids']    ?? 0);
        $taille   = (int)   ($data['taille']   ?? 0);
        $activite = strtolower(trim($data['activite'] ?? ''));
        $objectif = strtolower(trim($data['objectif'] ?? ''));

        $errors = [];
        if (!in_array($sexe, ['homme', 'femme']))                                            { $errors[] = 'Sexe invalide.'; }
        if ($age < 10 || $age > 120)                                                         { $errors[] = 'Âge invalide.'; }
        if ($poids < 20.0 || $poids > 300.0)                                                { $errors[] = 'Poids invalide.'; }
        if ($taille < 100 || $taille > 250)                                                  { $errors[] = 'Taille invalide.'; }
        if (!in_array($activite, ['sedentaire','leger','modere','actif','tres_actif']))      { $errors[] = 'Activité invalide.'; }
        if (!in_array($objectif, ['perte','maintien','prise']))                              { $errors[] = 'Objectif invalide.'; }

        if ($errors) {
            return new JsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);
        }

        $bmr = (10 * $poids) + (6.25 * $taille) - (5 * $age) + ($sexe === 'homme' ? 5 : -161);
        $palMap = ['sedentaire'=>1.2,'leger'=>1.375,'modere'=>1.55,'actif'=>1.725,'tres_actif'=>1.9];
        $idee     = $bmr * $palMap[$activite];
        $calCible = match($objectif) { 'perte'=>$idee-500, 'prise'=>$idee+300, default=>$idee };
        $calCible = max($calCible, $sexe === 'homme' ? 1500 : 1200);

        $protCible = round(($calCible * 0.25) / 4);
        $glucCible = round(($calCible * 0.50) / 4);
        $lipCible  = round(($calCible * 0.25) / 9);

        $profil = compact('sexe','age','poids','taille','activite','objectif');
        $request->getSession()->set('bmr_profil', $profil);

        return new JsonResponse([
            'success'   => true,
            'bmr'       => round($bmr, 1),
            'idee'      => round($idee, 1),
            'calCible'  => round($calCible),
            'protCible' => (int)$protCible,
            'glucCible' => (int)$glucCible,
            'lipCible'  => (int)$lipCible,
            'profil'    => $profil,
        ]);
    }

    // ─── API : BMR get ──────────────────────────────────────────────
    #[Route('/api/bmr-profil', name: 'nutrition_api_bmr_get', methods: ['GET'])]
    public function apiBmrGet(Request $request): JsonResponse
    {
        $profil = $request->getSession()->get('bmr_profil');
        if (!$profil) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun profil BMR sauvegardé.'], 404);
        }
        return new JsonResponse(['success' => true, 'profil' => $profil]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  API : ANALYSE PHOTO DE REPAS — Google Gemini 1.5 Flash
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Analyse une photo de repas via Google Gemini 1.5 Flash Vision.
     *
     * Route : POST /nutrition/api/analyze-photo
     *
     * Corps JSON attendu :
     * {
     *   "image": "data:image/jpeg;base64,/9j/4AAQ...",
     *   "repas": "Déjeuner"
     * }
     *
     * Réponse :
     * {
     *   "success": true,
     *   "analysis": {
     *     "plats_detectes":       ["Pâtes bolognaise", "Salade"],
     *     "calories_totales":     650,
     *     "proteines_g":          35.0,
     *     "glucides_g":           72.0,
     *     "lipides_g":            18.0,
     *     "score_equilibre":      7,
     *     "suggestions":          ["Ajouter des légumes verts", ...],
     *     "note_nutritionnelle":  "Repas bien équilibré..."
     *   }
     * }
     */
    #[Route('/api/analyze-photo', name: 'nutrition_api_analyze_photo', methods: ['POST'])]
    public function apiAnalyzePhoto(
        Request $request,
        GeminiVisionService $gemini
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['image'])) {
            return new JsonResponse(['success' => false, 'message' => 'Image manquante.'], 400);
        }

        $imageRaw = $data['image'];
        $mimeType = 'image/jpeg';
        $base64   = $imageRaw;

        // Extraire mimeType + base64 pur depuis le data URI
        if (preg_match('/^data:(image\/[a-zA-Z0-9+\-]+);base64,(.+)$/s', $imageRaw, $matches)) {
            $mimeType = $matches[1];
            $base64   = $matches[2];
        }

        // Limite de taille (~3 MB réel)
        if (strlen($base64) > 5_000_000) {
            return new JsonResponse(['success' => false, 'message' => 'Image trop volumineuse. Max 3 Mo.'], 400);
        }

        // Formats acceptés
        if (!in_array(strtolower($mimeType), ['image/jpeg','image/jpg','image/png','image/webp','image/heic'])) {
            return new JsonResponse(['success' => false, 'message' => 'Format non supporté. Utilisez JPEG, PNG ou WebP.'], 400);
        }

        $repasType = $data['repas'] ?? 'Déjeuner';

        try {
            $analysis = $gemini->analyzeMealPhoto($base64, $mimeType, $repasType);
            return new JsonResponse(['success' => true, 'analysis' => $analysis]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── API : recherche recettes Spoonacular ────────────────────────
    #[Route('/api/recettes', name: 'nutrition_api_recettes', methods: ['GET'])]
    public function apiRecettes(
        Request $request,
        SpoonacularService $spoonacular
    ): JsonResponse {
        $ingredients = trim($request->query->get('ingredients', ''));
        $number      = min((int) $request->query->get('number', 8), 20);

        if ($ingredients === '') {
            return new JsonResponse(['success' => false, 'message' => 'Veuillez saisir au moins un ingrédient.'], 400);
        }

        try {
            $results  = $spoonacular->findByIngredients($ingredients, $number);
            $recettes = [];

            foreach ($results as $r) {
                $used   = array_map(fn($i) => $i['name'], $r['usedIngredients']   ?? []);
                $missed = array_map(fn($i) => $i['name'], $r['missedIngredients'] ?? []);

                $recettes[] = [
                    'id'                => $r['id'],
                    'titre'             => $r['title'],
                    'image'             => $r['image'] ?? null,
                    'usedCount'         => $r['usedIngredientCount']  ?? 0,
                    'missedCount'       => $r['missedIngredientCount'] ?? 0,
                    'usedIngredients'   => $used,
                    'missedIngredients' => $missed,
                    'likes'             => $r['likes'] ?? 0,
                ];
            }

            return new JsonResponse(['success' => true, 'recettes' => $recettes, 'total' => count($recettes)]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 503);
        }
    }

    // ─── API : détail recette ───────────────────────────────────────
    #[Route('/api/recette/{id}', name: 'nutrition_api_recette_detail', methods: ['GET'])]
    public function apiRecetteDetail(
        int $id,
        SpoonacularService $spoonacular
    ): JsonResponse {
        if ($id <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'ID invalide.'], 400);
        }

        try {
            $data   = $spoonacular->getRecipeDetails($id);
            $macros = $spoonacular->extractMacros($data);

            $ingredients = array_map(function ($ing) {
                return [
                    'nom'      => $ing['nameClean'] ?? $ing['name'] ?? '',
                    'quantite' => round($ing['amount'] ?? 0, 1),
                    'unite'    => $ing['unit'] ?? '',
                    'original' => $ing['original'] ?? '',
                ];
            }, $data['extendedIngredients'] ?? []);

            $resume = strip_tags($data['summary'] ?? '');
            if (strlen($resume) > 300) {
                $resume = substr($resume, 0, 300) . '…';
            }

            return new JsonResponse([
                'success' => true,
                'recette' => [
                    'id'           => $data['id'],
                    'titre'        => $data['title'],
                    'image'        => $data['image'] ?? null,
                    'temps'        => $data['readyInMinutes'] ?? null,
                    'portions'     => $data['servings']       ?? 1,
                    'calories'     => $macros['calories'],
                    'proteines'    => $macros['proteines'],
                    'glucides'     => $macros['glucides'],
                    'lipides'      => $macros['lipides'],
                    'ingredients'  => $ingredients,
                    'resume'       => $resume,
                    'sourceUrl'    => $data['sourceUrl'] ?? null,
                    'instructions' => $data['sourceUrl'] ?? null,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 503);
        }
    }

    // ─── API : ajouter une recette Spoonacular au journal ────────────
    #[Route('/api/ajouter-recette', name: 'nutrition_api_ajouter_recette', methods: ['POST'])]
    public function apiAjouterRecette(
        Request $request,
        AlimentRepository $alimentRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            $data     = json_decode($request->getContent(), true);
            $required = ['recipe_id','recipe_title','calories','meal_type','date'];

            foreach ($required as $field) {
                if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                    return new JsonResponse(['success' => false, 'message' => "Champ manquant: {$field}"], 400);
                }
            }

            $recipeTitle = trim($data['recipe_title']);
            $calories    = (float)$data['calories'];
            $mealType    = trim($data['meal_type']);

            try {
                $dateConsommation = new \DateTime($data['date']);
            } catch (\Exception) {
                return new JsonResponse(['success' => false, 'message' => 'Date invalide.'], 400);
            }

            if (!in_array($mealType, array_keys($this->repasTypes()))) {
                return new JsonResponse(['success' => false, 'message' => 'Type de repas invalide.'], 400);
            }

            $alimentName = "🍳 Recette: " . substr($recipeTitle, 0, 40);
            $aliment     = $alimentRepo->findByName($alimentName);

            if (!$aliment) {
                $aliment = new Aliment();
                $aliment->setNomAliment($alimentName);
                $aliment->setCaloriesPour100g(round(($calories * 100) / 300));
                $aliment->setProteines((float)($data['proteines'] ?? 20));
                $aliment->setGlucides((float) ($data['glucides']  ?? 50));
                $aliment->setLipides((float)  ($data['lipides']   ?? 15));
                $em->persist($aliment);
                $em->flush();
            }

            $consommation = new Consommation();
            $consommation->setAliment($aliment);
            $consommation->setTypeRepas($mealType);
            $consommation->setDateConsommation($dateConsommation);
            $consommation->setPoidsGrammes(300);
            $consommation->setUserId(self::DEMO_USER_ID);
            $em->persist($consommation);
            $em->flush();

            return new JsonResponse(['success' => true, 'message' => 'Recette ajoutée au journal.', 'consommation_id' => $consommation->getId()]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    // ─── API : données journal pour une date ─────────────────────────
    #[Route('/api/journal', name: 'nutrition_api_journal', methods: ['GET'])]
    public function apiJournal(
        Request $request,
        ConsommationRepository $consRepo
    ): JsonResponse {
        $dateStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));

        try {
            $dt = new \DateTime($dateStr);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Date invalide.'], 400);
        }

        $userId        = self::DEMO_USER_ID;
        $consommations = $consRepo->findByUserAndDate($userId, $dt);
        $grouped       = $this->groupByRepas($consommations);

        $result = [];
        foreach ($grouped as $repas => $items) {
            $result[$repas] = array_map([$this, 'consToArray'], $items);
        }

        return new JsonResponse([
            'success'   => true,
            'grouped'   => $result,
            'totalCal'  => $consRepo->sumCaloriesByUserAndDate($userId, $dt),
            'totalProt' => $consRepo->sumProtByUserAndDate($userId, $dt),
            'totalGluc' => $consRepo->sumGlucByUserAndDate($userId, $dt),
            'totalLip'  => $consRepo->sumLipByUserAndDate($userId, $dt),
        ]);
    }

    // ─── API : recherche aliments ────────────────────────────────────
    #[Route('/api/aliments', name: 'nutrition_api_aliments', methods: ['GET'])]
    public function apiAliments(
        Request $request,
        AlimentRepository $repo
    ): JsonResponse {
        $q        = trim($request->query->get('q', ''));
        $aliments = $q ? $repo->search($q) : $repo->findAllOrdered();

        return new JsonResponse(array_map(fn($a) => [
            'id'        => $a->getId(),
            'nom'       => $a->getNomAliment(),
            'cal_100g'  => $a->getCaloriesPour100g(),
            'proteines' => $a->getProteines(),
            'glucides'  => $a->getGlucides(),
            'lipides'   => $a->getLipides(),
        ], $aliments));
    }

    // ─── API : ajouter une consommation ─────────────────────────────
    #[Route('/api/ajouter', name: 'nutrition_api_ajouter', methods: ['POST'])]
    public function apiAjouter(
        Request $request,
        EntityManagerInterface $em,
        AlimentRepository $alimentRepo,
        ConsommationRepository $consRepo
    ): JsonResponse {
        $data   = json_decode($request->getContent(), true) ?? [];
        $errors = [];

        if (empty($data['aliment_id']))                                              { $errors['aliment'] = 'Veuillez sélectionner un aliment.'; }
        if (!isset($data['poids_grammes']) || (float)$data['poids_grammes'] <= 0)   { $errors['poids']   = 'La quantité doit être supérieure à 0 g.'; }
        if (empty($data['type_repas']))                                              { $errors['repas']   = 'Le type de repas est requis.'; }
        if (empty($data['date']))                                                     { $errors['date']    = 'La date est requise.'; }

        if ($errors) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 422);
        }

        $aliment = $alimentRepo->find((int)$data['aliment_id']);
        if (!$aliment) {
            return new JsonResponse(['success' => false, 'errors' => ['aliment' => 'Aliment introuvable.']], 404);
        }

        try {
            $dt = new \DateTime($data['date']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'errors' => ['date' => 'Date invalide.']], 400);
        }

        if (!in_array($data['type_repas'], array_keys($this->repasTypes()))) {
            return new JsonResponse(['success' => false, 'errors' => ['repas' => 'Type de repas invalide.']], 422);
        }

        $c = new Consommation();
        $c->setAliment($aliment);
        $c->setUserId(self::DEMO_USER_ID);
        $c->setDateConsommation($dt);
        $c->setTypeRepas($data['type_repas']);
        $c->setPoidsGrammes((int)round((float)$data['poids_grammes']));
        $c->setQuantiteEauMl(!empty($data['eau_ml']) ? (int)$data['eau_ml'] : null);

        $em->persist($c);
        $em->flush();

        return new JsonResponse([
            'success'      => true,
            'consommation' => $this->consToArray($c),
            'totalCal'     => $consRepo->sumCaloriesByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalProt'    => $consRepo->sumProtByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalGluc'    => $consRepo->sumGlucByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalLip'     => $consRepo->sumLipByUserAndDate(self::DEMO_USER_ID, $dt),
        ]);
    }

    // ─── API : modifier une consommation ────────────────────────────
    #[Route('/api/modifier/{id}', name: 'nutrition_api_modifier', methods: ['POST', 'PUT'])]
    public function apiModifier(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ConsommationRepository $consRepo
    ): JsonResponse {
        $c = $consRepo->find($id);
        if (!$c || $c->getUserId() !== self::DEMO_USER_ID) {
            return new JsonResponse(['success' => false, 'message' => 'Consommation introuvable.'], 404);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $errors = [];

        if (!isset($data['poids_grammes']) || (float)$data['poids_grammes'] <= 0) {
            $errors['poids'] = 'La quantité doit être supérieure à 0 g.';
        }

        if ($errors) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 422);
        }

        $c->setPoidsGrammes((int)round((float)$data['poids_grammes']));
        $em->flush();

        $dt = $c->getDateConsommation();
        return new JsonResponse([
            'success'      => true,
            'consommation' => $this->consToArray($c),
            'totalCal'     => $consRepo->sumCaloriesByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalProt'    => $consRepo->sumProtByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalGluc'    => $consRepo->sumGlucByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalLip'     => $consRepo->sumLipByUserAndDate(self::DEMO_USER_ID, $dt),
        ]);
    }

    // ─── API : supprimer une consommation ───────────────────────────
    #[Route('/api/supprimer/{id}', name: 'nutrition_api_supprimer', methods: ['POST', 'DELETE'])]
    public function apiSupprimer(
        int $id,
        EntityManagerInterface $em,
        ConsommationRepository $consRepo
    ): JsonResponse {
        $c = $consRepo->find($id);
        if (!$c || $c->getUserId() !== self::DEMO_USER_ID) {
            return new JsonResponse(['success' => false, 'message' => 'Consommation introuvable.'], 404);
        }

        $dt = clone $c->getDateConsommation();
        $em->remove($c);
        $em->flush();

        return new JsonResponse([
            'success'   => true,
            'totalCal'  => $consRepo->sumCaloriesByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalProt' => $consRepo->sumProtByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalGluc' => $consRepo->sumGlucByUserAndDate(self::DEMO_USER_ID, $dt),
            'totalLip'  => $consRepo->sumLipByUserAndDate(self::DEMO_USER_ID, $dt),
        ]);
    }

    // ─── Helpers privés ─────────────────────────────────────────────

    private function repasTypes(): array
    {
        return [
            'Petit-déjeuner' => ['icon' => '🌅', 'color' => '#F59E0B'],
            'Déjeuner'       => ['icon' => '☀️',  'color' => '#10B981'],
            'Dîner'          => ['icon' => '🌙', 'color' => '#6366F1'],
            'Snack'          => ['icon' => '🍎', 'color' => '#F43F5E'],
        ];
    }

    private function groupByRepas(array $consommations): array
    {
        $types = [
            'Petit-déjeuner' => [],
            'Déjeuner'       => [],
            'Dîner'          => [],
            'Snack'          => [],
        ];

        foreach ($consommations as $c) {
            $t = $c->getTypeRepas();
            if (!array_key_exists($t, $types)) {
                $types[$t] = [];
            }
            $types[$t][] = $c;
        }

        return $types;
    }

    private function consToArray(Consommation $c): array
    {
        return [
            'id'          => $c->getId(),
            'aliment_id'  => $c->getAliment()?->getId(),
            'aliment_nom' => $c->getAliment()?->getNomAliment(),
            'cal_100g'    => $c->getAliment()?->getCaloriesPour100g(),
            'proteines'   => round($c->getProteines(), 1),
            'glucides'    => round($c->getGlucides(), 1),
            'lipides'     => round($c->getLipides(), 1),
            'poids'       => $c->getPoidsGrammes(),
            'calories'    => round($c->getCalories(), 1),
            'type_repas'  => $c->getTypeRepas(),
            'date'        => $c->getDateConsommation()?->format('Y-m-d'),
            'eau_ml'      => $c->getQuantiteEauMl(),
        ];
    }
}