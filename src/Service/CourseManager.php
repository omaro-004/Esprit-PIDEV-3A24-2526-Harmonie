<?php

namespace App\Service;

/**
 * CourseManager — business-rule validation for the Course entity.
 *
 * Rules mirror the guards already present in CoursesController:
 *  1. Title must not be empty.
 *  2. Subject name must not be empty.
 *  3. Title must not exceed 255 characters.
 *  4. Subject name must not exceed 100 characters.
 *  5. Uploaded file extension must belong to the allowed list.
 *  6. Cover image (when provided) must be an allowed image type.
 */
class CourseManager
{
    // Keep in sync with CoursesController::ALLOWED_EXTENSIONS
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
        'txt', 'md', 'rtfx',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
    ];

    private const ALLOWED_COVER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const MAX_TITLE_LENGTH   = 255;
    private const MAX_SUBJECT_LENGTH = 100;

    /**
     * Validates all course creation / update data in one call.
     *
     * @param string $title
     * @param string $subject
     *
     * @throws \InvalidArgumentException on the first rule violation found.
     * @return true
     */
    public function validateCourse(string $title, string $subject): bool
    {
        $this->validateTitle($title);
        $this->validateSubject($subject);

        return true;
    }

    /**
     * Rule 1 & 3 — title must be non-empty and ≤ 255 chars.
     */
    public function validateTitle(string $title): bool
    {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Le titre du cours est obligatoire.');
        }

        if (strlen(trim($title)) > self::MAX_TITLE_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Le titre ne peut pas dépasser %d caractères.', self::MAX_TITLE_LENGTH)
            );
        }

        return true;
    }

    /**
     * Rule 2 & 4 — subject must be non-empty and ≤ 100 chars.
     */
    public function validateSubject(string $subject): bool
    {
        if (trim($subject) === '') {
            throw new \InvalidArgumentException('La matière (subject) est obligatoire.');
        }

        if (strlen(trim($subject)) > self::MAX_SUBJECT_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('La matière ne peut pas dépasser %d caractères.', self::MAX_SUBJECT_LENGTH)
            );
        }

        return true;
    }

    /**
     * Rule 5 — file extension must be in the allowed list.
     */
    public function isAllowedFileExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Rule 6 — cover image extension must be an image type.
     */
    public function isAllowedCoverExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_COVER_EXTENSIONS, true);
    }
}
