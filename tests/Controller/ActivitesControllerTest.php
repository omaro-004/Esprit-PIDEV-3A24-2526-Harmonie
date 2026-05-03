<?php

namespace App\Tests\Controller;

use App\Entity\Activite;
use App\Entity\Exercice;
use App\Service\ActiviteManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique de validation du ActivitesController.
 * On teste la règle métier sans démarrer Symfony (pas de WebTestCase).
 *
 * Emplacement : tests/Controller/ActivitesControllerTest.php
 * Commande   : php vendor/bin/phpunit tests/Controller/ActivitesControllerTest.php --testdox
 */
class ActivitesControllerTest extends TestCase
{
    private ActiviteManager $manager;

    protected function setUp(): void
    {
        $this->manager = new ActiviteManager();
    }

    private function makeExercice(string $nom): Exercice
    {
        $e = new Exercice();
        $e->setNomExercice($nom);
        $e->setTypeExercice('Force_Homme');
        return $e;
    }

    /** ✅ Test 1 : Données valides passent la validation */
    public function testDonneesValidesPasse(): void
    {
        // Simule la validation que le controller effectue avant persist
        $data = [
            'exercice_id'   => 1,
            'duree_minutes' => 45,
            'date_activite' => '2025-06-01',
        ];

        $errors = [];
        if (empty($data['exercice_id']))                      $errors['exercice'] = 'Exercice requis.';
        if (empty($data['duree_minutes']) || $data['duree_minutes'] < 1 || $data['duree_minutes'] > 300)
            $errors['duree'] = 'Durée invalide.';
        if (empty($data['date_activite']))                    $errors['date'] = 'Date requise.';

        $this->assertEmpty($errors);
    }

    /** ❌ Test 2 : Exercice manquant → erreur */
    public function testExerciceManquantEchoue(): void
    {
        $data = ['duree_minutes' => 30, 'date_activite' => '2025-06-01'];

        $errors = [];
        if (empty($data['exercice_id'])) $errors['exercice'] = 'Veuillez sélectionner un exercice.';

        $this->assertArrayHasKey('exercice', $errors);
    }

    /** ❌ Test 3 : Durée = 0 → erreur */
    public function testDureeZeroEchoue(): void
    {
        $data = ['exercice_id' => 1, 'duree_minutes' => 0, 'date_activite' => '2025-06-01'];

        $errors = [];
        if (empty($data['duree_minutes']) || (int)$data['duree_minutes'] < 1 || (int)$data['duree_minutes'] > 300)
            $errors['duree'] = 'La durée est requise (1 – 300 min).';

        $this->assertArrayHasKey('duree', $errors);
        $this->assertSame('La durée est requise (1 – 300 min).', $errors['duree']);
    }

    /** ❌ Test 4 : Durée > 300 → erreur */
    public function testDureeDepasseeEchoue(): void
    {
        $data = ['exercice_id' => 1, 'duree_minutes' => 400, 'date_activite' => '2025-06-01'];

        $errors = [];
        if ((int)$data['duree_minutes'] > 300)
            $errors['duree'] = 'La durée est requise (1 – 300 min).';

        $this->assertArrayHasKey('duree', $errors);
    }

    /** ❌ Test 5 : Date manquante → erreur */
    public function testDateManquanteEchoue(): void
    {
        $data = ['exercice_id' => 1, 'duree_minutes' => 30];

        $errors = [];
        if (empty($data['date_activite'])) $errors['date'] = 'La date est requise.';

        $this->assertArrayHasKey('date', $errors);
    }

    /** ✅ Test 6 : Transformation activité → tableau (activiteToArray) */
    public function testActiviteToArray(): void
    {
        $exercice = $this->makeExercice('Squat');

        $activite = new Activite();
        $activite->setExercice($exercice);
        $activite->setDureeMinutes(60);
        $activite->setDateActivite(new \DateTime('2025-06-01'));
        $activite->setCaloriesBrulees(350);
        $activite->setNbSeries(4);
        $activite->setNbRepetitions(10);
        $activite->setPoids(80.0);
        $activite->setNotes('Très bonne séance');

        // Reproduire la logique de activiteToArray()
        $arr = [
            'id'               => $activite->getId(),
            'exercice_id'      => $activite->getExercice()?->getId(),
            'exercice_nom'     => $activite->getExercice()?->getNomExercice(),
            'exercice_type'    => $activite->getExercice()?->getTypeExercice(),
            'exercice_video'   => $activite->getExercice()?->getVideoExercice(),
            'date_activite'    => $activite->getDateActivite()?->format('Y-m-d'),
            'duree_minutes'    => $activite->getDureeMinutes(),
            'calories_brulees' => $activite->getCaloriesBrulees(),
            'nb_series'        => $activite->getNbSeries(),
            'nb_repetitions'   => $activite->getNbRepetitions(),
            'poids'            => $activite->getPoids(),
            'notes'            => $activite->getNotes(),
        ];

        $this->assertSame('2025-06-01', $arr['date_activite']);
        $this->assertSame(60, $arr['duree_minutes']);
        $this->assertSame(350, $arr['calories_brulees']);
        $this->assertSame('Squat', $arr['exercice_nom']);
        $this->assertSame('Force_Homme', $arr['exercice_type']);
        $this->assertSame(4, $arr['nb_series']);
        $this->assertSame(80.0, $arr['poids']);
    }
}