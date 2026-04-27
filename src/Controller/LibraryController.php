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
        $courses = $this->db->fetchAllAssociative(
            "SELECT c.id, c.title, s.name AS subject_name, c.cover_image_path
             FROM courses c
             LEFT JOIN subject s ON s.id = c.subjectid
             WHERE c.is_published = 1
             ORDER BY c.id DESC"
        );

        $courses = array_map(fn($r) => [
            'id'             => (int) $r['id'],
            'title'          => $r['title'],
            'subjectName'    => $r['subject_name'],
            'coverImagePath' => $r['cover_image_path'],
        ], $courses);

        $recommendedCourses = $this->getRecommendedCourses();

        return $this->render('library/library.html.twig', [
            'courses' => $courses,
            'recommendedCourses' => $recommendedCourses,
        ]);
    }

    private function getRecommendedCourses(): array
    {
        $user = $this->getUser();
        if (!$user) {
            return [];
        }

        $userRow = $this->db->fetchAssociative(
            'SELECT user_id FROM `user` WHERE user_email = ?',
            [$user->getUserIdentifier()]
        );

        if (!$userRow) {
            return [];
        }

        $userId = (int) $userRow['user_id'];

        $savedSubjectIds = [];
        $savedSubjects = $this->db->fetchAllAssociative(
            "SELECT DISTINCT c.subjectid
             FROM saved_courses sc
             JOIN courses c ON c.id = sc.course_id
             WHERE sc.user_id = ? AND c.subjectid IS NOT NULL",
            [$userId]
        );

        foreach ($savedSubjects as $subject) {
            $savedSubjectIds[] = (int) $subject['subjectid'];
        }

        if (empty($savedSubjectIds)) {
            $recommendedCourses = $this->db->fetchAllAssociative(
                "SELECT c.id, c.title, s.name AS subject_name, c.cover_image_path, c.saves
                 FROM courses c
                 LEFT JOIN subject s ON s.id = c.subjectid
                 WHERE c.is_published = 1
                 ORDER BY c.saves DESC
                 LIMIT 10"
            );
        } else {
            $placeholders = str_repeat('?,', count($savedSubjectIds) - 1) . '?';
            $recommendedCourses = $this->db->fetchAllAssociative(
                "SELECT c.id, c.title, s.name AS subject_name, c.cover_image_path, c.saves
                 FROM courses c
                 LEFT JOIN subject s ON s.id = c.subjectid
                 WHERE c.is_published = 1
                   AND c.id NOT IN (SELECT course_id FROM saved_courses WHERE user_id = ?)
                   AND c.subjectid IN ($placeholders)
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
