<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\JournalHumeurRepository;
use App\Repository\UserRepository;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/journal')]
class AdminJournalController extends AbstractController
{
    public function __construct(
        private readonly JournalHumeurRepository $journalRepo,
        private readonly UserRepository          $userRepo,
        private readonly GroqService             $groq,
        private readonly EntityManagerInterface  $em,
    ) {}

    #[Route('', name: 'admin_journal_index', methods: ['GET'])]
    public function index(): Response
    {
        $entries   = $this->journalRepo->findAllForAdmin();
        $unreadIds = array_values(array_map(
            fn($e) => $e->getId(),
            array_filter($entries, fn($e) => !$e->isReadByAdmin())
        ));

        return $this->render('admin/journal/index.html.twig', [
            'entries'   => $entries,
            'unreadIds' => $unreadIds,
        ]);
    }

    #[Route('/mark-read', name: 'admin_journal_mark_read', methods: ['POST'])]
    public function markRead(): JsonResponse
    {
        $this->journalRepo->markUnreadAsRead();
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/students', name: 'admin_journal_students', methods: ['GET'])]
    public function students(): Response
    {
        $users    = $this->userRepo->findAll();
        $userData = array_map(function (object $user) {
            /** @var User $user */
            return [
                'user'  => $user,
                'stats' => $this->journalRepo->moodStats($user),
            ];
        }, $users);

        $userData = array_values(array_filter($userData, fn($u) => $u['stats']['total'] > 0));

        return $this->render('admin/journal/students.html.twig', [
            'userData' => $userData,
        ]);
    }

    #[Route('/user/{id}', name: 'admin_journal_user', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function userJournal(User $user): Response
    {
        $entries = $this->journalRepo->findAllByUser($user);
        $stats   = $this->journalRepo->moodStats($user);
        $trend   = $this->journalRepo->scoreTrend($user, 30);
        $dist    = $this->journalRepo->moodDistribution($user);

        return $this->render('admin/journal/user.html.twig', compact('user', 'entries', 'stats', 'trend', 'dist'));
    }

    #[Route('/user/{id}/rapport', name: 'admin_journal_rapport', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function rapport(User $user): Response
    {
        $entries = $this->journalRepo->findAllByUser($user);

        if (empty($entries)) {
            $this->addFlash('error', 'Cet étudiant n\'a aucune entrée dans son journal.');
            return $this->redirectToRoute('admin_journal_students');
        }

        $stats = $this->journalRepo->moodStats($user);

        $lines = ["Résumé anonymisé du journal de l'étudiant(e) :", ""];
        foreach ($entries as $e) {
            $lines[] = sprintf(
                "- %s : humeur=%s, score=%d/5",
                $e->getDateJournal()->format('d/m/Y'),
                $e->getHumeur()->label(),
                $e->getScore()
            );
        }
        $lines[] = "";
        $lines[] = sprintf("Score moyen : %.2f/5 sur %d entrées.", $stats['avgScore'], $stats['total']);

        $journalSummary = implode("\n", $lines);
        $studentName    = $user->getFirstName() . ' ' . $user->getLastName();

        try {
            $aiText = $this->groq->generateWellbeingReport($studentName, $journalSummary);
        } catch (\Throwable $e) {
            $aiText = "Rapport non disponible (erreur de génération IA).";
        }

        $html = $this->renderView('admin/journal/rapport_pdf.html.twig', [
            'user'        => $user,
            'entries'     => $entries,
            'stats'       => $stats,
            'aiText'      => $aiText,
            'generatedAt' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'rapport_bienetre_' . strtolower(str_replace(' ', '_', $studentName)) . '_' . date('Ymd') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
