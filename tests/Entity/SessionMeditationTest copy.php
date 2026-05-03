<?php

namespace App\Tests\Entity;

use App\Entity\Conseil;
use App\Entity\SessionMeditation;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class SessionMeditationTest extends TestCase
{
    private SessionMeditation $session;

    protected function setUp(): void
    {
        $this->session = new SessionMeditation();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->session->getId());
        $this->assertNull($this->session->getUser());
        $this->assertSame('', $this->session->getAuteur());
        $this->assertNull($this->session->getDuree());
        $this->assertSame('', $this->session->getTheme());
        $this->assertNull($this->session->getAudioUrl());
        $this->assertCount(0, $this->session->getConseils());
    }

    public function testSetAndGetAuteur(): void
    {
        $this->session->setAuteur('Jean Dupont');
        $this->assertSame('Jean Dupont', $this->session->getAuteur());
    }

    public function testSetAuteurNullFallsBackToEmptyString(): void
    {
        $this->session->setAuteur(null);
        $this->assertSame('', $this->session->getAuteur());
    }

    public function testSetAndGetTheme(): void
    {
        $this->session->setTheme('Relaxation');
        $this->assertSame('Relaxation', $this->session->getTheme());
    }

    public function testSetThemeNullFallsBackToEmptyString(): void
    {
        $this->session->setTheme(null);
        $this->assertSame('', $this->session->getTheme());
    }

    public function testSetAndGetDuree(): void
    {
        $this->session->setDuree(20);
        $this->assertSame(20, $this->session->getDuree());
    }

    public function testSetDureeNull(): void
    {
        $this->session->setDuree(null);
        $this->assertNull($this->session->getDuree());
    }

    public function testSetAndGetAudioUrl(): void
    {
        $this->session->setAudioUrl('https://youtube.com/watch?v=abc');
        $this->assertSame('https://youtube.com/watch?v=abc', $this->session->getAudioUrl());
    }

    public function testSetAudioUrlEmptyStringBecomesNull(): void
    {
        $this->session->setAudioUrl('');
        $this->assertNull($this->session->getAudioUrl());
    }

    public function testSetAudioUrlNullStaysNull(): void
    {
        $this->session->setAudioUrl(null);
        $this->assertNull($this->session->getAudioUrl());
    }

    public function testSetAndGetUser(): void
    {
        $user = new User();
        $this->session->setUser($user);
        $this->assertSame($user, $this->session->getUser());
    }

    public function testSetUserNull(): void
    {
        $user = new User();
        $this->session->setUser($user);
        $this->session->setUser(null);
        $this->assertNull($this->session->getUser());
    }

    public function testAddConseil(): void
    {
        $conseil = new Conseil();
        $this->session->addConseil($conseil);

        $this->assertCount(1, $this->session->getConseils());
        $this->assertSame($this->session, $conseil->getSession());
    }

    public function testAddConseilDoesNotDuplicate(): void
    {
        $conseil = new Conseil();
        $this->session->addConseil($conseil);
        $this->session->addConseil($conseil); // add same twice

        $this->assertCount(1, $this->session->getConseils());
    }

    public function testRemoveConseil(): void
    {
        $conseil = new Conseil();
        $this->session->addConseil($conseil);
        $this->session->removeConseil($conseil);

        $this->assertCount(0, $this->session->getConseils());
        $this->assertNull($conseil->getSession());
    }

    public function testFluentInterface(): void
    {
        $result = $this->session
            ->setAuteur('Test')
            ->setTheme('Sommeil')
            ->setDuree(15)
            ->setAudioUrl('https://youtube.com/watch?v=xyz');

        $this->assertInstanceOf(SessionMeditation::class, $result);
    }
}
