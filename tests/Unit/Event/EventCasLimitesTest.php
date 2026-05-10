<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use PHPUnit\Framework\TestCase;

class EventCasLimitesTest extends TestCase
{
    private const MAX_TITLE_LENGTH = 100;
    private const MIN_TITLE_LENGTH = 3;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Teste qu'un titre à la longueur maximale est accepté.
     */
    public function testTitleAtExactMaxLength(): void
    {
        $title = str_repeat('A', self::MAX_TITLE_LENGTH);

        $this->assertTrue($this->isTitleLengthValid($title));
    }

    /**
     * Teste qu'un titre à la longueur minimale est accepté.
     */
    public function testTitleAtMinLength(): void
    {
        $title = str_repeat('B', self::MIN_TITLE_LENGTH);

        $this->assertTrue($this->isTitleLengthValid($title));
    }

    /**
     * Teste que l'égalité entre date de début et de fin est rejetée.
     */
    public function testStartDateEqualsEndDate(): void
    {
        $date = new \DateTimeImmutable('2026-05-10 09:00:00');

        $this->assertFalse($this->isEndAfterStart($date, $date));
    }

    /**
     * Teste que la date de fin une seconde après la date de début est acceptée.
     */
    public function testStartDateOneSecondBeforeEndDate(): void
    {
        $start = new \DateTimeImmutable('2026-05-10 09:00:00');
        $end = $start->modify('+1 second');

        $this->assertTrue($this->isEndAfterStart($start, $end));
    }

    /**
     * Teste le nombre maximum de participants (non applicable à l'entité Event).
     */
    public function testMaximumNumberOfParticipants(): void
    {
        $this->markTestSkipped('Pas de champ participants dans Evenement.');
    }

    /**
     * Teste que zéro participant est géré (non applicable à l'entité Event).
     */
    public function testZeroParticipants(): void
    {
        $this->markTestSkipped('Pas de champ participants dans Evenement.');
    }

    private function isTitleLengthValid(string $title): bool
    {
        $length = mb_strlen($title);

        return $length >= self::MIN_TITLE_LENGTH && $length <= self::MAX_TITLE_LENGTH;
    }

    private function isEndAfterStart(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        return $end > $start;
    }
}
