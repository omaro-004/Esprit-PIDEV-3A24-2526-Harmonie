<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;

#[Route('/admin/course-reports')]
class AdminCourseReportsController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('', name: 'admin_course_reports_index', methods: ['GET'])]
    public function index(Request $req): Response
    {
        $status = $req->query->get('status', 'all');
        $search = trim((string) $req->query->get('q', ''));

        $where  = [];
        $params = [];

        if ($status !== 'all') {
            $where[]  = 'cr.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[]  = '(c.title LIKE ? OR reporter.user_email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $sql = 'SELECT'
            . ' cr.id, cr.course_id, cr.reporter_id, cr.reason, cr.details, cr.status, cr.created_at,'
            . ' c.title AS course_title, c.is_published AS course_is_published, c.userid_id AS course_owner_id,'
            . ' owner.user_email AS owner_email,'
            . ' reporter.user_email AS reporter_email'
            . ' FROM course_reports cr'
            . ' JOIN courses c ON c.id = cr.course_id'
            . ' LEFT JOIN user owner ON owner.user_id = c.userid_id'
            . ' LEFT JOIN user reporter ON reporter.user_id = cr.reporter_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY cr.course_id ASC, cr.created_at DESC';

        $rows = $this->db->fetchAllAssociative($sql, $params);

        // Group rows by course_id
        $grouped = [];
        foreach ($rows as $row) {
            $cid = $row['course_id'];
            if (!isset($grouped[$cid])) {
                $grouped[$cid] = [
                    'course_id'          => $cid,
                    'course_title'       => $row['course_title'],
                    'course_is_published'=> $row['course_is_published'],
                    'owner_email'        => $row['owner_email'],
                    'reports'            => [],
                ];
            }
            $grouped[$cid]['reports'][] = $row;
        }

        // Compute worst status per group (pending > reviewed > dismissed)
        $priority = ['pending' => 0, 'reviewed' => 1, 'dismissed' => 2];
        foreach ($grouped as &$group) {
            usort($group['reports'], fn($a, $b) => ($priority[$a['status']] ?? 9) - ($priority[$b['status']] ?? 9));
            $group['worst_status'] = $group['reports'][0]['status'] ?? 'dismissed';
        }
        unset($group);

        $stats = $this->db->fetchAssociative(
            "SELECT COUNT(*) AS total, SUM(status='pending') AS pending, SUM(status='reviewed') AS reviewed, SUM(status='dismissed') AS dismissed FROM course_reports"
        );

        return $this->render('admin/library/course-reports.html.twig', [
            'grouped'       => array_values($grouped),
            'stats'         => $stats,
            'currentStatus' => $status,
            'search'        => $search,
        ]);
    }

    #[Route('/unpublish/{courseId}', name: 'admin_course_reports_unpublish', requirements: ['courseId' => '\d+'], methods: ['POST'])]
    public function unpublish(int $courseId): JsonResponse
    {
        $affected = $this->db->executeStatement(
            'UPDATE courses SET is_published = 0, admin_locked = 1 WHERE id = ?',
            [$courseId]
        );
        if ($affected === 0) {
            return $this->json(['message' => 'Course not found.'], 404);
        }
        $this->db->executeStatement(
            "UPDATE course_reports SET status = 'reviewed' WHERE course_id = ? AND status = 'pending'",
            [$courseId]
        );
        $this->addFlash('success', 'Cours dépublié et verrouillé.');
        return $this->json(['ok' => true]);
    }

    #[Route('/dismiss/{reportId}', name: 'admin_course_reports_dismiss', requirements: ['reportId' => '\d+'], methods: ['POST'])]
    public function dismiss(int $reportId): JsonResponse
    {
        $affected = $this->db->executeStatement(
            "UPDATE course_reports SET status = 'dismissed' WHERE id = ?",
            [$reportId]
        );
        if ($affected === 0) {
            return $this->json(['message' => 'Report not found.'], 404);
        }
        return $this->json(['ok' => true]);
    }

    #[Route('/restore/{courseId}', name: 'admin_course_reports_restore', requirements: ['courseId' => '\d+'], methods: ['POST'])]
    public function restore(int $courseId): JsonResponse
    {
        $affected = $this->db->executeStatement(
            'UPDATE courses SET is_published = 1, admin_locked = 0 WHERE id = ?',
            [$courseId]
        );
        if ($affected === 0) {
            return $this->json(['message' => 'Course not found.'], 404);
        }
        $this->addFlash('success', 'Cours restauré et republié.');
        return $this->json(['ok' => true]);
    }
}
