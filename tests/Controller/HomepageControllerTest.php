<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomepageControllerTest extends WebTestCase
{
    public function testHomepageRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        // La page redirige vers login car l'utilisateur n'est pas connecté
        // C'est le comportement CORRECT — on teste que la redirection fonctionne
        $this->assertResponseRedirects('/login');
    }
}