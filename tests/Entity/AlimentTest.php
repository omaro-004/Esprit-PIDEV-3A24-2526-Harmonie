<?php

namespace App\Tests\Entity;

use App\Entity\Aliment;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Aliment.
 *
 * Emplacement : tests/Entity/AlimentTest.php
 * Commande   : php vendor/bin/phpunit tests/Entity/AlimentTest.php --testdox
 */
class AlimentTest extends TestCase
{
    private function makeAliment(): Aliment
    {
        $a = new Aliment();
        $a->setNomAliment('Poulet grillé');
        $a->setCaloriesPour100g(165);
        $a->setProteines(31.0);
        $a->setGlucides(0.0);
        $a->setLipides(3.6);
        return $a;
    }

    /** ✅ Test 1 : Id est null avant persistance */
    public function testIdNullAvantPersistance(): void
    {
        $a = new Aliment();
        $this->assertNull($a->getId());
    }

    /** ✅ Test 2 : setNomAliment / getNomAliment */
    public function testNomAliment(): void
    {
        $a = new Aliment();
        $a->setNomAliment('Thon en boîte');
        $this->assertSame('Thon en boîte', $a->getNomAliment());
    }

    /** ✅ Test 3 : setCaloriesPour100g / getCaloriesPour100g */
    public function testCaloriesPour100g(): void
    {
        $a = new Aliment();
        $a->setCaloriesPour100g(132);
        $this->assertSame(132, $a->getCaloriesPour100g());
    }

    /** ✅ Test 4 : setProteines / getProteines */
    public function testProteines(): void
    {
        $a = new Aliment();
        $a->setProteines(28.5);
        $this->assertSame(28.5, $a->getProteines());
    }

    /** ✅ Test 5 : setGlucides / getGlucides */
    public function testGlucides(): void
    {
        $a = new Aliment();
        $a->setGlucides(12.3);
        $this->assertSame(12.3, $a->getGlucides());
    }

    /** ✅ Test 6 : setLipides / getLipides */
    public function testLipides(): void
    {
        $a = new Aliment();
        $a->setLipides(5.2);
        $this->assertSame(5.2, $a->getLipides());
    }

    /** ✅ Test 7 : Tous les champs sont null par défaut */
    public function testValeursParDefautNulles(): void
    {
        $a = new Aliment();
        $this->assertNull($a->getId());
        $this->assertNull($a->getNomAliment());
        $this->assertNull($a->getCaloriesPour100g());
        $this->assertNull($a->getProteines());
        $this->assertNull($a->getGlucides());
        $this->assertNull($a->getLipides());
    }

    /** ✅ Test 8 : Aliment complètement rempli */
    public function testAlimentCompletementRempli(): void
    {
        $a = $this->makeAliment();
        $this->assertSame('Poulet grillé', $a->getNomAliment());
        $this->assertSame(165, $a->getCaloriesPour100g());
        $this->assertSame(31.0, $a->getProteines());
        $this->assertSame(0.0, $a->getGlucides());
        $this->assertSame(3.6, $a->getLipides());
    }

    /** ✅ Test 9 : setNomAliment retourne static (fluent) */
    public function testSetNomAlimentRetourneStatic(): void
    {
        $a = new Aliment();
        $result = $a->setNomAliment('Riz');
        $this->assertSame($a, $result);
    }

    /** ✅ Test 10 : Modification d'une valeur existante */
    public function testModificationValeur(): void
    {
        $a = $this->makeAliment();
        $a->setCaloriesPour100g(200);
        $this->assertSame(200, $a->getCaloriesPour100g());
    }
}
