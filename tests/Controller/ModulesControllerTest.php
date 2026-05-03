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
                ->setTypeUtilisateur('ETUDIANT');
            $em = static::getContainer()->get('doctrine.orm.entity_manager');
            $em->persist($testUser);
            $em->flush();
        }

        $this->client->loginUser($testUser);
    }

    public function testAllModulePagesLoad(): void
    {
        // Test all module routes
        $routes = [
            '/activites' => 'Activités',
            '/forum' => 'Forum',
            '/tache/index' => 'Tâches',
            '/evenement/index' => 'Événements',
            '/nutrition' => 'Nutrition',
            '/meditation' => 'Méditation',
            '/journal' => 'Journal',
            '/library' => 'Library'
        ];

        foreach ($routes as $route => $expectedTitle) {
            $crawler = $this->client->request('GET', $route);

            $this->assertResponseIsSuccessful(sprintf('Route %s should be successful', $route));
            $this->assertSelectorTextContains('title', $expectedTitle, sprintf('Route %s should have correct title', $route));
        }
    }

    public function testNavigationLinksFromHomepage(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        // Test that all navigation links exist on homepage
        $this->assertSelectorExists('a[href="/activites"]');
        $this->assertSelectorExists('a[href="/forum"]');
        $this->assertSelectorExists('a[href="/tache/index"]');
        $this->assertSelectorExists('a[href="/evenement/index"]');
        $this->assertSelectorExists('a[href="/nutrition"]');
        $this->assertSelectorExists('a[href="/meditation"]');
        $this->assertSelectorExists('a[href="/journal"]');
        $this->assertSelectorExists('a[href="/library"]');
    }


}
