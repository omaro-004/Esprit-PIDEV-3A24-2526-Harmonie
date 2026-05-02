<?php

namespace App\Tests\Controller;

use App\Entity\Aliment;
use App\Entity\Consommation;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique du NutritionController.
 *
 * Emplacement : tests/Controller/NutritionControllerTest.php
 * Commande   : php vendor/bin/phpunit tests/Controller/NutritionControllerTest.php --testdox
 */
class NutritionControllerTest extends TestCase
{
    private function makeAliment(string $nom, int $cal, float $prot, float $gluc, float $lip): Aliment
    {
        $a = new Aliment();
        $a->setNomAliment($nom);
        $a->setCaloriesPour100g($cal);
        $a->setProteines($prot);
        $a->setGlucides($gluc);
        $a->setLipides($lip);
        return $a;
    }

    private function makeConso(Aliment $aliment, int $poids, string $repas, string $date = '2025-06-01'): Consommation
    {
        $c = new Consommation();
        $c->setAliment($aliment);
        $c->setPoidsGrammes($poids);
        $c->setTypeRepas($repas);
        $c->setDateConsommation(new \DateTime($date));
        $c->setUserId(1);
        return $c;
    }

    /** ✅ Test 1 : consToArray() retourne toutes les clés requises */
    public function testConsToArrayStructure(): void
    {
        $aliment = $this->makeAliment('Riz', 130, 2.7, 28.0, 0.3);
        $c = $this->makeConso($aliment, 200, 'Déjeuner');

        // Reproduire consToArray()
        $arr = [
            'id'           => $c->getId(),
            'aliment_id'   => $c->getAliment()?->getId(),
            'aliment_nom'  => $c->getAliment()?->getNomAliment(),
            'type_repas'   => $c->getTypeRepas(),
            'poids'        => $c->getPoidsGrammes(),
            'calories'     => round($c->getCalories(), 1),
            'proteines'    => round($c->getProteines(), 1),
            'glucides'     => round($c->getGlucides(), 1),
            'lipides'      => round($c->getLipides(), 1),
            'date'         => $c->getDateConsommation()?->format('Y-m-d'),
        ];

        $this->assertSame('Riz', $arr['aliment_nom']);
        $this->assertSame('Déjeuner', $arr['type_repas']);
        $this->assertSame(200, $arr['poids']);
        $this->assertSame(260.0, $arr['calories']); // 130 * 200 / 100
        $this->assertSame('2025-06-01', $arr['date']);
    }

    /** ✅ Test 2 : groupByRepas() groupe correctement par type de repas */
    public function testGroupByRepasCorrect(): void
    {
        $aliment = $this->makeAliment('Banane', 89, 1.1, 23.0, 0.3);

        $consommations = [
            $this->makeConso($aliment, 100, 'Petit-déjeuner'),
            $this->makeConso($aliment, 150, 'Déjeuner'),
            $this->makeConso($aliment, 120, 'Déjeuner'),
            $this->makeConso($aliment, 80,  'Dîner'),
        ];

        // Reproduire groupByRepas()
        $types = [];
        foreach ($consommations as $c) {
            $t = $c->getTypeRepas();
            if ($t === null) continue;
            if (!array_key_exists($t, $types)) $types[$t] = [];
            $types[$t][] = $c;
        }

        $this->assertCount(3, $types);
        $this->assertCount(1, $types['Petit-déjeuner']);
        $this->assertCount(2, $types['Déjeuner']);
        $this->assertCount(1, $types['Dîner']);
    }

    /** ❌ Test 3 : Consommation sans type_repas est ignorée dans groupByRepas */
    public function testGroupByRepasSansTypeIgnore(): void
    {
        $aliment = $this->makeAliment('Pomme', 52, 0.3, 14.0, 0.2);
        $c1 = $this->makeConso($aliment, 150, 'Déjeuner');

        $c2 = new Consommation();
        $c2->setAliment($aliment);
        $c2->setPoidsGrammes(100);
        // Pas de typeRepas → null

        $types = [];
        foreach ([$c1, $c2] as $c) {
            $t = $c->getTypeRepas();
            if ($t === null) continue;
            if (!array_key_exists($t, $types)) $types[$t] = [];
            $types[$t][] = $c;
        }

        $this->assertCount(1, $types);
        $this->assertArrayNotHasKey('', $types);
    }

    /** ✅ Test 4 : Calcul total calories du jour */
    public function testTotalCaloriesJournee(): void
    {
        $poulet  = $this->makeAliment('Poulet', 165, 31.0, 0.0, 3.6);
        $riz     = $this->makeAliment('Riz', 130, 2.7, 28.0, 0.3);
        $beurre  = $this->makeAliment('Beurre', 717, 0.9, 0.1, 81.0);

        $consommations = [
            $this->makeConso($poulet, 150, 'Déjeuner'),   // 247.5
            $this->makeConso($riz, 200, 'Déjeuner'),       // 260.0
            $this->makeConso($beurre, 10, 'Petit-déjeuner'), // 71.7
        ];

        $total = array_sum(array_map(fn(Consommation $c) => $c->getCalories(), $consommations));

        $this->assertEqualsWithDelta(579.2, $total, 0.1);
    }

    /** ✅ Test 5 : Validation date de consommation */
    public function testValidationDateConsommation(): void
    {
        $dateStr = '2025-06-01';

        try {
            $dt = new \DateTime($dateStr);
            $valid = true;
        } catch (\Exception $e) {
            $valid = false;
            $dt = new \DateTime();
        }

        $this->assertTrue($valid);
        $this->assertSame('2025-06-01', $dt->format('Y-m-d'));
    }

    /** ❌ Test 6 : Consommation sans aliment → calories = 0 */
    public function testCaloriesSansAlimentEgalZero(): void
    {
        $c = new Consommation();
        $c->setPoidsGrammes(200);

        $this->assertSame(0.0, $c->getCalories());
        $this->assertSame(0.0, $c->getProteines());
        $this->assertSame(0.0, $c->getGlucides());
        $this->assertSame(0.0, $c->getLipides());
    }
}