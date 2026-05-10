<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function createUser(): User
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
        return $user;
    }

    public function testRolesForAdminAndStudent(): void
    {
        $user = $this->createUser();
        $this->assertSame(['ROLE_USER'], $user->getRoles());

        $user->setTypeUtilisateur('ADMIN');
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testDisplayAvatarPrefersUserImagePath(): void
    {
        $user = $this->createUser();
        $user->setOauthAvatarUrl('http://example.com/oauth.png');
        $this->assertSame('http://example.com/oauth.png', $user->getDisplayAvatar());

        $user->setUserImagePath('user_images/profile.png');
        $this->assertSame('user_images/profile.png', $user->getDisplayAvatar());
    }

    public function testIsOAuthUser(): void
    {
        $user = $this->createUser();
        $this->assertFalse($user->isOAuthUser());

        $user->setGoogleId('google-123');
        $this->assertTrue($user->isOAuthUser());
    }

    public function testUserIdentifierIsEmail(): void
    {
        $user = $this->createUser();
        $this->assertSame('jean.dupont@example.com', $user->getUserIdentifier());
    }

    public function testIsActiveToggle(): void
    {
        $user = $this->createUser();
        $this->assertTrue($user->isActive());

        $user->setIsActive(false);
        $this->assertFalse($user->isActive());
    }

    public function testFaceIdDefaultsToFalse(): void
    {
        $user = $this->createUser();
        $this->assertFalse($user->isFaceIdEnabled());
    }

    public function testOAuthUserWhenFacebookIdSet(): void
    {
        $user = $this->createUser();
        $user->setFacebookId('fb-123');
        $this->assertTrue($user->isOAuthUser());
    }

    public function testImagePathCanBeUpdated(): void
    {
        $user = $this->createUser();
        $this->assertNull($user->getUserImagePath());

        $user->setUserImagePath('user_images/avatar.png');
        $this->assertSame('user_images/avatar.png', $user->getUserImagePath());
    }
}
