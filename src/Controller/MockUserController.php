<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DEV ONLY — mock user switcher. Delete once auth is integrated.
 */
class MockUserController extends AbstractController
{
    // ── Hardcoded fallback users shown when the DB table is empty ─────────
    private const DUMMY_USERS = [
        ['id' => 1, 'firstname' => 'Alice',   'lastname' => 'Admin',   'email' => 'alice@dev.local',   'role' => 'ADMIN'],
        ['id' => 2, 'firstname' => 'Bob',      'lastname' => 'Coach',   'email' => 'bob@dev.local',     'role' => 'COACH'],
        ['id' => 3, 'firstname' => 'Carol',    'lastname' => 'Student', 'email' => 'carol@dev.local',   'role' => 'ETUDIANT'],
        ['id' => 4, 'firstname' => 'David',    'lastname' => 'Student', 'email' => 'david@dev.local',   'role' => 'ETUDIANT'],
        ['id' => 5, 'firstname' => 'Eve',      'lastname' => 'Coach',   'email' => 'eve@dev.local',     'role' => 'COACH'],
    ];

    public function __construct(private Connection $db) {}

    // ── LIST USERS ─────────────────────────────────────────────────────────
    #[Route('/dev/mock-user/list', name: 'dev_mock_user_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        // 1. Try the real user table with standard column names
        try {
            $users = $this->db->fetchAllAssociative(
                'SELECT user_id AS id, user_prenom AS firstname, user_nom AS lastname,
                        user_email AS email, type_utilisateur AS role
                 FROM `user`
                 ORDER BY user_prenom, user_nom
                 LIMIT 200'
            );

            if (!empty($users)) {
                return $this->json($users);
            }
        } catch (\Throwable) {
            // Table missing or columns differ — fall through to alternatives
        }

        // 2. Try generic column names (id, firstname / first_name, etc.)
        try {
            $users = $this->tryGenericColumns();
            if (!empty($users)) {
                return $this->json($users);
            }
        } catch (\Throwable) {}

        // 3. Nothing in DB — return hardcoded dummy users so the widget works
        return $this->json(self::DUMMY_USERS);
    }

    // ── SET ACTIVE USER ────────────────────────────────────────────────────
    #[Route('/dev/mock-user/set', name: 'dev_mock_user_set', methods: ['POST'])]
    public function set(Request $req, SessionInterface $session): JsonResponse
    {
        $id = (int) $req->request->get('userId', 0);

        if ($id <= 0) {
            $session->remove('mock_user_id');
            $session->remove('mock_user_data');
            return $this->json(['ok' => true, 'cleared' => true]);
        }

        $session->set('mock_user_id', $id);

        // Try DB first, fall back to dummy list
        $user = $this->findUser($id);
        $session->set('mock_user_data', $user);

        return $this->json(['ok' => true, 'user' => $user]);
    }

    // ── GET CURRENT USER ───────────────────────────────────────────────────
    #[Route('/dev/mock-user/current', name: 'dev_mock_user_current', methods: ['GET'])]
    public function current(SessionInterface $session): JsonResponse
    {
        $id = $session->get('mock_user_id');
        if (!$id) {
            return $this->json(['user' => null]);
        }

        // Return cached data from session to avoid repeated DB hits
        $cached = $session->get('mock_user_data');
        if ($cached && ($cached['id'] ?? null) == $id) {
            return $this->json(['user' => $cached]);
        }

        $user = $this->findUser((int) $id);
        return $this->json(['user' => $user]);
    }

    // ── HELPERS ────────────────────────────────────────────────────────────

    /**
     * Find a user by id — DB first, dummy list as fallback
     * @return array<mixed>|null
     */
    private function findUser(int $id): ?array
    {
        try {
            $user = $this->db->fetchAssociative(
                'SELECT user_id AS id, user_prenom AS firstname, user_nom AS lastname,
                        user_email AS email, type_utilisateur AS role
                 FROM `user` WHERE user_id = ?',
                [$id]
            );
            if ($user) return $user;
        } catch (\Throwable) {}

        // Fall back to dummy list
        foreach (self::DUMMY_USERS as $u) {
            if ($u['id'] === $id) return $u;
        }

        return null;
    }

    /**
     * Attempt to detect alternative column naming conventions
     * @return array<mixed>
     */
    private function tryGenericColumns(): array
    {
        // Try snake_case alternatives common in other frameworks
        $candidates = [
            "SELECT id, firstname, lastname, email, role FROM `user` ORDER BY firstname LIMIT 200",
            "SELECT id, first_name AS firstname, last_name AS lastname, email, role FROM `user` ORDER BY first_name LIMIT 200",
            "SELECT id, prenom AS firstname, nom AS lastname, email, 'USER' AS role FROM `user` ORDER BY prenom LIMIT 200",
        ];

        foreach ($candidates as $sql) {
            try {
                $rows = $this->db->fetchAllAssociative($sql);
                if (!empty($rows)) return $rows;
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }
}
