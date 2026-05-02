<?php

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * Tests unitaires pour le template Twig : nutrition/recettes.html.twig
 *
 * Emplacement : tests/Twig/RecettesTemplateTest.php
 * Commande   : php vendor/bin/phpunit tests/Twig/RecettesTemplateTest.php --testdox
 */
class RecettesTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $templateContent = (string) file_get_contents(
            __DIR__ . '/../../templates/nutrition/recettes.html.twig'
        );

        $baseStub   = '{% block stylesheets %}{% endblock %}{% block body %}{% endblock %}{% block javascripts %}{% endblock %}';
        $topbarStub = '<div class="topbar">Harmony</div>';

        $loader = new ArrayLoader([
            'nutrition/recettes.html.twig' => $templateContent,
            'base.html.twig'               => $baseStub,
            '_planning_topbar.html.twig'   => $topbarStub,
        ]);

        $this->twig = new Environment($loader, ['autoescape' => 'html']);
        $this->twig->addFunction(new TwigFunction('path', function (string $name, array $params = []): string {
            if (!$params) {
                return '/' . $name;
            }
            return '/' . $name . '?' . http_build_query($params);
        }));
        $this->twig->addFunction(new TwigFunction('asset', fn (string $path) => '/assets/' . ltrim($path, '/')));
    }

    /** @return array<string, mixed> */
    private function makeBaseVars(): array
    {
        return [
            'date'  => '2025-06-01',
            'repas' => 'Déjeuner',
        ];
    }

    /** ✅ Test 1 : Template se compile sans erreur */
    public function testTemplateCompileSansErreur(): void
    {
        $output = $this->twig->render('nutrition/recettes.html.twig', $this->makeBaseVars());
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /** ✅ Test 2 : Titre "Recettes" présent */
    public function testTitreRecettesPresent(): void
    {
        $output = $this->twig->render('nutrition/recettes.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('Recettes', $output);
    }

    /** ✅ Test 3 : La date est passée en variable JS */
    public function testDateDansJs(): void
    {
        $vars         = $this->makeBaseVars();
        $vars['date'] = '2025-07-20';
        $output       = $this->twig->render('nutrition/recettes.html.twig', $vars);
        $this->assertStringContainsString('2025-07-20', $output);
    }

    /** ✅ Test 4 : Le type de repas est passé en variable JS */
    public function testRepasDansJs(): void
    {
        $vars          = $this->makeBaseVars();
        $vars['repas'] = 'Dîner';
        $output        = $this->twig->render('nutrition/recettes.html.twig', $vars);
        $this->assertStringContainsString('Dîner', $output);
    }

    /** ✅ Test 5 : Lien de retour vers le journal contient la date */
    public function testLienRetourContientDate(): void
    {
        $output = $this->twig->render('nutrition/recettes.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('2025-06-01', $output);
    }

    /** ✅ Test 6 : Template contient "Harmony" */
    public function testHarmonyPresent(): void
    {
        $output = $this->twig->render('nutrition/recettes.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('Harmony', $output);
    }
}
