<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class RegistrationControllerTest extends WebTestCase
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

    public function testRegisterPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
    }

    public function testRegisterStep2RedirectsWithoutSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register/step2');
        $this->assertResponseRedirects('/register');
    }

    public function testRegisterStep3RedirectsWithoutSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register/step3');
        $this->assertResponseRedirects('/register');
    }

    public function testRegisterSaveFaceWithoutSessionReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register/save-face', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegisterRedirectsWhenLoggedIn(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/register');

        $router = static::getContainer()->get('router');
        $this->assertResponseRedirects($router->generate('homepage'));
    }

    public function testRegisterStep2RedirectsWhenLoggedIn(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/register/step2');

        $router = static::getContainer()->get('router');
        $this->assertResponseRedirects($router->generate('homepage'));
    }

    public function testRegisterStep3RedirectsWhenLoggedIn(): void
    {
        $client = static::createClient();
        $em = $this->initDatabase();
        $user = $this->createPersistedUser($em);
        $client->loginUser($user);
        $client->request('GET', '/register/step3');

        $router = static::getContainer()->get('router');
        $this->assertResponseRedirects($router->generate('homepage'));
    }

    public function testRegisterStep2ShowsFormWithSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');
        $session = $client->getRequest()->getSession();
        $session->set('reg_step1', [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@example.com',
            'password' => 'hashed',
            'dateNaissance' => '2000-01-01',
        ]);
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $client->request('GET', '/register/step2');
        $this->assertResponseIsSuccessful();
    }
}
