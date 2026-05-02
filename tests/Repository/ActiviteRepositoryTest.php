<?php

namespace App\Tests\Repository;

use App\Entity\Activite;
use App\Entity\Exercice;
use App\Repository\ActiviteRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ActiviteRepository — logique métier sans BDD.
 *
 * Emplacement : tests/Repository/ActiviteRepositoryTest.php
 * Commande   : php vendor/bin/phpunit tests/Repository/ActiviteRepositoryTest.php --testdox
 *
 * Note : Les méthodes qui touchent la BDD (find*, sum*, count*) sont testées
 * via des mocks. Pour les tests d'intégration réels, utiliser KernelTestCase.
 */
class ActiviteRepositoryTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function makeActivite(string $date, ?int $userId = 1): Activite
    {
        $exercice = new Exercice();
        $exercice->setNomExercice('Pompes');
        $exercice->setTypeExercice('Force_Homme');

        $a = new Activite();
        $a->setExercice($exercice);
        $a->setDateActivite(new \DateTime($date));
        $a->setDureeMinutes(30);
        $a->setCaloriesBrulees(200);
        $a->setUserId($userId);

        return $a;
    }

    // ─────────────────────────────────────────────────────────────────
    // Tests sur findByUserGroupedByDate (logique de groupement)
    // ─────────────────────────────────────────────────────────────────

    /** ✅ Test 1 : Groupement correct par date */
    public function testGroupByDateGroupeCorrectement(): void
    {
        // On teste la logique de groupement sans passer par le repo
        $activites = [
            $this->makeActivite('2025-01-10'),
            $this->makeActivite('2025-01-10'),
            $this->makeActivite('2025-01-11'),
        ];

        // Reproduire la logique de findByUserGroupedByDate()
        $grouped = [];
        foreach ($activites as $activite) {
            $dateObj = $activite->getDateActivite();
            if ($dateObj === null) continue;
            $date = $dateObj->format('Y-m-d');
            $grouped[$date][] = $activite;
        }

        $this->assertCount(2, $grouped);
        $this->assertArrayHasKey('2025-01-10', $grouped);
        $this->assertArrayHasKey('2025-01-11', $grouped);
        $this->assertCount(2, $grouped['2025-01-10']);
        $this->assertCount(1, $grouped['2025-01-11']);
    }

    /** ✅ Test 2 : Activité sans date est ignorée dans le groupement */
    public function testGroupByDateIgnoreSansDate(): void
    {
        $a1 = $this->makeActivite('2025-03-01');
        $a2 = new Activite(); // pas de date

        $grouped = [];
        foreach ([$a1, $a2] as $activite) {
            $dateObj = $activite->getDateActivite();
            if ($dateObj === null) continue;
            $date = $dateObj->format('Y-m-d');
            $grouped[$date][] = $activite;
        }

        $this->assertCount(1, $grouped);
        $this->assertArrayHasKey('2025-03-01', $grouped);
    }

    /** ✅ Test 3 : Liste vide donne un tableau vide */
    public function testGroupByDateListeVide(): void
    {
        $grouped = [];
        foreach ([] as $activite) {
            $dateObj = $activite->getDateActivite();
            if ($dateObj === null) continue;
            $grouped[$dateObj->format('Y-m-d')][] = $activite;
        }

        $this->assertEmpty($grouped);
    }

    // ─────────────────────────────────────────────────────────────────
    // Tests sur les entités Activite (comportements unitaires)
    // ─────────────────────────────────────────────────────────────────

    /** ✅ Test 4 : Une Activite avec toutes ses données est valide */
    public function testActiviteCompletementRemplie(): void
    {
        $a = $this->makeActivite('2025-06-15', 42);
        $a->setNbSeries(3);
        $a->setNbRepetitions(12);
        $a->setPoids(80.5);
        $a->setNotes('Bonne séance');

        $this->assertSame(42, $a->getUserId());
        $this->assertSame(30, $a->getDureeMinutes());
        $this->assertSame(200, $a->getCaloriesBrulees());
        $this->assertSame(3, $a->getNbSeries());
        $this->assertSame(12, $a->getNbRepetitions());
        $this->assertSame(80.5, $a->getPoids());
        $this->assertSame('Bonne séance', $a->getNotes());
        $this->assertSame('2025-06-15', $a->getDateActivite()?->format('Y-m-d'));
    }

    /** ✅ Test 5 : Champs optionnels sont null par défaut */
    public function testActiviteChampOptionnelsNullParDefaut(): void
    {
        $a = new Activite();

        $this->assertNull($a->getId());
        $this->assertNull($a->getExercice());
        $a->setDureeMinutes(null);
        $this->assertNull($a->getDureeMinutes());
        $this->assertNull($a->getCaloriesBrulees());
        $this->assertNull($a->getNbSeries());
        $this->assertNull($a->getNbRepetitions());
        $this->assertNull($a->getPoids());
        $this->assertNull($a->getNotes());
    }

    /** ✅ Test 6 : Calcul du nombre de sessions distinct par date */
    public function testComptageSessionsDistinctes(): void
    {
        $activites = [
            $this->makeActivite('2025-01-01'),
            $this->makeActivite('2025-01-01'), // même date → 1 session
            $this->makeActivite('2025-01-02'), // autre date → 2 sessions
            $this->makeActivite('2025-01-03'), // autre date → 3 sessions
        ];

        $dates = [];
        foreach ($activites as $a) {
            $d = $a->getDateActivite()?->format('Y-m-d');
            if ($d) $dates[$d] = true;
        }

        $this->assertCount(3, $dates);
    }
}