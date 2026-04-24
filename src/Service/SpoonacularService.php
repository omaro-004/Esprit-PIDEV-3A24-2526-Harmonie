<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service Spoonacular – intégration de l'API de recherche de recettes.
 *
 * Points d'entrée utilisés :
 *   • GET /recipes/findByIngredients  → recettes à partir d'une liste d'ingrédients
 *   • GET /recipes/{id}/information   → détails complets d'une recette (nutrition incluse)
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
     * @return array  Tableau de recettes tel que retourné par Spoonacular
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
                    'ranking'      => 2,        // Minimise les ingrédients manquants
                    'ignorePantry' => 'true',   // Ignore sel/eau/huile de base
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
     * @return array  Données brutes de la recette avec nutrition
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
     * @param array  $recipeData  Données retournées par getRecipeDetails()
     * @param string $name        Nom anglais du nutriment : "Calories", "Protein", "Fat"…
     *
     * @return float  Valeur arrondie à 1 décimale (0.0 si absent)
     */
    public function getNutrient(array $recipeData, string $name): float
    {
        $nutrients = $recipeData['nutrition']['nutrients'] ?? [];

        foreach ($nutrients as $nutrient) {
            if (strcasecmp($nutrient['name'] ?? '', $name) === 0) {
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
     * @param array $recipeData  Données retournées par getRecipeDetails()
     * @return array{calories:float, proteines:float, glucides:float, lipides:float}
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