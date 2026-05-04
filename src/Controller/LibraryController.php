<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LibraryController extends AbstractController
{
    public function __construct(private readonly Connection $db) {}

    #[Route('/library', name: 'library')]
    public function index(): Response
    {
        // Only courses marked is_published = 1
        $courses = $this->db->fetchAllAssociative(
            "SELECT c.id, c.title, s.name AS subject_name, c.cover_image_path
             FROM courses c
             LEFT JOIN subject s ON s.id = c.subjectid_id
             WHERE c.is_published = 1
             ORDER BY c.id DESC"
        );

        $courses = array_map(fn($r) => [
            'id'             => (int) $r['id'],
            'title'          => $r['title'],
            'subjectName'    => $r['subject_name'],
            'coverImagePath' => $r['cover_image_path'],
        ], $courses);

        // Get recommended courses based on user's course subjects
        $recommendedCourses = $this->getRecommendedCourses();

        return $this->render('library/library.html.twig', [
            'courses' => $courses,
            'recommendedCourses' => $recommendedCourses,
        ]);
    }

    /**
     * @return array<mixed>
     */
    private function getRecommendedCourses(): array
    {
        $user = $this->getUser();
        if (!$user) {
            return [];
        }

        // Get user ID from email
        $userRow = $this->db->fetchAssociative(
            'SELECT user_id FROM `user` WHERE user_email = ?',
            [$user->getUserIdentifier()]
        );

        if (!$userRow) {
            return [];
        }

        $userId = (int) $userRow['user_id'];

        // First, collect subject IDs from courses the user has saved.
        // If none exist yet (new user), we fall back to showing the most popular courses overall.
        $savedSubjectIds = [];
        $savedSubjects = $this->db->fetchAllAssociative(
            "SELECT DISTINCT c.subjectid_id
             FROM saved_courses sc
             JOIN courses c ON c.id = sc.course_id
             WHERE sc.user_id = ? AND c.subjectid_id IS NOT NULL",
            [$userId]
        );

        foreach ($savedSubjects as $subject) {
            $savedSubjectIds[] = (int) $subject['subjectid_id'];
        }

        // Build the main query: subject-matched if we have signals, otherwise top popular
        if (empty($savedSubjectIds)) {
            // No save history — recommend the most popular published courses
            $recommendedCourses = $this->db->fetchAllAssociative(
                "SELECT c.id, c.title, s.name AS subject_name, c.cover_image_path, c.saves
                 FROM courses c
                 LEFT JOIN subject s ON s.id = c.subjectid_id
                 WHERE c.is_published = 1
                 ORDER BY c.saves DESC
                 LIMIT 10"
            );
        } else {
            // Has save history — recommend by matching subjects, excluding already-saved
            $placeholders = str_repeat('?,', count($savedSubjectIds) - 1) . '?';
            $recommendedCourses = $this->db->fetchAllAssociative(
                "SELECT c.id, c.title, s.name AS subject_name, c.cover_image_path, c.saves
                 FROM courses c
                 LEFT JOIN subject s ON s.id = c.subjectid_id
                 WHERE c.is_published = 1
                   AND c.id NOT IN (SELECT course_id FROM saved_courses WHERE user_id = ?)
                   AND c.subjectid_id IN ($placeholders)
                 ORDER BY c.saves DESC
                 LIMIT 10",
                array_merge([$userId], $savedSubjectIds)
            );
        }

        return array_map(fn($r) => [
            'id'             => (int) $r['id'],
            'title'          => $r['title'],
            'subjectName'    => $r['subject_name'],
            'coverImagePath' => $r['cover_image_path'],
            'saves'          => (int) $r['saves'],
        ], $recommendedCourses);
    }
}
