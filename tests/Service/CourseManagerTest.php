<?php

namespace App\Tests\Service;

use App\Service\CourseManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CourseManager.
 *
 * Business rules tested
 * ─────────────────────
 * Rule 1 : Le titre du cours est obligatoire.
 * Rule 2 : La matière (subject) est obligatoire.
 * Rule 3 : Le titre ne peut pas dépasser 255 caractères.
 * Rule 4 : La matière ne peut pas dépasser 100 caractères.
 * Rule 5 : L'extension du fichier uploadé doit être autorisée.
 * Rule 6 : L'extension de l'image de couverture doit être une image autorisée.
 *
 * Run : php bin/phpunit
 */
class CourseManagerTest extends TestCase
{
    private CourseManager $manager;

    // ── Setup ────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->manager = new CourseManager();
    }

    // ════════════════════════════════════════════════════════════════════
    // RULE 1 — Titre obligatoire
    // ════════════════════════════════════════════════════════════════════

    /**
     * Un cours avec un titre et une matière valides doit être accepté.
     */
    public function testValidCourseIsAccepted(): void
    {
        $result = $this->manager->validateCourse('Introduction à Symfony', 'Informatique');

        $this->assertTrue($result);
    }

    /**
     * Un titre vide doit lever une InvalidArgumentException.
     */
    public function testEmptyTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du cours est obligatoire.');

        $this->manager->validateCourse('', 'Informatique');
    }

    /**
     * Un titre composé uniquement d'espaces est considéré comme vide.
     */
    public function testWhitespaceTitleThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du cours est obligatoire.');

        $this->manager->validateCourse('     ', 'Informatique');
    }

    // ════════════════════════════════════════════════════════════════════
    // RULE 2 — Matière obligatoire
    // ════════════════════════════════════════════════════════════════════

    /**
     * Une matière vide doit lever une InvalidArgumentException.
     */
    public function testEmptySubjectThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La matière (subject) est obligatoire.');

        $this->manager->validateCourse('Cours de Mathématiques', '');
    }

    /**
     * Une matière composée uniquement d'espaces est considérée comme vide.
     */
    public function testWhitespaceSubjectThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La matière (subject) est obligatoire.');

        $this->manager->validateCourse('Cours de Mathématiques', '   ');
    }

    // ════════════════════════════════════════════════════════════════════
    // RULE 3 — Titre ≤ 255 caractères
    // ════════════════════════════════════════════════════════════════════

    /**
     * Un titre de exactement 255 caractères doit être accepté.
     */
    public function testTitleAtMaxLengthIsAccepted(): void
    {
        $title  = str_repeat('A', 255);
        $result = $this->manager->validateTitle($title);

        $this->assertTrue($result);
    }

    /**
     * Un titre de 256 caractères doit lever une InvalidArgumentException.
     */
    public function testTitleExceedingMaxLengthThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre ne peut pas dépasser 255 caractères.');

        $this->manager->validateTitle(str_repeat('A', 256));
    }

    // ════════════════════════════════════════════════════════════════════
    // RULE 4 — Matière ≤ 100 caractères
    // ════════════════════════════════════════════════════════════════════

    /**
     * Une matière de exactement 100 caractères doit être acceptée.
     */
    public function testSubjectAtMaxLengthIsAccepted(): void
    {
        $subject = str_repeat('B', 100);
        $result  = $this->manager->validateSubject($subject);

        $this->assertTrue($result);
    }

    /**
     * Une matière de 101 caractères doit lever une InvalidArgumentException.
     */
    public function testSubjectExceedingMaxLengthThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La matière ne peut pas dépasser 100 caractères.');

        $this->manager->validateSubject(str_repeat('B', 101));
    }

    // ════════════════════════════════════════════════════════════════════
    // RULE 5 — Extension de fichier autorisée
    // ════════════════════════════════════════════════════════════════════

    /**
     * Les extensions autorisées (documents et images) doivent retourner true.
     *
     * @dataProvider allowedExtensionProvider
     */
    public function testAllowedFileExtensionReturnsTrue(string $ext): void
    {
        $this->assertTrue($this->manager->isAllowedFileExtension($ext));
    }

    public static function allowedExtensionProvider(): array
    {
        return [
            'PDF'  => ['pdf'],
            'DOCX' => ['docx'],
            'PPTX' => ['pptx'],
            'XLSX' => ['xlsx'],
            'TXT'  => ['txt'],
            'MD'   => ['md'],
            'JPG'  => ['jpg'],
            'PNG'  => ['png'],
            'WEBP' => ['webp'],
        ];
    }

    /**
     * Les extensions non autorisées doivent retourner false.
     *
     * @dataProvider forbiddenExtensionProvider
     */
    public function testForbiddenFileExtensionReturnsFalse(string $ext): void
    {
        $this->assertFalse($this->manager->isAllowedFileExtension($ext));
    }

    public static function forbiddenExtensionProvider(): array
    {
        return [
            'EXE' => ['exe'],
            'PHP' => ['php'],
            'SH'  => ['sh'],
            'ZIP' => ['zip'],
            'JS'  => ['js'],
        ];
    }

    /**
     * La vérification d'extension est insensible à la casse.
     */
    public function testFileExtensionCheckIsCaseInsensitive(): void
    {
        $this->assertTrue($this->manager->isAllowedFileExtension('PDF'));
        $this->assertTrue($this->manager->isAllowedFileExtension('Png'));
        $this->assertTrue($this->manager->isAllowedFileExtension('DOCX'));
    }

    // ════════════════════════════════════════════════════════════════════
    // RULE 6 — Extension de l'image de couverture
    // ════════════════════════════════════════════════════════════════════

    /**
     * Les extensions d'image autorisées pour la couverture doivent retourner true.
     *
     * @dataProvider allowedCoverExtensionProvider
     */
    public function testAllowedCoverExtensionReturnsTrue(string $ext): void
    {
        $this->assertTrue($this->manager->isAllowedCoverExtension($ext));
    }

    public static function allowedCoverExtensionProvider(): array
    {
        return [
            'JPG'  => ['jpg'],
            'JPEG' => ['jpeg'],
            'PNG'  => ['png'],
            'WEBP' => ['webp'],
            'GIF'  => ['gif'],
        ];
    }

    /**
     * Un PDF ou un DOCX ne doit pas être accepté comme image de couverture.
     */
    public function testNonImageExtensionRejectedAsCover(): void
    {
        $this->assertFalse($this->manager->isAllowedCoverExtension('pdf'));
        $this->assertFalse($this->manager->isAllowedCoverExtension('docx'));
        $this->assertFalse($this->manager->isAllowedCoverExtension('exe'));
    }
}
