<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;

#[Route('/admin/courses')]
class AdminCoursesController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('', name: 'admin_courses_index', methods: ['GET'])]
    public function index(Request $req): Response
    {
        $search = trim((string) $req->query->get('q', ''));

        $where  = ['c.is_published = 1'];
        $params = [];

        if ($search !== '') {
            $where[]  = '(c.title LIKE ? OR u.user_email LIKE ? OR u.user_nom LIKE ? OR u.user_prenom LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $where);

        $sql = 'SELECT'
            . ' c.id, c.title, c.created_at, c.is_published, c.admin_locked, c.saves, c.cover_image_path,'
            . ' u.user_id AS owner_id,'
            . ' u.user_email AS owner_email,'
            . ' CONCAT(u.user_prenom, \' \', u.user_nom) AS owner_name,'
            . ' (SELECT COUNT(*) FROM course_reports cr WHERE cr.course_id = c.id) AS report_count,'
            . ' (SELECT COUNT(*) FROM coursefile cf WHERE cf.courseid_id = c.id) AS file_count'
            . ' FROM courses c'
            . ' LEFT JOIN user u ON u.user_id = c.userid_id'
            . ' WHERE ' . $whereClause
            . ' ORDER BY c.created_at DESC';

        $courses = $this->db->fetchAllAssociative($sql, $params);

        $stats = $this->db->fetchAssociative(
            'SELECT SUM(is_published = 1) AS published, SUM(is_published = 0) AS unpublished, SUM(admin_locked = 1) AS locked, COUNT(*) AS total FROM courses'
        );

        return $this->render('admin/library/courses.html.twig', [
            'courses' => $courses,
            'stats'   => $stats,
            'search'  => $search,
        ]);
    }

    #[Route('/unpublish/{courseId}', name: 'admin_courses_unpublish', requirements: ['courseId' => '\d+'], methods: ['POST'])]
    public function unpublish(int $courseId): JsonResponse
    {
        $affected = $this->db->executeStatement(
            'UPDATE courses SET is_published = 0, admin_locked = 1 WHERE id = ?',
            [$courseId]
        );

        if ($affected === 0) {
            return $this->json(['message' => 'Course not found.'], 404);
        }

        $this->addFlash('success', 'Cours dépublié et verrouillé.');
        return $this->json(['ok' => true]);
    }

    #[Route('/view/{courseId}', name: 'admin_courses_view', requirements: ['courseId' => '\d+'], methods: ['GET'])]
    public function view(int $courseId): Response
    {
        $course = $this->db->fetchAssociative(
            'SELECT c.*, CONCAT(u.user_prenom, \' \', u.user_nom) AS owner_name, u.user_email AS owner_email'
            . ' FROM courses c LEFT JOIN user u ON u.user_id = c.userid_id'
            . ' WHERE c.id = ?',
            [$courseId]
        );

        if (!$course) {
            throw $this->createNotFoundException('Course not found.');
        }

        $files = $this->db->fetchAllAssociative(
            'SELECT id, originalname, mimetype, sizebytes, uploaded_at FROM coursefile WHERE courseid_id = ? ORDER BY uploaded_at ASC',
            [$courseId]
        );

        $reports = $this->db->fetchAllAssociative(
            'SELECT cr.*, u.user_email AS reporter_email FROM course_reports cr'
            . ' LEFT JOIN user u ON u.user_id = cr.reporter_id'
            . ' WHERE cr.course_id = ? ORDER BY cr.created_at DESC',
            [$courseId]
        );

        return $this->render('admin/library/course-view.html.twig', [
            'course'  => $course,
            'files'   => $files,
            'reports' => $reports,
        ]);
    }
}
