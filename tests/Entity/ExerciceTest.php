<?php

namespace App\Tests\Entity;

use App\Entity\Exercice;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Exercice.
 *
 * Emplacement : tests/Entity/ExerciceTest.php
 * Commande   : php vendor/bin/phpunit tests/Entity/ExerciceTest.php --testdox
 */
class ExerciceTest extends TestCase
{
    private function makeExercice(): Exercice
    {
        $e = new Exercice();
        $e->setNomExercice('Pompes');
        $e->setTypeExercice('Force_Homme');
        $e->setVideoExercice('https://youtube.com/abc123');
        return $e;
    }

    /** ✅ Test 1 : Id est null avant persistance */
    public function testIdNullAvantPersistance(): void
    {
        $e = new Exercice();
        $this->assertNull($e->getId());
    }

    /** ✅ Test 2 : setNomExercice / getNomExercice */
    public function testNomExercice(): void
    {
        $e = new Exercice();
        $e->setNomExercice('Squat');
        $this->assertSame('Squat', $e->getNomExercice());
    }

    /** ✅ Test 3 : setTypeExercice / getTypeExercice */
    public function testTypeExercice(): void
    {
        $e = new Exercice();
        $e->setTypeExercice('Cardio_Mixte');
        $this->assertSame('Cardio_Mixte', $e->getTypeExercice());
    }

    /** ✅ Test 4 : setVideoExercice / getVideoExercice */
    public function testVideoExercice(): void
    {
        $e = new Exercice();
        $e->setVideoExercice('https://youtube.com/xyz');
        $this->assertSame('https://youtube.com/xyz', $e->getVideoExercice());
    }

    /** ✅ Test 5 : Champs optionnels null par défaut */
    public function testValeursParDefautNulles(): void
    {
        $e = new Exercice();
        $this->assertNull($e->getId());
        $this->assertNull($e->getNomExercice());
        $this->assertNull($e->getTypeExercice());
        $this->assertNull($e->getVideoExercice());
    }

    /** ✅ Test 6 : typeExercice peut être null (optionnel) */
    public function testTypeExerciceNullable(): void
    {
        $e = new Exercice();
        $e->setTypeExercice(null);
        $this->assertNull($e->getTypeExercice());
    }

    /** ✅ Test 7 : videoExercice peut être null (optionnel) */
    public function testVideoExerciceNullable(): void
    {
        $e = new Exercice();
        $e->setVideoExercice(null);
        $this->assertNull($e->getVideoExercice());
    }

    /** ✅ Test 8 : Exercice complètement rempli */
    public function testExerciceCompletementRempli(): void
    {
        $e = $this->makeExercice();
        $this->assertSame('Pompes', $e->getNomExercice());
        $this->assertSame('Force_Homme', $e->getTypeExercice());
        $this->assertSame('https://youtube.com/abc123', $e->getVideoExercice());
    }

    /** ✅ Test 9 : setNomExercice retourne static (fluent) */
    public function testSetNomExerciceRetourneStatic(): void
    {
        $e = new Exercice();
        $result = $e->setNomExercice('Deadlift');
        $this->assertSame($e, $result);
    }

    /** ✅ Test 10 : Modification du nom */
    public function testModificationNom(): void
    {
        $e = $this->makeExercice();
        $e->setNomExercice('Burpees');
        $this->assertSame('Burpees', $e->getNomExercice());
    }
}
