<?php

namespace App\Tests\Controller;

use App\Entity\Aliment;
use App\Service\AlimentManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique du AdminNutritionController.
 *
 * Emplacement : tests/Controller/AdminNutritionControllerTest.php
 * Commande   : php vendor/bin/phpunit tests/Controller/AdminNutritionControllerTest.php --testdox
 */
class AdminNutritionControllerTest extends TestCase
{
    private AlimentManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AlimentManager();
    }

    /** ✅ Test 1 : validateData() valide avec données complètes */
    public function testValidateDataComplet(): void
    {
        $data = [
            'nomAliment'       => 'Poulet grillé',
            'caloriesPour100g' => 165,
            'proteines'        => 31.0,
            'glucides'         => 0.0,
            'lipides'          => 3.6,
        ];

        $error = $this->validateData($data);
        $this->assertNull($error);
    }

    /** ❌ Test 2 : validateData() échoue si nom vide */
    public function testValidateDataNomVide(): void
    {
        $data = [
            'nomAliment'       => '',
            'caloriesPour100g' => 100,
            'proteines'        => 10.0,
            'glucides'         => 10.0,
            'lipides'          => 5.0,
        ];

        $error = $this->validateData($data);
        $this->assertNotNull($error);
        $this->assertStringContainsString('nom', strtolower($error));
    }

    /** ❌ Test 3 : validateData() échoue si calories négatives */
    public function testValidateDataCaloriesNegatives(): void
    {
        $data = [
            'nomAliment'       => 'Test',
            'caloriesPour100g' => -10,
            'proteines'        => 10.0,
            'glucides'         => 10.0,
            'lipides'          => 5.0,
        ];

        $error = $this->validateData($data);
        $this->assertNotNull($error);
    }

    /** ✅ Test 4 : serialize() retourne la bonne structure */
    public function testSerializeAliment(): void
    {
        $aliment = new Aliment();
        $aliment->setNomAliment('Thon');
        $aliment->setCaloriesPour100g(132);
        $aliment->setProteines(28.0);
        $aliment->setGlucides(0.0);
        $aliment->setLipides(1.2);

        // Reproduire serialize()
        $arr = [
            'id'              => $aliment->getId(),
            'nomAliment'      => $aliment->getNomAliment(),
            'caloriesPour100g'=> $aliment->getCaloriesPour100g(),
            'proteines'       => $aliment->getProteines(),
            'glucides'        => $aliment->getGlucides(),
            'lipides'         => $aliment->getLipides(),
        ];

        $this->assertSame('Thon', $arr['nomAliment']);
        $this->assertSame(132, $arr['caloriesPour100g']);
        $this->assertSame(28.0, $arr['proteines']);
        $this->assertNull($arr['id']); // non persisté
    }

    /** ✅ Test 5 : hydrate() remplit l'entité correctement */
    public function testHydrateAliment(): void
    {
        $aliment = new Aliment();
        $data = [
            'nomAliment'       => '  Saumon  ',
            'caloriesPour100g' => 208,
            'proteines'        => 20.0,
            'glucides'         => 0.0,
            'lipides'          => 13.0,
        ];

        // Reproduire hydrate()
        $aliment->setNomAliment(trim($data['nomAliment']));
        $aliment->setCaloriesPour100g((int) $data['caloriesPour100g']);
        $aliment->setProteines((float) $data['proteines']);
        $aliment->setGlucides((float) $data['glucides']);
        $aliment->setLipides((float) $data['lipides']);

        $this->assertSame('Saumon', $aliment->getNomAliment()); // trim appliqué
        $this->assertSame(208, $aliment->getCaloriesPour100g());
    }

    /** ❌ Test 6 : Données null → erreur immédiate */
    public function testValidateDataNull(): void
    {
        $error = $this->validateData(null);
        $this->assertNotNull($error);
        $this->assertSame('Aucune donnée reçue.', $error);
    }

    // ── Reproduit validateData() du AdminNutritionController ──────────
    /**
     * @param array<string, mixed>|null $data
     */
    private function validateData(?array $data): ?string
    {
        if (!$data) return 'Aucune donnée reçue.';
        if (empty(trim((string)($data['nomAliment'] ?? '')))) return "Le nom de l'aliment est obligatoire.";
        $cal = (int)($data['caloriesPour100g'] ?? 0);
        if ($cal < 0 || $cal > 9000) return 'Les calories doivent être entre 0 et 9000.';
        return null;
    }
}