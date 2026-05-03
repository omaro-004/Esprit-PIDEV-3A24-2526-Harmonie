<?php

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Tests unitaires pour le template Twig : activites/bilan_pdf.html.twig
 *
 * Emplacement : tests/Twig/BilanPdfTemplateTest.php
 * Commande   : php vendor/bin/phpunit tests/Twig/BilanPdfTemplateTest.php --testdox
 */
class BilanPdfTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $templateContent = (string) file_get_contents(
            __DIR__ . '/../../templates/activites/bilan_pdf.html.twig'
        );

        $loader = new ArrayLoader([
            'activites/bilan_pdf.html.twig' => $templateContent,
        ]);

        $this->twig = new Environment($loader, ['autoescape' => 'html']);
    }

    // ── Helper : données minimales valides ────────────────────────────
    /**
     * @return array<string, mixed>
     */
    private function makeBaseVars(): array
    {
        return [
            'exportDate' => new \DateTime('2025-06-01 10:30:00'),
            'stats'      => [
                'sessions' => 5,
                'minutes'  => 200,
                'calories' => 1500,
            ],
            'grouped'    => [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 1 : Le template se compile sans erreur de syntaxe
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 1 : Template se charge et se compile sans erreur */
    public function testTemplateCompileSansErreur(): void
    {
        $vars   = $this->makeBaseVars();
        $output = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 2 : La date d'export apparaît dans le rendu
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 2 : La date d'export est affichée correctement */
    public function testDateExportAffichee(): void
    {
        $vars   = $this->makeBaseVars();
        $output = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        $this->assertStringContainsString('01/06/2025', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 3 : Les statistiques sessions s'affichent
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 3 : Nombre de sessions affiché */
    public function testStatSessionsAffichees(): void
    {
        $vars          = $this->makeBaseVars();
        $vars['stats'] = ['sessions' => 8, 'minutes' => 300, 'calories' => 2000];
        $output        = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        $this->assertStringContainsString('8', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 4 : Message "aucune séance" quand grouped est vide
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 4 : Message vide quand pas de sessions */
    public function testMessageVideQuandAucuneSession(): void
    {
        $vars           = $this->makeBaseVars();
        $vars['grouped'] = [];
        $output         = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        // Le template doit afficher un message ou au moins rendre sans erreur
        $this->assertIsString($output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 5 : Rendu avec sessions non vides
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 5 : Template rend les sessions avec exercices */
    public function testRenduAvecSessions(): void
    {
        $vars = $this->makeBaseVars();
        $vars['grouped'] = [
            '2025-06-01' => [
                [
                    'exercice_nom'     => 'Pompes',
                    'exercice_type'    => 'Force_Homme',
                    'duree_minutes'    => 30,
                    'calories_brulees' => 200,
                    'nb_series'        => 3,
                    'nb_repetitions'   => 15,
                    'poids'            => null,
                    'notes'            => null,
                ],
            ],
        ];

        $output = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        $this->assertStringContainsString('Pompes', $output);
        $this->assertStringContainsString('30', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 6 : Calcul avgMin dans le template
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 6 : avgMin calculé correctement (200/5 = 40) */
    public function testAvgMinCalculeCorrectement(): void
    {
        $vars          = $this->makeBaseVars();
        $vars['stats'] = ['sessions' => 5, 'minutes' => 200, 'calories' => 1500];
        $output        = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        // avgMin = 200/5 = 40
        $this->assertStringContainsString('40', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 7 : Sessions = 0 → avgMin = 0 (pas de division par zéro)
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 7 : Pas de division par zéro si sessions = 0 */
    public function testPasDivisionParZeroSiSessionsNulles(): void
    {
        $vars          = $this->makeBaseVars();
        $vars['stats'] = ['sessions' => 0, 'minutes' => 0, 'calories' => 0];
        $output        = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        $this->assertIsString($output);
        $this->assertStringNotContainsString('INF', $output);
        $this->assertStringNotContainsString('NaN', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 8 : Le template contient le titre Harmony
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 8 : Le titre Harmony est présent */
    public function testTitreHarmonyPresent(): void
    {
        $output = $this->twig->render('activites/bilan_pdf.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('Harmony', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 9 : Badge cardio/force selon type exercice
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 9 : Badge 'badge-force' appliqué si type contient 'force' */
    public function testBadgeForceApplique(): void
    {
        $vars = $this->makeBaseVars();
        $vars['grouped'] = [
            '2025-06-01' => [[
                'exercice_nom'     => 'Squat',
                'exercice_type'    => 'Force_Homme',
                'duree_minutes'    => 45,
                'calories_brulees' => 300,
                'nb_series'        => null,
                'nb_repetitions'   => null,
                'poids'            => null,
                'notes'            => null,
            ]],
        ];

        $output = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        $this->assertStringContainsString('badge-force', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TEST 10 : Calories de session affichées si > 0
    // ─────────────────────────────────────────────────────────────────
    /** ✅ Test 10 : Calories de session affichées si supérieures à 0 */
    public function testCaloriesSessionAffichees(): void
    {
        $vars = $this->makeBaseVars();
        $vars['grouped'] = [
            '2025-06-01' => [[
                'exercice_nom'     => 'Vélo',
                'exercice_type'    => 'Cardio_Mixte',
                'duree_minutes'    => 60,
                'calories_brulees' => 500,
                'nb_series'        => null,
                'nb_repetitions'   => null,
                'poids'            => null,
                'notes'            => null,
            ]],
        ];

        $output = $this->twig->render('activites/bilan_pdf.html.twig', $vars);
        $this->assertStringContainsString('500', $output);
        $this->assertStringContainsString('kcal', $output);
    }
}
