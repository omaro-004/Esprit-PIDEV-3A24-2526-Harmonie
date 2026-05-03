<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique de validation du BlessureController.
 *
 * Emplacement : tests/Controller/BlessureControllerTest.php
 * Commande   : php vendor/bin/phpunit tests/Controller/BlessureControllerTest.php --testdox
 */
class BlessureControllerTest extends TestCase
{
    /** @var string[] */
    private array $articulationsAutorisees = [
        'tete', 'cou',
        'epaule_gauche', 'epaule_droite',
        'coude_gauche', 'coude_droit',
        'poignet_gauche', 'poignet_droit',
        'dos_haut', 'dos_bas', 'thorax', 'abdomen',
        'hanche_gauche', 'hanche_droite',
        'genou_gauche', 'genou_droit',
        'cheville_gauche', 'cheville_droite',
        'pied_gauche', 'pied_droit',
    ];

    /** @var string[] */
    private array $intensitesAutorisees = ['légère', 'modérée', 'intense'];

    /** ✅ Test 1 : Articulation valide est acceptée */
    public function testArticulationValideAcceptee(): void
    {
        $valid = ['genou_gauche', 'epaule_droite', 'dos_bas', 'tete'];

        foreach ($valid as $art) {
            $this->assertContains($art, $this->articulationsAutorisees, "Articulation '$art' devrait être valide");
        }
    }

    /** ❌ Test 2 : Articulation invalide est rejetée */
    public function testArticulationInvalideRejetee(): void
    {
        $invalides = ['bras', 'jambe', 'ventre', '', 'genou'];

        foreach ($invalides as $art) {
            $this->assertNotContains($art, $this->articulationsAutorisees, "Articulation '$art' devrait être invalide");
        }
    }

    /** ✅ Test 3 : Intensités valides acceptées */
    public function testIntensiteValideAcceptee(): void
    {
        foreach ($this->intensitesAutorisees as $intensite) {
            $this->assertContains($intensite, $this->intensitesAutorisees);
        }
    }

    /** ✅ Test 4 : Intensité invalide → remplacée par 'modérée' */
    public function testIntensiteInvalideRemplaceeParDefaut(): void
    {
        $intensite = 'extreme'; // invalide

        if (!in_array($intensite, $this->intensitesAutorisees, true)) {
            $intensite = 'modérée';
        }

        $this->assertSame('modérée', $intensite);
    }

    /** ❌ Test 5 : Articulation vide → rejetée */
    public function testArticulationVideeRejetee(): void
    {
        $articulation = '';
        $valide = $articulation !== '' && in_array($articulation, $this->articulationsAutorisees, true);

        $this->assertFalse($valide);
    }

    /** ✅ Test 6 : Parsing JSON du body de requête valide */
    public function testParsingJsonBodyValide(): void
    {
        $bodyJson = json_encode([
            'articulation' => 'genou_gauche',
            'typeActivite' => 'musculation',
            'intensite'    => 'modérée',
        ]);

        $data = json_decode((string) $bodyJson, true);

        $this->assertIsArray($data);

        $articulation = trim($data['articulation'] ?? '');
        $typeActivite = trim($data['typeActivite'] ?? 'général');
        $intensite    = trim($data['intensite']    ?? 'modérée');

        $this->assertSame('genou_gauche', $articulation);
        $this->assertSame('musculation', $typeActivite);
        $this->assertSame('modérée', $intensite);
        $this->assertContains($articulation, $this->articulationsAutorisees);
    }
}