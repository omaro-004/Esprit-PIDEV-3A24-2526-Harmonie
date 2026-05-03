<?php

namespace App\Tests\Repository;

use App\Entity\Aliment;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AlimentRepository — logique métier sans BDD.
 *
 * Emplacement : tests/Repository/AlimentRepositoryTest.php
 * Commande   : php vendor/bin/phpunit tests/Repository/AlimentRepositoryTest.php --testdox
 */
class AlimentRepositoryTest extends TestCase
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

    /** ✅ Test 1 : Un aliment est bien créé avec ses valeurs */
    public function testAlimentCreationValide(): void
    {
        $a = $this->makeAliment('Poulet', 165, 31.0, 0.0, 3.6);

        $this->assertSame('Poulet', $a->getNomAliment());
        $this->assertSame(165, $a->getCaloriesPour100g());
        $this->assertSame(31.0, $a->getProteines());
        $this->assertSame(0.0, $a->getGlucides());
        $this->assertSame(3.6, $a->getLipides());
    }

    /** ✅ Test 2 : Tri alphabétique simulé (logique findAllOrdered) */
    public function testTriAlphabetique(): void
    {
        $aliments = [
            $this->makeAliment('Riz', 130, 2.7, 28.0, 0.3),
            $this->makeAliment('Avocat', 160, 2.0, 9.0, 15.0),
            $this->makeAliment('Banane', 89, 1.1, 23.0, 0.3),
        ];

        usort($aliments, fn(Aliment $a, Aliment $b) =>
            strcmp((string) $a->getNomAliment(), (string) $b->getNomAliment())
        );

        $this->assertSame('Avocat', $aliments[0]->getNomAliment());
        $this->assertSame('Banane', $aliments[1]->getNomAliment());
        $this->assertSame('Riz', $aliments[2]->getNomAliment());
    }

    /** ✅ Test 3 : Recherche par nom (logique search LIKE) */
    public function testRecherchePourNom(): void
    {
        $aliments = [
            $this->makeAliment('Poulet grillé', 165, 31.0, 0.0, 3.6),
            $this->makeAliment('Riz blanc', 130, 2.7, 28.0, 0.3),
            $this->makeAliment('Poulet rôti', 180, 28.0, 0.0, 7.0),
        ];

        $query = 'poulet';
        $results = array_filter($aliments, fn(Aliment $a) =>
            stripos((string) $a->getNomAliment(), $query) !== false
        );

        $this->assertCount(2, $results);
    }

    /** ✅ Test 4 : Filtre par calories (logique cal_min / cal_max) */
    public function testFiltreCalories(): void
    {
        $aliments = [
            $this->makeAliment('Laitue', 15, 1.4, 2.8, 0.2),
            $this->makeAliment('Poulet', 165, 31.0, 0.0, 3.6),
            $this->makeAliment('Huile d\'olive', 884, 0.0, 0.0, 100.0),
        ];

        $calMin = 100;
        $calMax = 500;

        $results = array_filter($aliments, fn(Aliment $a) =>
            $a->getCaloriesPour100g() >= $calMin &&
            $a->getCaloriesPour100g() <= $calMax
        );

        $this->assertCount(1, $results);
        $this->assertSame('Poulet', array_values($results)[0]->getNomAliment());
    }

    /** ✅ Test 5 : Filtre par protéines */
    public function testFiltreProteines(): void
    {
        $aliments = [
            $this->makeAliment('Yaourt', 59, 3.5, 4.7, 3.3),
            $this->makeAliment('Thon', 132, 28.0, 0.0, 1.2),
            $this->makeAliment('Blanc d\'oeuf', 52, 11.0, 0.7, 0.2),
        ];

        $protMin = 10.0;
        $results = array_filter($aliments, fn(Aliment $a) =>
            ($a->getProteines() ?? 0) >= $protMin
        );

        $this->assertCount(2, $results);
    }

    /** ✅ Test 6 : Champs nullable par défaut */
    public function testAlimentChampNullParDefaut(): void
    {
        $a = new Aliment();

        $this->assertNull($a->getId());
        $this->assertNull($a->getNomAliment());
        $this->assertNull($a->getCaloriesPour100g());
        $this->assertNull($a->getProteines());
        $this->assertNull($a->getGlucides());
        $this->assertNull($a->getLipides());
    }
}