<?php

namespace App\Tests\Service;

use App\Entity\Aliment;
use App\Service\AlimentManager;
use PHPUnit\Framework\TestCase;

class AlimentManagerTest extends TestCase
{
    private AlimentManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AlimentManager();
    }

    /** ✅ Test 1 : Un aliment valide doit passer la validation */
    public function testAlimentValide(): void
    {
        $aliment = new Aliment();
        $aliment->setNomAliment('Poulet grillé');
        $aliment->setCaloriesPour100g(165);
        $aliment->setProteines(31.0);
        $aliment->setGlucides(0.0);
        $aliment->setLipides(3.6);

        $this->assertTrue($this->manager->validate($aliment));
    }

    /** ❌ Test 2 : Nom vide doit échouer */
    public function testAlimentNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'aliment est obligatoire.');

        $aliment = new Aliment();
        $aliment->setNomAliment('');
        $aliment->setCaloriesPour100g(100);
        $aliment->setProteines(10.0);
        $aliment->setGlucides(10.0);
        $aliment->setLipides(5.0);

        $this->manager->validate($aliment);
    }

    /** ❌ Test 3 : Calories négatives doivent échouer */
    public function testAlimentCaloriesNegatives(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les calories doivent être comprises entre 0 et 9000 kcal/100g.');

        $aliment = new Aliment();
        $aliment->setNomAliment('Produit invalide');
        $aliment->setCaloriesPour100g(-50);
        $aliment->setProteines(10.0);
        $aliment->setGlucides(10.0);
        $aliment->setLipides(5.0);

        $this->manager->validate($aliment);
    }

    /** ❌ Test 4 : Calories > 9000 doivent échouer */
    public function testAlimentCaloriesTropElevees(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $aliment = new Aliment();
        $aliment->setNomAliment('Super produit');
        $aliment->setCaloriesPour100g(9999);
        $aliment->setProteines(10.0);
        $aliment->setGlucides(10.0);
        $aliment->setLipides(5.0);

        $this->manager->validate($aliment);
    }

    /** ❌ Test 5 : Protéines > 100 doivent échouer */
    public function testAlimentProteinesTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les protéines doivent être comprises entre 0 et 100 g/100g.');

        $aliment = new Aliment();
        $aliment->setNomAliment('Protéine poudre');
        $aliment->setCaloriesPour100g(400);
        $aliment->setProteines(150.0); // > 100
        $aliment->setGlucides(5.0);
        $aliment->setLipides(5.0);

        $this->manager->validate($aliment);
    }

    /** ❌ Test 6 : Somme des macros > 100 doit échouer */
    public function testAlimentSommeMacrosTropElevee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La somme des macronutriments ne peut pas dépasser 100 g/100g.');

        $aliment = new Aliment();
        $aliment->setNomAliment('Produit impossible');
        $aliment->setCaloriesPour100g(500);
        $aliment->setProteines(50.0);
        $aliment->setGlucides(40.0);
        $aliment->setLipides(30.0); // 50+40+30 = 120 > 100

        $this->manager->validate($aliment);
    }
}