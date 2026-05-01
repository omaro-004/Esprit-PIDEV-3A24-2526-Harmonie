<?php

namespace App\Controller\LibraryControllers;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/library')]
class LibraryController extends AbstractController
{
    public function __construct(private readonly Connection $db) {}

    // ── GET /library ── all published courses ──────────────────────────────
    #[Route('', name: 'library', methods: ['GET'])]
    public function index(): Response
    {
        // Only courses marked is_published = 1
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

        return $this->render('library/library.html.twig', [
            'courses' => $courses,
        ]);
    }
}
