<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomepageControllerTest extends WebTestCase
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

    public function testHomepageLoads(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('title', 'Harmony');
        $this->assertSelectorTextContains('h1', 'Bienvenue surHarmony');
        $this->assertSelectorTextContains('p', 'Votre espace étudiant centralisé');
    }
}
