<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TestControllerTest extends WebTestCase
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

    public function testIndex(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }
}
