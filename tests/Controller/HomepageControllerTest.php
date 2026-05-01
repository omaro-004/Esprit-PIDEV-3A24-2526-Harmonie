<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomepageControllerTest extends WebTestCase
{
    public function testHomepageLoads(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('title', 'Accueil Étudiant - Harmony');
        $this->assertSelectorTextContains('h1', 'Bienvenue sur Harmony');
        $this->assertSelectorTextContains('p', 'Votre plateforme étudiante moderne');
        $this->assertSelectorTextContains('.logo-text', 'Harmony');
        $this->assertSelectorTextContains('.nav-button.active', 'Activités');
        $this->assertSelectorExists('link[href*="assets/example/styles.css"]');
        $this->assertSelectorExists('.cta-button');
    }
}
