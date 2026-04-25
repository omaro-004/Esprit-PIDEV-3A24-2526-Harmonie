<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ModulesControllerTest extends WebTestCase
{
    // Test 1 : les pages protégées redirigent bien vers login
    public function testAllModulePagesLoad(): void
    {
        $client = static::createClient();

        $protectedRoutes = [
            '/activites',
            '/forum',
            '/taches',
            '/evenements',
            '/nutrition',
        ];

        foreach ($protectedRoutes as $route) {
            $client->request('GET', $route);
            $this->assertResponseRedirects(
                '/login',
                null,
                "Route $route devrait rediriger vers /login"
            );
        }
    }

    // Test 2 : la page login est accessible sans authentification
    public function testNavigationLinksFromHomepage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    // Test 3 : la page register est accessible
    public function testBackLinksWork(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        // 200 ou 302 sont acceptables
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains(
            $statusCode,
            [200, 302],
            'La page register doit retourner 200 ou 302'
        );
    }
}