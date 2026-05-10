<?php

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * Tests unitaires pour le template Twig : nutrition/ajouter.html.twig
 *
 * Emplacement : tests/Twig/AjouterTemplateTest.php
 * Commande   : php vendor/bin/phpunit tests/Twig/AjouterTemplateTest.php --testdox
 */
class AjouterTemplateTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $templateContent = (string) file_get_contents(
            __DIR__ . '/../../templates/nutrition/ajouter.html.twig'
        );

        $baseStub   = '{% block stylesheets %}{% endblock %}{% block body %}{% endblock %}{% block javascripts %}{% endblock %}';
        $topbarStub = '<div class="topbar">Harmony</div>';

        $loader = new ArrayLoader([
            'nutrition/ajouter.html.twig' => $templateContent,
            'base.html.twig'              => $baseStub,
            '_planning_topbar.html.twig'  => $topbarStub,
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
            'date'            => '2025-06-01',
            'repas'           => 'Déjeuner',
            'aliments'        => [],
            'currentRepasData'=> ['bg' => '#D1FAE5', 'color' => '#10B981', 'icon' => '🥗'],
            'repasData'       => [
                'Petit-déjeuner' => ['cls' => 'breakfast', 'icon' => '☀️'],
                'Déjeuner'       => ['cls' => 'lunch',     'icon' => '🥗'],
                'Dîner'          => ['cls' => 'dinner',    'icon' => '🌙'],
                'Snack'          => ['cls' => 'snack',     'icon' => '🍎'],
            ],
        ];
    }

    /** ✅ Test 1 : Template se compile sans erreur */
    public function testTemplateCompileSansErreur(): void
    {
        $output = $this->twig->render('nutrition/ajouter.html.twig', $this->makeBaseVars());
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /** ✅ Test 2 : Titre "Ajouter un Aliment" présent */
    public function testTitreAjouterPresent(): void
    {
        $output = $this->twig->render('nutrition/ajouter.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('Ajouter', $output);
    }

    /** ✅ Test 3 : Date affichée au format d/m/Y */
    public function testDateAffichee(): void
    {
        $output = $this->twig->render('nutrition/ajouter.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('01/06/2025', $output);
    }

    /** ✅ Test 4 : Repas actif affiché */
    public function testRepasActifAffiche(): void
    {
        $output = $this->twig->render('nutrition/ajouter.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('Déjeuner', $output);
    }

    /** ✅ Test 5 : Liste aliments vide → message approprié */
    public function testAucunAlimentAfficheMessageVide(): void
    {
        $vars             = $this->makeBaseVars();
        $vars['aliments'] = [];
        $output           = $this->twig->render('nutrition/ajouter.html.twig', $vars);
        // Le template affiche un count "0 aliment disponible"
        $this->assertStringContainsString('0', $output);
    }

    /** ✅ Test 6 : Aliments listés correctement */
    public function testAlimentsAffiches(): void
    {
        $vars             = $this->makeBaseVars();
        $vars['aliments'] = [
            (object)['id' => 1, 'nomAliment' => 'Poulet grillé', 'caloriesPour100g' => 165,
                     'proteines' => 31.0, 'glucides' => 0.0, 'lipides' => 3.6],
            (object)['id' => 2, 'nomAliment' => 'Riz blanc', 'caloriesPour100g' => 130,
                     'proteines' => 2.7, 'glucides' => 28.0, 'lipides' => 0.3],
        ];
        $output = $this->twig->render('nutrition/ajouter.html.twig', $vars);
        $this->assertStringContainsString('Poulet grillé', $output);
        $this->assertStringContainsString('Riz blanc', $output);
        $this->assertStringContainsString('165', $output);
    }

    /** ✅ Test 7 : Les 4 types de repas apparaissent */
    public function testQuatreRepasAffiches(): void
    {
        $output = $this->twig->render('nutrition/ajouter.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('Petit-déjeuner', $output);
        $this->assertStringContainsString('Dîner', $output);
        $this->assertStringContainsString('Snack', $output);
    }

    /** ✅ Test 8 : Repas actif a la classe 'active' */
    public function testRepasActifClasse(): void
    {
        $output = $this->twig->render('nutrition/ajouter.html.twig', $this->makeBaseVars());
        $this->assertStringContainsString('active', $output);
    }

    /** ✅ Test 9 : Le compteur d'aliments est correct */
    public function testCompteurAlimentsCorrect(): void
    {
        $vars             = $this->makeBaseVars();
        $vars['aliments'] = array_fill(0, 3, (object)[
            'id' => 1, 'nomAliment' => 'Test', 'caloriesPour100g' => 100,
            'proteines' => 10.0, 'glucides' => 10.0, 'lipides' => 5.0,
        ]);
        $output = $this->twig->render('nutrition/ajouter.html.twig', $vars);
        $this->assertStringContainsString('3', $output);
    }

    /** ✅ Test 10 : La date est passée en JS comme variable RETURN_DATE */
    public function testReturnDateDansJs(): void
    {
        $vars         = $this->makeBaseVars();
        $vars['date'] = '2025-07-15';
        $output       = $this->twig->render('nutrition/ajouter.html.twig', $vars);
        $this->assertStringContainsString('2025-07-15', $output);
    }
}
