<?php

namespace App\Service;

use App\Repository\ExerciceRepository;

/**
 * Service de statistiques pour les exercices.
 * Utilisé par AdminSportController pour alimenter le graphique Chart.js.
 */
class ExerciceStatsService
{
    public function __construct(
        private readonly ExerciceRepository $exerciceRepository,
    ) {}

    /**
     * Retourne la répartition des exercices par type sous forme de tableau associatif.
     * Exemple : ['Cardio' => 12, 'Musculation' => 8, 'Yoga' => 5, ...]
     *
     * @return array<string, int>
     */
    public function getExercicesByType(): array
    {
        $exercices = $this->exerciceRepository->findAllOrdered();

        $counts = [];
        foreach ($exercices as $exercice) {
            $type = $exercice->getTypeExercice() ?? 'Non classifié';
            // On normalise : on prend uniquement la partie avant le "_" si présent
            // Ex: "Cardio_Homme" → "Cardio", "Cardio_Femme" → "Cardio"
            $typeNormalise = explode('_', $type)[0];
            $typeNormalise = trim($typeNormalise);

            if ($typeNormalise === '') {
                $typeNormalise = 'Non classifié';
            }

            $counts[$typeNormalise] = ($counts[$typeNormalise] ?? 0) + 1;
        }

        // Trier par nombre décroissant pour un graphique plus lisible
        arsort($counts);

        return $counts;
    }

    /**
     * Retourne le nombre total d'exercices.
     */
    public function getTotalExercices(): int
    {
        return count($this->exerciceRepository->findAllOrdered());
    }

    /**
     * Retourne la palette de couleurs pour le graphique (une couleur par type).
     *
     * @param int $count Nombre de couleurs nécessaires
     * @return array<string> Liste de couleurs RGBA
     */
    public function getPalette(int $count): array
    {
        $palette = [
            'rgba(16, 185, 129, 0.85)',   // vert  – Cardio
            'rgba(59, 130, 246, 0.85)',    // bleu  – Musculation
            'rgba(245, 158, 11, 0.85)',    // orange– Yoga
            'rgba(139, 92, 246, 0.85)',    // violet– Étirement
            'rgba(239, 68, 68, 0.85)',     // rouge – HIIT
            'rgba(20, 184, 166, 0.85)',    // teal  – Natation
            'rgba(249, 115, 22, 0.85)',    // orange vif
            'rgba(236, 72, 153, 0.85)',    // rose
            'rgba(99, 102, 241, 0.85)',    // indigo
            'rgba(234, 179, 8, 0.85)',     // jaune
        ];

        // Si plus de couleurs nécessaires, on génère des couleurs HSL
        while (count($palette) < $count) {
            $hue = (count($palette) * 37) % 360;
            $palette[] = "hsla({$hue}, 70%, 55%, 0.85)";
        }

        return array_slice($palette, 0, $count);
    }
}