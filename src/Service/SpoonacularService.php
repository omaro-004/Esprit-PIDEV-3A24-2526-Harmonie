<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service Spoonacular – intégration de l'API de recherche de recettes.
 */
class SpoonacularService
{
    private const BASE_URL = 'https://api.spoonacular.com';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $spoonacularApiKey
    ) {}

    // ──────────────────────────────────────────────────────────────────────────
    // RECHERCHE PAR INGRÉDIENTS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Retourne une liste de recettes qui utilisent au maximum les ingrédients fournis.
     *
     * @param string $ingredients  Liste séparée par des virgules : "apple,oats,milk"
     * @param int    $number       Nombre de résultats maximum (défaut : 8)
     *
     * @return array<mixed>
     *
     * @throws \RuntimeException si l'API retourne une erreur
     */
    public function findByIngredients(string $ingredients, int $number = 8): array
    {
        try {
            $response = $this->client->request('GET', self::BASE_URL . '/recipes/findByIngredients', [
                'query' => [
                    'ingredients'  => $ingredients,
                    'number'       => $number,
                    'ranking'      => 2,
                    'ignorePantry' => 'true',
                    'apiKey'       => $this->spoonacularApiKey,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 401) {
                throw new \RuntimeException('Clé API Spoonacular invalide. Vérifiez votre SPOONACULAR_API_KEY dans le fichier .env.');
            }
            if ($statusCode === 402) {
                throw new \RuntimeException('Quota Spoonacular dépassé. Veuillez patienter ou passer à un plan supérieur.');
            }
            if ($statusCode !== 200) {
                throw new \RuntimeException('Erreur Spoonacular (code ' . $statusCode . ').');
            }

            return $response->toArray();

        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Impossible de contacter l\'API Spoonacular : ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DÉTAILS D'UNE RECETTE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Retourne les informations complètes d'une recette, nutrition incluse.
     *
     * @param int $id  Identifiant Spoonacular de la recette
     *
     * @return array<mixed>
     *
     * @throws \RuntimeException si l'API retourne une erreur
     */
    public function getRecipeDetails(int $id): array
    {
        try {
            $response = $this->client->request('GET', self::BASE_URL . "/recipes/{$id}/information", [
                'query' => [
                    'includeNutrition' => 'true',
                    'apiKey'           => $this->spoonacularApiKey,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 401) {
                throw new \RuntimeException('Clé API Spoonacular invalide.');
            }
            if ($statusCode === 404) {
                throw new \RuntimeException('Recette introuvable (id=' . $id . ').');
            }
            if ($statusCode !== 200) {
                throw new \RuntimeException('Erreur Spoonacular (code ' . $statusCode . ').');
            }

            return $response->toArray();

        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Impossible de contacter l\'API Spoonacular : ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPER : extraction d'un nutriment
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Extrait la valeur d'un nutriment précis depuis le tableau nutrition de la recette.
     *
     * @param array<mixed> $recipeData  Données retournées par getRecipeDetails()
     * @param string       $name        Nom anglais du nutriment : "Calories", "Protein", "Fat"…
     *
     * @return float  Valeur arrondie à 1 décimale (0.0 si absent)
     */
    public function getNutrient(array $recipeData, string $name): float
    {
        $rawNutrients = $recipeData['nutrition']['nutrients'] ?? [];

        // Fix PHPStan — $rawNutrients est mixed, on s'assure que c'est un tableau
        if (!is_array($rawNutrients)) {
            return 0.0;
        }

        foreach ($rawNutrients as $nutrient) {
            // Chaque élément peut être mixed ; on ne travaille qu'avec des tableaux
            if (!is_array($nutrient)) {
                continue;
            }
            if (strcasecmp((string) ($nutrient['name'] ?? ''), $name) === 0) {
                return round((float) ($nutrient['amount'] ?? 0), 1);
            }
        }

        return 0.0;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPER : résumé nutritionnel formaté
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Construit un tableau résumé des macros principaux d'une recette.
     *
     * @param array<mixed> $recipeData  Données retournées par getRecipeDetails()
     * @return array{calories: float, proteines: float, glucides: float, lipides: float}
     */
    public function extractMacros(array $recipeData): array
    {
        return [
            'calories'  => $this->getNutrient($recipeData, 'Calories'),
            'proteines' => $this->getNutrient($recipeData, 'Protein'),
            'glucides'  => $this->getNutrient($recipeData, 'Carbohydrates'),
            'lipides'   => $this->getNutrient($recipeData, 'Fat'),
        ];
    }
}