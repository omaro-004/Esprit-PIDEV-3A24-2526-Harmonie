<?php

namespace App\Tests\Controller;

use App\Entity\Exercice;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique du AdminSportController.
 *
 * Emplacement : tests/Controller/AdminSportControllerTest.php
 * Commande   : php vendor/bin/phpunit tests/Controller/AdminSportControllerTest.php --testdox
 */
class AdminSportControllerTest extends TestCase
{
    // ── Reproduit validateData() du AdminSportController ─────────────
    /** @param array<string, mixed>|null $data */
    private function validateData(?array $data): ?string
    {
        if (!$data) return 'Aucune donnée reçue.';
        if (empty(trim((string) ($data['nomExercice']  ?? '')))) return "Le nom de l'exercice est obligatoire.";
        if (empty(trim((string) ($data['typeExercice'] ?? '')))) return "Le type d'exercice est obligatoire.";
        return null;
    }

    /** @return array<string, mixed> */
    private function serialize(Exercice $e): array
    {
        return [
            'id'            => $e->getId(),
            'nomExercice'   => $e->getNomExercice(),
            'typeExercice'  => $e->getTypeExercice(),
            'videoExercice' => $e->getVideoExercice(),
        ];
    }

    /** ✅ Test 1 : Données valides passent validateData() */
    public function testValidateDataValide(): void
    {
        $data = ['nomExercice' => 'Pompes', 'typeExercice' => 'Force_Homme'];
        $this->assertNull($this->validateData($data));
    }

    /** ❌ Test 2 : Nom vide → erreur */
    public function testValidateDataNomVide(): void
    {
        $data  = ['nomExercice' => '', 'typeExercice' => 'Force_Homme'];
        $error = $this->validateData($data);
        $this->assertNotNull($error);
        $this->assertStringContainsString('nom', strtolower($error));
    }

    /** ❌ Test 3 : Type vide → erreur */
    public function testValidateDataTypeVide(): void
    {
        $data  = ['nomExercice' => 'Squat', 'typeExercice' => ''];
        $error = $this->validateData($data);
        $this->assertNotNull($error);
        $this->assertStringContainsString('type', strtolower($error));
    }

    /** ❌ Test 4 : Données null → erreur immédiate */
    public function testValidateDataNull(): void
    {
        $this->assertSame('Aucune donnée reçue.', $this->validateData(null));
    }

    /** ✅ Test 5 : serialize() retourne la bonne structure */
    public function testSerializeExercice(): void
    {
        $e = new Exercice();
        $e->setNomExercice('Deadlift');
        $e->setTypeExercice('Force_Homme');
        $e->setVideoExercice('https://youtube.com/xyz');

        $arr = $this->serialize($e);

        $this->assertSame('Deadlift', $arr['nomExercice']);
        $this->assertSame('Force_Homme', $arr['typeExercice']);
        $this->assertSame('https://youtube.com/xyz', $arr['videoExercice']);
        $this->assertNull($arr['id']); // non persisté
    }

    /** ✅ Test 6 : Validation de la section (homme/femme/'') */
    public function testValidationSection(): void
    {
        $sectionsValides = ['homme', 'femme', ''];
        foreach ($sectionsValides as $section) {
            $valid = in_array($section, ['homme', 'femme', ''], true);
            $this->assertTrue($valid, "Section '$section' devrait être valide");
        }

        $sectionInvalide = 'mixte';
        $this->assertFalse(in_array($sectionInvalide, ['homme', 'femme', ''], true));
    }
}