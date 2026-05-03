<?php

namespace App\Tests\Service;

use App\Entity\Activite;
use App\Entity\Exercice;
use App\Service\ActiviteManager;
use PHPUnit\Framework\TestCase;

class ActiviteManagerTest extends TestCase
{
    private ActiviteManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ActiviteManager();
    }

    /** ✅ Test 1 : Une activité valide doit passer la validation */
    public function testActiviteValide(): void
    {
        $exercice = new Exercice();
        $exercice->setNomExercice('Pompes');
        $exercice->setTypeExercice('Force_Homme');

        $activite = new Activite();
        $activite->setExercice($exercice);
        $activite->setDureeMinutes(30);
        $activite->setDateActivite(new \DateTime());
        $activite->setCaloriesBrulees(200);

        $this->assertTrue($this->manager->validate($activite));
    }

    /** ❌ Test 2 : Sans exercice, la validation doit échouer */
    public function testActiviteSansExercice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'exercice est obligatoire.');

        $activite = new Activite();
        $activite->setDureeMinutes(30);
        $activite->setDateActivite(new \DateTime());

        $this->manager->validate($activite);
    }

    /** ❌ Test 3 : Durée = 0 doit échouer */
    public function testActiviteDureeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être comprise entre 1 et 300 minutes.');

        $exercice = new Exercice();
        $exercice->setNomExercice('Squat');

        $activite = new Activite();
        $activite->setExercice($exercice);
        $activite->setDureeMinutes(0);
        $activite->setDateActivite(new \DateTime());

        $this->manager->validate($activite);
    }

    /** ❌ Test 4 : Durée > 300 doit échouer */
    public function testActiviteDureeDepassee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La durée doit être comprise entre 1 et 300 minutes.');

        $exercice = new Exercice();
        $exercice->setNomExercice('Course');

        $activite = new Activite();
        $activite->setExercice($exercice);
        $activite->setDureeMinutes(500);
        $activite->setDateActivite(new \DateTime());

        $this->manager->validate($activite);
    }

    /** ❌ Test 5 : Sans date, la validation doit échouer */
    public function testActiviteSansDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de l\'activité est obligatoire.');

        $exercice = new Exercice();
        $exercice->setNomExercice('Vélo');

        $activite = new Activite();
        $activite->setExercice($exercice);
        $activite->setDureeMinutes(45);
        // Pas de date

        $this->manager->validate($activite);
    }

    /** ❌ Test 6 : Calories négatives doivent échouer */
    public function testActiviteCaloriesNegatives(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les calories brûlées ne peuvent pas être négatives.');

        $exercice = new Exercice();
        $exercice->setNomExercice('Natation');

        $activite = new Activite();
        $activite->setExercice($exercice);
        $activite->setDureeMinutes(60);
        $activite->setDateActivite(new \DateTime());
        $activite->setCaloriesBrulees(-100);

        $this->manager->validate($activite);
    }
}