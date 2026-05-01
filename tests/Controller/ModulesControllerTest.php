<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ModulesControllerTest extends WebTestCase
{
    public function testAllModulePagesLoad(): void
    {
        $client = static::createClient();

        // Test all module routes
        $routes = [
            '/activites' => 'Activités - Harmony',
            '/forum' => 'Forum - Harmony',
            '/taches' => 'Tâches - Harmony',
            '/evenements' => 'Événements - Harmony',
            '/nutrition' => 'Nutrition - Harmony',
            '/meditation' => 'Méditation - Harmony',
            '/journal' => 'Journal - Harmony',
            '/library' => 'Library - Harmony'
        ];

        foreach ($routes as $route => $expectedTitle) {
            $crawler = $client->request('GET', $route);

            $this->assertResponseIsSuccessful(sprintf('Route %s should be successful', $route));
            $this->assertSelectorTextContains('title', $expectedTitle, sprintf('Route %s should have correct title', $route));
            $this->assertSelectorTextContains('.content-card > p', 'Cette page est en cours de développement.', sprintf('Route %s should have development message', $route));
            $this->assertSelectorExists('link[href*="assets/example/styles.css"]', sprintf('Route %s should include styles', $route));
            $this->assertSelectorExists('.back-link', sprintf('Route %s should have back link', $route));
        }
    }

    public function testNavigationLinksFromHomepage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        // Test that all navigation links exist on homepage
        $this->assertSelectorExists('a[href="/activites"]');
        $this->assertSelectorExists('a[href="/forum"]');
        $this->assertSelectorExists('a[href="/taches"]');
        $this->assertSelectorExists('a[href="/evenements"]');
        $this->assertSelectorExists('a[href="/nutrition"]');
        $this->assertSelectorExists('a[href="/meditation"]');
        $this->assertSelectorExists('a[href="/journal"]');
        $this->assertSelectorExists('a[href="/library"]');
    }

    public function testBackLinksWork(): void
    {
        $client = static::createClient();

        // Test back link from one of the module pages
        $crawler = $client->request('GET', '/activites');
        $this->assertResponseIsSuccessful();

        // Click the back link
        $link = $crawler->selectLink('← Retour à l\'accueil')->link();
        $crawler = $client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('title', 'Accueil Étudiant - Harmony');
    }
}
