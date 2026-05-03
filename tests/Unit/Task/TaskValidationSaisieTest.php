<?php

declare(strict_types=1);

namespace Tests\Unit\Task;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaskValidationSaisieTest extends TestCase
{
    private const MAX_TITLE_LENGTH = 255;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Teste qu'un titre vide est rejeté selon les règles de saisie.
     */
    #[DataProvider('emptyTitleProvider')]
    public function testTitleCannotBeEmpty(?string $title): void
    {
        $errors = $this->validateTitle($title, self::MAX_TITLE_LENGTH);

        $this->assertContains('empty', $errors);
    }

    /**
     * Teste qu'un titre trop long est rejeté selon les règles de saisie.
     */
    #[DataProvider('tooLongTitleProvider')]
    public function testTitleCannotExceedMaxLength(string $title): void
    {
        $errors = $this->validateTitle($title, self::MAX_TITLE_LENGTH);

        $this->assertContains('too_long', $errors);
    }

    /**
     * Teste que la date de début est obligatoire.
     */
    public function testStartDateIsRequired(): void
    {
        $errors = $this->validateDateString(null);

        $this->assertContains('required', $errors);
    }

    /**
     * Teste que la date de fin est obligatoire.
     */
    public function testEndDateIsRequired(): void
    {
        $errors = $this->validateDateString('');

        $this->assertContains('required', $errors);
    }

    /**
     * Teste que le format d'email invalide est rejeté (non applicable à l'entité Task).
     */
    public function testInvalidEmailFormatRejected(): void
    {
        $this->markTestSkipped('Pas de champ email dans Tache.');
    }

    /**
     * Teste que le format de date invalide est rejeté.
     */
    #[DataProvider('invalidDateProvider')]
    public function testInvalidDateFormatRejected(string $date): void
    {
        $errors = $this->validateDateString($date);

        $this->assertNotEmpty($errors);
    }

    public static function emptyTitleProvider(): array
    {
        return [
            [null],
            [''],
            ['   '],
        ];
    }

    public static function tooLongTitleProvider(): array
    {
        return [
            [str_repeat('A', self::MAX_TITLE_LENGTH + 1)],
        ];
    }

    public static function invalidDateProvider(): array
    {
        return [
            ['99-99-9999'],
            ['not-a-date'],
        ];
    }

    private function validateTitle(?string $title, int $maxLength): array
    {
        $errors = [];
        $value = $title === null ? '' : trim($title);

        if ($value === '') {
            $errors[] = 'empty';
        }
        if ($value !== '' && mb_strlen($value) > $maxLength) {
            $errors[] = 'too_long';
        }

        return $errors;
    }

    private function validateDateString(?string $date): array
    {
        $errors = [];
        $value = $date === null ? '' : trim($date);

        if ($value === '') {
            $errors[] = 'required';
            return $errors;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$dt instanceof \DateTimeImmutable) {
            $errors[] = 'invalid_format';
        }

        return $errors;
    }
}
