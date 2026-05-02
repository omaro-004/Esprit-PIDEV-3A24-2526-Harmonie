<?php

namespace App\Tests\Entity;

use App\Entity\Activite;
use App\Entity\Exercice;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Activite.
 *
 * Emplacement : tests/Entity/ActiviteTest.php
 * Commande   : php vendor/bin/phpunit tests/Entity/ActiviteTest.php --testdox
 */
class ActiviteTest extends TestCase
{
    private function makeExercice(): Exercice
    {
        $e = new Exercice();
        $e->setNomExercice('Pompes');
        $e->setTypeExercice('Force_Homme');
        return $e;
    }

    private function makeActivite(): Activite
    {
        $a = new Activite();
        $a->setExercice($this->makeExercice());
        $a->setDateActivite(new \DateTime('2025-06-01'));
        $a->setDureeMinutes(45);
        $a->setCaloriesBrulees(300);
        $a->setNbSeries(3);
        $a->setNbRepetitions(12);
        $a->setPoids(75.5);
        $a->setNotes('Bonne séance');
        $a->setUserId(1);
        return $a;
    }

    /** ✅ Test 1 : Id est null avant persistance */
    public function testIdNullAvantPersistance(): void
    {
        $a = new Activite();
        $this->assertNull($a->getId());
    }

    /** ✅ Test 2 : setExercice / getExercice */
    public function testExercice(): void
    {
        $e = $this->makeExercice();
        $a = new Activite();
        $a->setExercice($e);
        $this->assertSame($e, $a->getExercice());
        $this->assertSame('Pompes', $a->getExercice()?->getNomExercice());
    }

    /** ✅ Test 3 : setDateActivite / getDateActivite */
    public function testDateActivite(): void
    {
        $date = new \DateTime('2025-06-15');
        $a    = new Activite();
        $a->setDateActivite($date);
        $this->assertSame('2025-06-15', $a->getDateActivite()?->format('Y-m-d'));
    }

    /** ✅ Test 4 : setDureeMinutes / getDureeMinutes */
    public function testDureeMinutes(): void
    {
        $a = new Activite();
        $a->setDureeMinutes(60);
        $this->assertSame(60, $a->getDureeMinutes());
    }

    /** ✅ Test 5 : setCaloriesBrulees / getCaloriesBrulees */
    public function testCaloriesBrulees(): void
    {
        $a = new Activite();
        $a->setCaloriesBrulees(450);
        $this->assertSame(450, $a->getCaloriesBrulees());
    }

    /** ✅ Test 6 : setNbSeries / getNbSeries */
    public function testNbSeries(): void
    {
        $a = new Activite();
        $a->setNbSeries(4);
        $this->assertSame(4, $a->getNbSeries());
    }

    /** ✅ Test 7 : setNbRepetitions / getNbRepetitions */
    public function testNbRepetitions(): void
    {
        $a = new Activite();
        $a->setNbRepetitions(10);
        $this->assertSame(10, $a->getNbRepetitions());
    }

    /** ✅ Test 8 : setPoids / getPoids */
    public function testPoids(): void
    {
        $a = new Activite();
        $a->setPoids(80.5);
        $this->assertSame(80.5, $a->getPoids());
    }

    /** ✅ Test 9 : setNotes / getNotes */
    public function testNotes(): void
    {
        $a = new Activite();
        $a->setNotes('Excellent entraînement');
        $this->assertSame('Excellent entraînement', $a->getNotes());
    }

    /** ✅ Test 10 : setUserId / getUserId */
    public function testUserId(): void
    {
        $a = new Activite();
        $a->setUserId(42);
        $this->assertSame(42, $a->getUserId());
    }

    /** ✅ Test 11 : Tous les champs null par défaut */
    public function testValeursParDefautNulles(): void
    {
        $a = new Activite();
        $this->assertNull($a->getId());
        $this->assertNull($a->getExercice());
        $this->assertNull($a->getDateActivite());
        $this->assertNull($a->getDureeMinutes());
        $this->assertNull($a->getCaloriesBrulees());
        $this->assertNull($a->getNbSeries());
        $this->assertNull($a->getNbRepetitions());
        $this->assertNull($a->getPoids());
        $this->assertNull($a->getNotes());
        $this->assertNull($a->getUserId());
    }

    /** ✅ Test 12 : Activite complètement remplie */
    public function testActiviteCompletementRemplie(): void
    {
        $a = $this->makeActivite();
        $this->assertSame('Pompes', $a->getExercice()?->getNomExercice());
        $this->assertSame('2025-06-01', $a->getDateActivite()?->format('Y-m-d'));
        $this->assertSame(45, $a->getDureeMinutes());
        $this->assertSame(300, $a->getCaloriesBrulees());
        $this->assertSame(3, $a->getNbSeries());
        $this->assertSame(12, $a->getNbRepetitions());
        $this->assertSame(75.5, $a->getPoids());
        $this->assertSame('Bonne séance', $a->getNotes());
        $this->assertSame(1, $a->getUserId());
    }

    /** ✅ Test 13 : exercice peut être null */
    public function testExerciceNullable(): void
    {
        $a = new Activite();
        $a->setExercice(null);
        $this->assertNull($a->getExercice());
    }

    /** ✅ Test 14 : setDureeMinutes retourne static (fluent) */
    public function testSetDureeMinutesRetourneStatic(): void
    {
        $a      = new Activite();
        $result = $a->setDureeMinutes(30);
        $this->assertSame($a, $result);
    }
}
