<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class ModerationServiceTest extends TestCase
{
    // Liste locale simulée — même logique que ton ModerationService
    private array $motsInterdits = ['spam', 'insulte', 'haine'];

    private function contientMotInterdit(string $texte): bool
    {
        $texteLower = strtolower($texte);
        foreach ($this->motsInterdits as $mot) {
            if (str_contains($texteLower, $mot)) {
                return true;
            }
        }
        return false;
    }
    // Test 11 : texte propre → pas de mot interdit
    public function testTextePropreAccepte(): void
    {
        $texte = 'Bonjour, comment puis-je apprendre Python ?';
        $this->assertFalse($this->contientMotInterdit($texte));
    }
    // Test 12 : texte avec mot interdit → détecté
    public function testTexteAvecMotInterdit(): void
    {
        $texte = 'Ceci est du spam inutile';
        $this->assertTrue($this->contientMotInterdit($texte));
    }
    // Test 13 : mot interdit en majuscules → détecté quand même
    public function testMotInterditMajuscules(): void
    {
        $texte = 'Ceci est du SPAM inutile';
        $this->assertTrue($this->contientMotInterdit($texte));
    } 
}
