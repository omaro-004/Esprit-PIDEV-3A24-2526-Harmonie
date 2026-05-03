<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\SuspicionScoreService;
use PHPUnit\Framework\TestCase;

class SuspicionScoreServiceTest extends TestCase
{
    private function createUser(string $nom, string $prenom, string $email): User
    {
        $user = new User();
        $user->setUserNom($nom);
        $user->setUserPrenom($prenom);
        $user->setUserEmail($email);
        $user->setUserPassword('hashed');
        $user->setUserDateDeNaissance('2000-01-01');
        $user->setDateInscription('2024-01-01');
        $user->setTypeUtilisateur('ETUDIANT');
        $user->setIsActive(true);
        return $user;
    }

    public function testComputeScoreAndLabelForSuspiciousUser(): void
    {
        $service = new SuspicionScoreService();
        $user = $this->createUser('test', 'test', 'test@yopmail.com');

        $score = $service->compute($user);
        $this->assertGreaterThanOrEqual(60, $score);
        $this->assertSame('Très suspect', $service->getLabel($score));
    }

    public function testComputeScoreIsCappedAt100(): void
    {
        $service = new SuspicionScoreService();
        $user = $this->createUser('aaatest', 'aaa', 'test@spam.la');

        $score = $service->compute($user);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testSortBySuspicion(): void
    {
        $service = new SuspicionScoreService();
        $normal = $this->createUser('Dupont', 'Jean', 'jean.dupont@example.com');
        $suspicious = $this->createUser('test', 'test', 'test@yopmail.com');

        $sorted = $service->sortBySuspicion([$normal, $suspicious]);
        $this->assertSame($suspicious, $sorted[0]);
    }

    public function testNormalUserScoreIsLow(): void
    {
        $service = new SuspicionScoreService();
        $user = $this->createUser('Dupont', 'Jean', 'jean.dupont@example.com');
        $score = $service->compute($user);
        $this->assertLessThan(35, $score);
        $this->assertSame('Modéré', $service->getLabel($score));
    }

    public function testLabelThresholds(): void
    {
        $service = new SuspicionScoreService();
        $this->assertSame('Normal', $service->getLabel(0));
        $this->assertSame('Modéré', $service->getLabel(15));
        $this->assertSame('Suspect', $service->getLabel(35));
        $this->assertSame('Très suspect', $service->getLabel(60));
    }

    public function testColorThresholds(): void
    {
        $service = new SuspicionScoreService();
        $this->assertSame('#10B981', $service->getColor(0));
        $this->assertSame('#6A5ACD', $service->getColor(15));
        $this->assertSame('#E5A44B', $service->getColor(35));
        $this->assertSame('#E05252', $service->getColor(60));
    }

    public function testBorderColorThresholds(): void
    {
        $service = new SuspicionScoreService();
        $this->assertSame('#6EE7B7', $service->getBorderColor(0));
        $this->assertSame('#C4B5FD', $service->getBorderColor(15));
        $this->assertSame('#FCD34D', $service->getBorderColor(35));
        $this->assertSame('#FCA5A5', $service->getBorderColor(60));
    }

    public function testBreakdownContainsExpectedKeys(): void
    {
        $service = new SuspicionScoreService();
        $user = $this->createUser('Dupont', 'Jean', 'jean.dupont@example.com');
        $breakdown = $service->getBreakdown($user);

        $this->assertNotEmpty($breakdown);
        $this->assertArrayHasKey('critere', $breakdown[0]);
        $this->assertArrayHasKey('points', $breakdown[0]);
        $this->assertArrayHasKey('detail', $breakdown[0]);
        $this->assertArrayHasKey('flag', $breakdown[0]);
    }
}
