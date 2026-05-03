<?php

namespace App\Controller;

use App\Service\BlessureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * BlessureController
 * ------------------
 * Gère le modèle corporel interactif 3D pour la récupération des blessures sportives.
 *
 * Routes :
 *  GET  /blessure               → page principale avec la silhouette interactive
 *  POST /blessure/conseil       → endpoint AJAX → renvoie les conseils IA en JSON
 */
#[Route('/blessure', name: 'blessure_')]
class BlessureController extends AbstractController
{
    public function __construct(
        private readonly BlessureService $blessureService,
    ) {}

    /**
     * Page principale : affiche le modèle corporel interactif.
     */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        // Liste des types d'exercices disponibles (pour le sélecteur contextuel)
        $typesActivite = [
            'musculation',
            'cardio',
            'yoga',
            'natation',
            'course à pied',
            'cyclisme',
            'football',
            'basketball',
            'tennis',
            'arts martiaux',
            'crossfit',
            'général',
        ];

        return $this->render('blessure/index.html.twig', [
            'typesActivite' => $typesActivite,
        ]);
    }

    /**
     * Endpoint AJAX : reçoit l'articulation cliquée + contexte, retourne les conseils IA.
     *
     * Corps JSON attendu :
     * {
     *   "articulation": "genou_gauche",
     *   "typeActivite": "musculation",
     *   "intensite": "modérée"
     * }
     */
    #[Route('/conseil', name: 'conseil', methods: ['POST'])]
    public function getConseil(Request $request): JsonResponse
    {
        // 1. Sécurité : token CSRF (optionnel mais recommandé)
        // Note : désactivé ici car AJAX sans formulaire HTML classique.
        // Vous pouvez ajouter un header X-CSRF-Token si nécessaire.

        // 2. Décodage du corps JSON
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'error'   => 'Corps de la requête invalide (JSON attendu).',
            ], 400);
        }

        // 3. Validation des paramètres
        $articulation  = trim($data['articulation']  ?? '');
        $typeActivite  = trim($data['typeActivite']  ?? 'général');
        $intensite     = trim($data['intensite']     ?? 'modérée');

        // Liste blanche des articulations autorisées (sécurité)
        $articulationsAutorisees = [
            'tete', 'cou',
            'epaule_gauche', 'epaule_droite',
            'coude_gauche', 'coude_droit',
            'poignet_gauche', 'poignet_droit',
            'dos_haut', 'dos_bas', 'thorax', 'abdomen',
            'hanche_gauche', 'hanche_droite',
            'genou_gauche', 'genou_droit',
            'cheville_gauche', 'cheville_droite',
            'pied_gauche', 'pied_droit',
        ];

        if ($articulation === '' || !in_array($articulation, $articulationsAutorisees, true)) {
            return $this->json([
                'success' => false,
                'error'   => 'Articulation non reconnue : ' . htmlspecialchars($articulation),
            ], 400);
        }

        // Liste blanche des intensités
        $intensitesAutorisees = ['légère', 'modérée', 'intense'];
        if (!in_array($intensite, $intensitesAutorisees, true)) {
            $intensite = 'modérée';
        }

        // 4. Récupération de l'ID utilisateur (si authentifié)
        $userId = 0;
        if ($this->getUser() !== null && method_exists($this->getUser(), 'getId')) {
            $userId = (int) $this->getUser()->getId();
        }

        // 5. Appel au service IA
        try {
            $conseil = $this->blessureService->getRecoveryAdvice(
                $articulation,
                $typeActivite,
                $intensite,
                $userId
            );

            return $this->json([
                'success' => true,
                'data'    => $conseil,
            ]);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'Erreur lors de la génération des conseils : ' . $e->getMessage(),
            ], 500);
        }
    }
}