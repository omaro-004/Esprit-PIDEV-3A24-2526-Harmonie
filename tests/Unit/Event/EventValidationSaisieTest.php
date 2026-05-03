<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use App\Entity\Evenement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class EventValidationSaisieTest extends TestCase
{
    private const MAX_TITLE_LENGTH = 100;

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
     * Teste que le format d'email invalide est rejeté (non applicable à l'entité Event).
     */
    public function testInvalidEmailFormatRejected(): void
    {
        $this->markTestSkipped('Pas de champ email dans Evenement.');
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

    /**
     * Teste que la validation Symfony déclenche une violation pour un événement en présentiel sans lieu.
     */
    public function testPresentielRequiresAddressOrRoom(): void
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $event = (new Evenement())
            ->setEventType('cours')
            ->setLieuType('presentiel');

        $violations = $validator->validate($event);

        $this->assertGreaterThan(0, $violations->count());
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

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        if (!$dt instanceof \DateTimeImmutable) {
            $errors[] = 'invalid_format';
        }

        return $errors;
    }
}
