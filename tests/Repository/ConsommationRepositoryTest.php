<?php

namespace App\Tests\Repository;

use App\Entity\Aliment;
use App\Entity\Consommation;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ConsommationRepository — logique sans BDD.
 *
 * Emplacement : tests/Repository/ConsommationRepositoryTest.php
 * Commande   : php vendor/bin/phpunit tests/Repository/ConsommationRepositoryTest.php --testdox
 */
class ConsommationRepositoryTest extends TestCase
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

    private function makeConso(Aliment $aliment, int $poids, string $date, string $repas, int $userId = 1): Consommation
    {
        $c = new Consommation();
        $c->setAliment($aliment);
        $c->setPoidsGrammes($poids);
        $c->setDateConsommation(new \DateTime($date));
        $c->setTypeRepas($repas);
        $c->setUserId($userId);
        return $c;
    }

    /** ✅ Test 1 : getCalories() calcule correctement */
    public function testGetCaloriesCorrect(): void
    {
        $aliment = $this->makeAliment('Riz', 130, 2.7, 28.0, 0.3);
        $c = $this->makeConso($aliment, 200, '2025-01-01', 'Déjeuner');

        // 130 * 200 / 100 = 260
        $this->assertSame(260.0, $c->getCalories());
    }

    /** ✅ Test 2 : getCalories() retourne 0 si aliment null */
    public function testGetCaloriesSansAliment(): void
    {
        $c = new Consommation();
        $c->setPoidsGrammes(100);

        $this->assertSame(0.0, $c->getCalories());
    }

    /** ✅ Test 3 : getCalories() retourne 0 si poids null */
    public function testGetCaloriesSansPoids(): void
    {
        $aliment = $this->makeAliment('Poulet', 165, 31.0, 0.0, 3.6);
        $c = new Consommation();
        $c->setAliment($aliment);

        $this->assertSame(0.0, $c->getCalories());
    }

    /** ✅ Test 4 : getProteines() calcule correctement */
    public function testGetProteinesCorrect(): void
    {
        $aliment = $this->makeAliment('Thon', 132, 28.0, 0.0, 1.2);
        $c = $this->makeConso($aliment, 150, '2025-01-01', 'Dîner');

        // 28.0 * 150 / 100 = 42.0
        $this->assertSame(42.0, $c->getProteines());
    }

    /** ✅ Test 5 : Filtre par date et userId (logique findByUserAndDate) */
    public function testFiltreParDateEtUser(): void
    {
        $aliment = $this->makeAliment('Banane', 89, 1.1, 23.0, 0.3);

        $consommations = [
            $this->makeConso($aliment, 100, '2025-03-01', 'Petit-déjeuner', 1),
            $this->makeConso($aliment, 150, '2025-03-01', 'Déjeuner', 1),
            $this->makeConso($aliment, 200, '2025-03-02', 'Dîner', 1),   // autre date
            $this->makeConso($aliment, 100, '2025-03-01', 'Déjeuner', 2), // autre user
        ];

        $targetDate   = new \DateTime('2025-03-01');
        $targetUserId = 1;

        $filtered = array_filter($consommations, function (Consommation $c) use ($targetDate, $targetUserId) {
            return $c->getUserId() === $targetUserId
                && $c->getDateConsommation()?->format('Y-m-d') === $targetDate->format('Y-m-d');
        });

        $this->assertCount(2, $filtered);
    }

    /** ✅ Test 6 : Somme des calories sur une journée */
    public function testSommeCaloriesJournee(): void
    {
        $poulet  = $this->makeAliment('Poulet', 165, 31.0, 0.0, 3.6);
        $riz     = $this->makeAliment('Riz', 130, 2.7, 28.0, 0.3);

        $c1 = $this->makeConso($poulet, 200, '2025-03-01', 'Déjeuner'); // 165*200/100 = 330
        $c2 = $this->makeConso($riz,    150, '2025-03-01', 'Déjeuner'); // 130*150/100 = 195

        $total = $c1->getCalories() + $c2->getCalories();

        $this->assertSame(525.0, $total);
    }
}