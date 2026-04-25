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

}
