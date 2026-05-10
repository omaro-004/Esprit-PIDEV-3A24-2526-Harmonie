<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
{
    private function initDatabase(): EntityManagerInterface
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $classes = [$em->getClassMetadata(User::class)];
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
        return $em;
    }

    private function createPersistedUser(EntityManagerInterface $em): User
    {
        $user = new User();
        $user->setUserNom('Dupont');
        $user->setUserPrenom('Jean');
        $user->setUserEmail('jean.dupont@example.com');
        $user->setUserPassword('hashed');
        $user->setUserDateDeNaissance('2000-01-01');
        $user->setDateInscription('2024-01-01');
        $user->setTypeUtilisateur('ETUDIANT');
        $user->setIsActive(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testAnonymousRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/settings');
        $this->assertResponseRedirects('/login');

        $client->request('GET', '/profile/security');
        $this->assertResponseRedirects('/login');
    }

    public function testAnonymousProfileRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');
        $this->assertResponseRedirects('/login');
    }

    public function testAnonymousProfileEditRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/edit');
        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedCanAccessSettings(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/profile/settings');
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedCanAccessSecurity(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/profile/security');
        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedProfileRedirectsToSettings(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/profile');
        $this->assertResponseRedirects('/profile/settings');
    }

    public function testAuthenticatedEditRedirectsToSettings(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/profile/edit');
        $this->assertResponseRedirects('/profile/settings');
    }

    public function testAuthenticatedCanAccessTwoFactorPage(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/profile/2fa');
        $this->assertResponseIsSuccessful();
    }
}
