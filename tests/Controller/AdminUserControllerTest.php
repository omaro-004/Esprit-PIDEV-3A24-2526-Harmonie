<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminUserControllerTest extends WebTestCase
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

    private function createPersistedUser(
        EntityManagerInterface $em,
        string $type,
        string $email,
        string $nom = 'Dupont',
        string $prenom = 'Jean'
    ): User
    {
        $user = new User();
        $user->setUserNom($nom);
        $user->setUserPrenom($prenom);
        $user->setUserEmail($email);
        $user->setUserPassword('hashed');
        $user->setUserDateDeNaissance('2000-01-01');
        $user->setDateInscription('2024-01-01');
        $user->setTypeUtilisateur($type);
        $user->setIsActive(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testAnonymousRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users');
        $this->assertResponseRedirects('/login');
    }

    public function testAnonymousSearchRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users/search');
        $this->assertResponseRedirects('/login');
    }

    public function testNonAdminForbidden(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em, 'ETUDIANT', 'student@example.com');
        $client->loginUser($user);
        $client->request('GET', '/admin/users');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessIndex(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $admin = $this->createPersistedUser($em, 'ADMIN', 'admin@example.com');
        $client->loginUser($admin);
        $client->request('GET', '/admin/users');
        $this->assertResponseIsSuccessful();
    }

    public function testAdminSuspendedPageLoads(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $admin = $this->createPersistedUser($em, 'ADMIN', 'admin@example.com');
        $client->loginUser($admin);
        $client->request('GET', '/admin/users/suspended');
        $this->assertResponseIsSuccessful();
    }

    public function testAdminShowPageLoads(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $admin = $this->createPersistedUser($em, 'ADMIN', 'admin@example.com');
        $student = $this->createPersistedUser($em, 'ETUDIANT', 'student@example.com');
        $client->loginUser($admin);
        $client->request('GET', '/admin/users/' . $student->getUserId());
        $this->assertResponseIsSuccessful();
    }

    public function testAdminSearchReturnsJson(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $admin = $this->createPersistedUser($em, 'ADMIN', 'admin@example.com');
        $this->createPersistedUser($em, 'ETUDIANT', 'student@example.com');
        $client->loginUser($admin);
        $client->request('GET', '/admin/users/search');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testAdminSuspicionDetailReturnsJson(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $admin = $this->createPersistedUser($em, 'ADMIN', 'admin@example.com');
        $student = $this->createPersistedUser($em, 'ETUDIANT', 'student@example.com');
        $client->loginUser($admin);
        $client->request('GET', '/admin/users/' . $student->getUserId() . '/suspicion');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }
}
