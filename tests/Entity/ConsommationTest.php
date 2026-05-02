<?php

namespace App\Tests\Entity;

use App\Entity\Aliment;
use App\Entity\Consommation;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Consommation.
 *
 * Emplacement : tests/Entity/ConsommationTest.php
 * Commande   : php vendor/bin/phpunit tests/Entity/ConsommationTest.php --testdox
 */
class ConsommationTest extends TestCase
{
    private function makeAliment(
        string $nom,
        int    $cal,
        float  $prot,
        float  $gluc,
        float  $lip
    ): Aliment {
        $a = new Aliment();
        $a->setNomAliment($nom);
        $a->setCaloriesPour100g($cal);
        $a->setProteines($prot);
        $a->setGlucides($gluc);
        $a->setLipides($lip);
        return $a;
    }

    private function makeConso(): Consommation
    {
        $aliment = $this->makeAliment('Poulet grillé', 165, 31.0, 0.0, 3.6);
        $c = new Consommation();
        $c->setAliment($aliment);
        $c->setPoidsGrammes(200);
        $c->setDateConsommation(new \DateTime('2025-06-01 12:00:00'));
        $c->setTypeRepas('Déjeuner');
        $c->setUserId(1);
        return $c;
    }

    /** ✅ Test 1 : Id est null avant persistance */
    public function testIdNullAvantPersistance(): void
    {
        $c = new Consommation();
        $this->assertNull($c->getId());
    }

    /** ✅ Test 2 : setAliment / getAliment */
    public function testAliment(): void
    {
        $aliment = $this->makeAliment('Riz', 130, 2.7, 28.0, 0.3);
        $c = new Consommation();
        $c->setAliment($aliment);
        $this->assertSame($aliment, $c->getAliment());
        $this->assertSame('Riz', $c->getAliment()?->getNomAliment());
    }

    /** ✅ Test 3 : setDateConsommation / getDateConsommation */
    public function testDateConsommation(): void
    {
        $date = new \DateTime('2025-06-10 08:30:00');
        $c    = new Consommation();
        $c->setDateConsommation($date);
        $this->assertSame('2025-06-10', $c->getDateConsommation()?->format('Y-m-d'));
    }

    /** ✅ Test 4 : setTypeRepas / getTypeRepas */
    public function testTypeRepas(): void
    {
        $c = new Consommation();
        $c->setTypeRepas('Petit-déjeuner');
        $this->assertSame('Petit-déjeuner', $c->getTypeRepas());
    }

    /** ✅ Test 5 : setPoidsGrammes / getPoidsGrammes */
    public function testPoidsGrammes(): void
    {
        $c = new Consommation();
        $c->setPoidsGrammes(150);
        $this->assertSame(150, $c->getPoidsGrammes());
    }

    /** ✅ Test 6 : setQuantiteEauMl / getQuantiteEauMl */
    public function testQuantiteEauMl(): void
    {
        $c = new Consommation();
        $c->setQuantiteEauMl(250);
        $this->assertSame(250, $c->getQuantiteEauMl());
    }

    /** ✅ Test 7 : setUserId / getUserId */
    public function testUserId(): void
    {
        $c = new Consommation();
        $c->setUserId(7);
        $this->assertSame(7, $c->getUserId());
    }

    /** ✅ Test 8 : getCalories() calcul correct */
    public function testGetCaloriesCalculCorrect(): void
    {
        // 165 * 200 / 100 = 330.0
        $c = $this->makeConso();
        $this->assertSame(330.0, $c->getCalories());
    }

    /** ✅ Test 9 : getProteines() calcul correct */
    public function testGetProteinesCalculCorrect(): void
    {
        // 31.0 * 200 / 100 = 62.0
        $c = $this->makeConso();
        $this->assertSame(62.0, $c->getProteines());
    }

    /** ✅ Test 10 : getGlucides() calcul correct */
    public function testGetGlucidesCalculCorrect(): void
    {
        // 0.0 * 200 / 100 = 0.0
        $c = $this->makeConso();
        $this->assertSame(0.0, $c->getGlucides());
    }

    /** ✅ Test 11 : getLipides() calcul correct */
    public function testGetLipidesCalculCorrect(): void
    {
        // 3.6 * 200 / 100 = 7.2
        $c = $this->makeConso();
        $this->assertEqualsWithDelta(7.2, $c->getLipides(), 0.001);
    }

    /** ✅ Test 12 : getCalories() retourne 0 si aliment null */
    public function testGetCaloriesSansAliment(): void
    {
        $c = new Consommation();
        $c->setPoidsGrammes(100);
        $this->assertSame(0.0, $c->getCalories());
    }

    /** ✅ Test 13 : getCalories() retourne 0 si poids null */
    public function testGetCaloriesSansPoids(): void
    {
        $aliment = $this->makeAliment('Banane', 89, 1.1, 23.0, 0.3);
        $c = new Consommation();
        $c->setAliment($aliment);
        $this->assertSame(0.0, $c->getCalories());
    }

    /** ✅ Test 14 : Tous les champs null par défaut */
    public function testValeursParDefautNulles(): void
    {
        $c = new Consommation();
        $this->assertNull($c->getId());
        $this->assertNull($c->getAliment());
        $this->assertNull($c->getDateConsommation());
        $this->assertNull($c->getTypeRepas());
        $this->assertNull($c->getPoidsGrammes());
        $this->assertNull($c->getQuantiteEauMl());
        $this->assertNull($c->getUserId());
    }

    /** ✅ Test 15 : quantiteEauMl nullable */
    public function testQuantiteEauMlNullable(): void
    {
        $c = new Consommation();
        $c->setQuantiteEauMl(null);
        $this->assertNull($c->getQuantiteEauMl());
    }

    /** ✅ Test 16 : setAliment retourne static (fluent) */
    public function testSetAlimentRetourneStatic(): void
    {
        $aliment = $this->makeAliment('Riz', 130, 2.7, 28.0, 0.3);
        $c       = new Consommation();
        $result  = $c->setAliment($aliment);
        $this->assertSame($c, $result);
    }
}
