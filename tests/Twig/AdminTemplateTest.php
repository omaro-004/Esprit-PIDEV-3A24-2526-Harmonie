<?php

namespace App\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * Tests unitaires pour les templates Twig admin :
 *   - admin/nutrition.html.twig
 *   - admin/sport.html.twig
 *
 * Emplacement : tests/Twig/AdminTemplateTest.php
 * Commande   : php vendor/bin/phpunit tests/Twig/AdminTemplateTest.php --testdox
 */
class AdminTemplateTest extends TestCase
{
    // ── Helper : construit un environnement Twig avec un template donné ──
    private function buildEnv(string $templatePath, string $templateName): Environment
    {
        $content = (string) file_get_contents($templatePath);

        // Stub des bases admin
        $baseStub      = '{% block stylesheets %}{% endblock %}{% block body %}{% endblock %}{% block javascripts %}{% endblock %}{% block page_title %}{% endblock %}';
        $adminBaseStub = $baseStub;
        $topbarStub    = '<div class="topbar">Harmony</div>';

        $loader = new ArrayLoader([
            $templateName            => $content,
            'admin/base.html.twig'   => $adminBaseStub,
            'base.html.twig'         => $baseStub,
            '_planning_topbar.html.twig' => $topbarStub,
        ]);

        $twig = new Environment($loader, ['autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('path', function (string $name, array $params = []): string {
            if (!$params) {
                return '/' . $name;
            }
            return '/' . $name . '?' . http_build_query($params);
        }));
        $twig->addFunction(new TwigFunction('asset', fn (string $path) => '/assets/' . ltrim($path, '/')));
        return $twig;
    }

    /** @return array<string, mixed> */
    private function makeAdminNutritionVars(): array
    {
        return [
            'filters'    => [],
            'aliments'   => [],
            'pagination' => (object) [
                'currentPageNumber' => 1,
                'pageCount'         => 1,
                'totalItemCount'    => 0,
                'numItemsPerPage'   => 10,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // TESTS — admin/nutrition.html.twig
    // ─────────────────────────────────────────────────────────────────

    /** ✅ Test 1 : Admin nutrition — template se compile */
    public function testAdminNutritionCompile(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/nutrition.html.twig',
            'admin/nutrition.html.twig'
        );
        $output = $twig->render('admin/nutrition.html.twig', $this->makeAdminNutritionVars());
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /** ✅ Test 2 : Admin nutrition — titre "Nutrition" présent */
    public function testAdminNutritionTitrePresent(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/nutrition.html.twig',
            'admin/nutrition.html.twig'
        );
        $output = $twig->render('admin/nutrition.html.twig', $this->makeAdminNutritionVars());
        $this->assertStringContainsString('Nutrition', $output);
    }

    /** ✅ Test 3 : Admin nutrition — bouton "Ajouter" présent */
    public function testAdminNutritionBoutonAjouter(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/nutrition.html.twig',
            'admin/nutrition.html.twig'
        );
        $output = $twig->render('admin/nutrition.html.twig', $this->makeAdminNutritionVars());
        $this->assertStringContainsString('Ajouter', $output);
    }

    /** ✅ Test 4 : Admin nutrition — bouton export Excel présent */
    public function testAdminNutritionBoutonExcel(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/nutrition.html.twig',
            'admin/nutrition.html.twig'
        );
        $output = $twig->render('admin/nutrition.html.twig', $this->makeAdminNutritionVars());
        $this->assertStringContainsString('Excel', $output);
    }

    /** ✅ Test 5 : Admin nutrition — champ de recherche présent */
    public function testAdminNutritionChampRecherche(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/nutrition.html.twig',
            'admin/nutrition.html.twig'
        );
        $output = $twig->render('admin/nutrition.html.twig', $this->makeAdminNutritionVars());
        $this->assertStringContainsString('search', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    // TESTS — admin/sport.html.twig
    // ─────────────────────────────────────────────────────────────────

    /** ✅ Test 6 : Admin sport — template se compile */
    public function testAdminSportCompile(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/sport.html.twig',
            'admin/sport.html.twig'
        );
        $output = $twig->render('admin/sport.html.twig');
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /** ✅ Test 7 : Admin sport — titre "Sport" présent */
    public function testAdminSportTitrePresent(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/sport.html.twig',
            'admin/sport.html.twig'
        );
        $output = $twig->render('admin/sport.html.twig');
        $this->assertStringContainsString('Sport', $output);
    }

    /** ✅ Test 8 : Admin sport — onglets Femme/Homme/Tous présents */
    public function testAdminSportOngletsSections(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/sport.html.twig',
            'admin/sport.html.twig'
        );
        $output = $twig->render('admin/sport.html.twig');
        $this->assertStringContainsString('Femme', $output);
        $this->assertStringContainsString('Homme', $output);
        $this->assertStringContainsString('Tous', $output);
    }

    /** ✅ Test 9 : Admin sport — bouton "Ajouter un exercice" présent */
    public function testAdminSportBoutonAjouter(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/sport.html.twig',
            'admin/sport.html.twig'
        );
        $output = $twig->render('admin/sport.html.twig');
        $this->assertStringContainsString('Ajouter', $output);
    }

    /** ✅ Test 10 : Admin sport — champ recherche présent */
    public function testAdminSportChampRecherche(): void
    {
        $twig   = $this->buildEnv(
            __DIR__ . '/../../templates/admin/sport.html.twig',
            'admin/sport.html.twig'
        );
        $output = $twig->render('admin/sport.html.twig');
        $this->assertStringContainsString('search', $output);
    }
}
