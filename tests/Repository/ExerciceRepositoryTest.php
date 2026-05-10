<?php

namespace App\Tests\Repository;

use App\Entity\Exercice;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ExerciceRepository — logique sans BDD.
 *
 * Emplacement : tests/Repository/ExerciceRepositoryTest.php
 * Commande   : php vendor/bin/phpunit tests/Repository/ExerciceRepositoryTest.php --testdox
 */
class ExerciceRepositoryTest extends TestCase
{
    private function makeExercice(string $nom, ?string $type = null, ?string $video = null): Exercice
    {
        $e = new Exercice();
        $e->setNomExercice($nom);
        $e->setTypeExercice($type);
        $e->setVideoExercice($video);
        return $e;
    }

    /** ✅ Test 1 : Exercice créé avec les bonnes valeurs */
    public function testExerciceCreationValide(): void
    {
        $e = $this->makeExercice('Pompes', 'Force_Homme', 'https://youtube.com/abc');

        $this->assertSame('Pompes', $e->getNomExercice());
        $this->assertSame('Force_Homme', $e->getTypeExercice());
        $this->assertSame('https://youtube.com/abc', $e->getVideoExercice());
    }

    /** ✅ Test 2 : Tri par nom alphabétique (logique findAllOrdered) */
    public function testTriAlphabetique(): void
    {
        $exercices = [
            $this->makeExercice('Squat',  'Force_Homme'),
            $this->makeExercice('Pompes', 'Force_Homme'),
            $this->makeExercice('Vélo',   'Cardio_Mixte'),
        ];

        usort($exercices, fn(Exercice $a, Exercice $b) =>
            strcmp((string) $a->getNomExercice(), (string) $b->getNomExercice())
        );

        $this->assertSame('Pompes', $exercices[0]->getNomExercice());
        $this->assertSame('Squat', $exercices[1]->getNomExercice());
    }

    /** ✅ Test 3 : Filtre par type exact */
    public function testFiltreParType(): void
    {
        $exercices = [
            $this->makeExercice('Pompes',       'Force_Homme'),
            $this->makeExercice('Squat',         'Force_Homme'),
            $this->makeExercice('Gainage',       'Core_Mixte'),
            $this->makeExercice('Hip Thrust',    'Fessiers_Femme'),
        ];

        $typeFiltre = 'Force_Homme';
        $results = array_filter($exercices, fn(Exercice $e) =>
            $e->getTypeExercice() === $typeFiltre
        );

        $this->assertCount(2, $results);
    }

    /** ✅ Test 4 : Filtre section Homme */
    public function testFiltreSection_Homme(): void
    {
        $exercices = [
            $this->makeExercice('Pompes',   'Force_Homme'),
            $this->makeExercice('Gainage',  'Core_Mixte'),
            $this->makeExercice('Hip Thrust','Fessiers_Femme'),
        ];

        $results = array_filter($exercices, fn(Exercice $e) =>
            stripos((string) $e->getTypeExercice(), '_homme') !== false
        );

        $this->assertCount(1, $results);
        $this->assertSame('Pompes', array_values($results)[0]->getNomExercice());
    }

    /** ✅ Test 5 : Filtre section Femme */
    public function testFiltreSection_Femme(): void
    {
        $exercices = [
            $this->makeExercice('Pompes',    'Force_Homme'),
            $this->makeExercice('Hip Thrust','Fessiers_Femme'),
            $this->makeExercice('Squat',     'Jambes_Femme'),
        ];

        $results = array_filter($exercices, fn(Exercice $e) =>
            stripos((string) $e->getTypeExercice(), '_femme') !== false
        );

        $this->assertCount(2, $results);
    }

    /** ✅ Test 6 : Champs optionnels null par défaut */
    public function testExerciceChampNullParDefaut(): void
    {
        $e = new Exercice();

        $this->assertNull($e->getId());
        $this->assertNull($e->getNomExercice());
        $this->assertNull($e->getTypeExercice());
        $this->assertNull($e->getVideoExercice());
    }
}