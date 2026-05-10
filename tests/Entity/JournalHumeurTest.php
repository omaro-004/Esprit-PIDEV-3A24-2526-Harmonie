<?php

namespace App\Tests\Entity;

use App\Entity\JournalHumeur;
use App\Entity\User;
use App\Enum\Humeur;
use PHPUnit\Framework\TestCase;

class JournalHumeurTest extends TestCase
{
    private JournalHumeur $journal;

    protected function setUp(): void
    {
        $this->journal = new JournalHumeur();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->journal->getId());
        $this->assertNull($this->journal->getUser());
        $this->assertNotNull($this->journal->getDateJournal()); // set in constructor
        $this->assertNull($this->journal->getHumeur());
        $this->assertSame(3, $this->journal->getScore()); // default score
        $this->assertSame('', $this->journal->getContenu());
        $this->assertNull($this->journal->getAvatarImageUrl());
        $this->assertFalse($this->journal->isReadByAdmin());
        $this->assertNotNull($this->journal->getCreatedAt());
    }

    public function testConstructorSetsDateJournalToToday(): void
    {
        $today = new \DateTime();
        $date  = $this->journal->getDateJournal();

        $this->assertInstanceOf(\DateTimeInterface::class, $date);
        $this->assertSame($today->format('Y-m-d'), $date->format('Y-m-d'));
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->journal->getCreatedAt());
    }

    public function testSetAndGetUser(): void
    {
        $user = new User();
        $this->journal->setUser($user);
        $this->assertSame($user, $this->journal->getUser());
    }

    public function testSetUserNull(): void
    {
        $this->journal->setUser(null);
        $this->assertNull($this->journal->getUser());
    }

    public function testSetAndGetDateJournal(): void
    {
        $date = new \DateTime('2025-01-15');
        $this->journal->setDateJournal($date);
        $this->assertSame($date, $this->journal->getDateJournal());
    }

    public function testSetDateJournalNull(): void
    {
        $this->journal->setDateJournal(null);
        $this->assertNull($this->journal->getDateJournal());
    }

    public function testSetHumeurUpdateScore(): void
    {
        $this->journal->setHumeur(Humeur::TRES_BIEN);
        $this->assertSame(Humeur::TRES_BIEN, $this->journal->getHumeur());
        $this->assertSame(5, $this->journal->getScore());
    }

    public function testSetHumeurBienScore(): void
    {
        $this->journal->setHumeur(Humeur::BIEN);
        $this->assertSame(4, $this->journal->getScore());
    }

    public function testSetHumeurNeutreScore(): void
    {
        $this->journal->setHumeur(Humeur::NEUTRE);
        $this->assertSame(3, $this->journal->getScore());
    }

    public function testSetHumeurMalScore(): void
    {
        $this->journal->setHumeur(Humeur::MAL);
        $this->assertSame(2, $this->journal->getScore());
    }

    public function testSetHumeurTresMalScore(): void
    {
        $this->journal->setHumeur(Humeur::TRES_MAL);
        $this->assertSame(1, $this->journal->getScore());
    }

    public function testSetHumeurNullDoesNotChangeScore(): void
    {
        $this->journal->setHumeur(Humeur::BIEN); // score = 4
        $this->journal->setHumeur(null);
        $this->assertNull($this->journal->getHumeur());
        $this->assertSame(4, $this->journal->getScore()); // score unchanged
    }

    public function testSetAndGetContenu(): void
    {
        $this->journal->setContenu('Bonne journée aujourd\'hui.');
        $this->assertSame('Bonne journée aujourd\'hui.', $this->journal->getContenu());
    }

    public function testSetContenuNullFallsBackToEmptyString(): void
    {
        $this->journal->setContenu(null);
        $this->assertSame('', $this->journal->getContenu());
    }

    public function testSetAndGetAvatarImageUrl(): void
    {
        $this->journal->setAvatarImageUrl('https://example.com/avatar.png');
        $this->assertSame('https://example.com/avatar.png', $this->journal->getAvatarImageUrl());
    }

    public function testSetAvatarImageUrlNull(): void
    {
        $this->journal->setAvatarImageUrl(null);
        $this->assertNull($this->journal->getAvatarImageUrl());
    }

    public function testSetIsReadByAdmin(): void
    {
        $this->journal->setIsReadByAdmin(true);
        $this->assertTrue($this->journal->isReadByAdmin());

        $this->journal->setIsReadByAdmin(false);
        $this->assertFalse($this->journal->isReadByAdmin());
    }

    public function testFluentInterface(): void
    {
        $result = $this->journal
            ->setContenu('Test')
            ->setHumeur(Humeur::BIEN)
            ->setIsReadByAdmin(true);

        $this->assertInstanceOf(JournalHumeur::class, $result);
    }
}
