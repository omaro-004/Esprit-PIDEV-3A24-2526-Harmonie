<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ModulesControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneBy(['userEmail' => 'test@example.com']);

        if (!$testUser instanceof User) {
            $testUser = (new User())
                ->setUserEmail('test@example.com')
                ->setUserNom('Test')
                ->setUserPrenom('User')
                ->setUserPassword('password')
                ->setDateInscription('2026-05-03')
                ->setTypeUtilisateur('ADMIN');
        }

        $this->client->loginUser($testUser);
    }

    public function testAllModulePagesLoad(): void
    {
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
            $crawler = $this->client->request('GET', $route);

            $this->assertResponseIsSuccessful(sprintf('Route %s should be successful', $route));
            $this->assertSelectorTextContains('title', $expectedTitle, sprintf('Route %s should have correct title', $route));
            $this->assertSelectorTextContains('.content-card > p', 'Cette page est en cours de développement.', sprintf('Route %s should have development message', $route));
            $this->assertSelectorExists('link[href*="assets/example/styles.css"]', sprintf('Route %s should include styles', $route));
            $this->assertSelectorExists('.back-link', sprintf('Route %s should have back link', $route));
        }
    }

    public function testNavigationLinksFromHomepage(): void
    {
        $crawler = $this->client->request('GET', '/');

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
        // Test back link from one of the module pages
        $crawler = $this->client->request('GET', '/activites');
        $this->assertResponseIsSuccessful();

        // Click the back link
        $link = $crawler->selectLink('← Retour à l\'accueil')->link();
        $crawler = $this->client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('title', 'Accueil Étudiant - Harmony');
    }
}
