<?php

namespace App\Tests\Service;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    private function createValidUser(): User
    {
        $user = new User();
        $user->setUserNom('Dupont');
        $user->setUserPrenom('Jean');
        $user->setUserEmail('jean.dupont@email.com');
        $user->setUserPassword('HashedPassword123!');
        $user->setUserDateDeNaissance('2000-05-15');
        $user->setTypeUtilisateur('ETUDIANT');
        $user->setIsActive(true);
        return $user;
    }

    /** Test 1: Email valide */
    public function testEmailValide(): void
    {
        $user = $this->createValidUser();
        $this->assertNotEmpty($user->getUserEmail());
        $this->assertStringContainsString('@', $user->getUserEmail());
    }

    /** Test 2: Nom et Prenom non vides */
    public function testNomPrenomNonVides(): void
    {
        $user = $this->createValidUser();
        $this->assertNotEmpty($user->getUserNom());
        $this->assertNotEmpty($user->getUserPrenom());
    }

    /** Test 3: Date de naissance valide (format YYYY-MM-DD) */
    public function testDateNaissanceFormatValide(): void
    {
        $user = $this->createValidUser();
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}$/',
            $user->getUserDateDeNaissance()
        );
    }

    /** Test 4: Type utilisateur valide */
    public function testTypeUtilisateurValide(): void
    {
        $user = $this->createValidUser();
        $this->assertContains(
            $user->getTypeUtilisateur(),
            ['ETUDIANT', 'ADMIN']
        );
    }

    /** Test 5: Utilisateur actif par defaut */
    public function testUtilisateurActifParDefaut(): void
    {
        $user = $this->createValidUser();
        $this->assertTrue($user->isActive());
    }

    /** Test 6: Roles retournes correctement */
    public function testRolesRetournes(): void
    {
        $user = $this->createValidUser();
        $this->assertContains('ROLE_USER', $user->getRoles());
    }
}

