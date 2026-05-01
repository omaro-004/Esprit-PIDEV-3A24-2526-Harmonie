<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\SuspicionScoreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(UserRepository $repo, SuspicionScoreService $suspicion): Response
    {
        $allStudents = $repo->findAllStudents();
        $total       = count($allStudents);
        $active      = count($repo->findActiveStudents());
        $suspended   = count($repo->findSuspendedStudents());
        $recent      = array_slice($repo->findAllStudents(), 0, 5);

        // ── Stats pour graphiques ─────────────────────────────────────────

        // 1. Répartition par sexe
        $sexeCount = ['HOMME' => 0, 'FEMME' => 0, 'AUTRE' => 0, 'Non renseigné' => 0];
        foreach ($allStudents as $u) {
            $s = $u->getUserSexe();
            if ($s && isset($sexeCount[$s])) {
                $sexeCount[$s]++;
            } else {
                $sexeCount['Non renseigné']++;
            }
        }

        // 2. Répartition par niveau scolaire
        $niveauCount = [];
        foreach ($allStudents as $u) {
            $n = $u->getUserNiveauScolaire() ?? 'Non renseigné';
            $niveauCount[$n] = ($niveauCount[$n] ?? 0) + 1;
        }
        arsort($niveauCount);

        // 3. Répartition par activité physique
        $activiteCount = [];
        foreach ($allStudents as $u) {
            $a = $u->getUserNiveauActivitePhysique() ?? 'Non renseigné';
            $activiteCount[$a] = ($activiteCount[$a] ?? 0) + 1;
        }

        // 4. Inscriptions par mois (12 derniers mois)
        $inscriptionsByMonth = [];
        $now = new \DateTime();
        for ($i = 11; $i >= 0; $i--) {
            $month = (clone $now)->modify("-{$i} months")->format('Y-m');
            $inscriptionsByMonth[$month] = 0;
        }
        foreach ($allStudents as $u) {
            $month = substr($u->getDateInscription(), 0, 7); // "YYYY-MM"
            if (isset($inscriptionsByMonth[$month])) {
                $inscriptionsByMonth[$month]++;
            }
        }

        // 5. Score de suspicion — distribution
        $suspicionDist = ['Normal' => 0, 'Modéré' => 0, 'Suspect' => 0, 'Très suspect' => 0];
        foreach ($allStudents as $u) {
            $score = $suspicion->compute($u);
            $label = $suspicion->getLabel($score);
            $suspicionDist[$label] = ($suspicionDist[$label] ?? 0) + 1;
        }

        // 6. Répartition actifs/suspendus par mois (6 derniers mois)
        $statusByMonth = [];
        $now = new \DateTime();
        for ($i = 5; $i >= 0; $i--) {
            $month = (clone $now)->modify("-{$i} months")->format('Y-m');
            $statusByMonth[$month] = ['actif' => 0, 'suspendu' => 0];
        }
        foreach ($allStudents as $u) {
            $month = substr($u->getDateInscription(), 0, 7);
            if (isset($statusByMonth[$month])) {
                if ($u->isActive()) {
                    $statusByMonth[$month]['actif']++;
                } else {
                    $statusByMonth[$month]['suspendu']++;
                }
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'total'              => $total,
            'active'             => $active,
            'suspended'          => $suspended,
            'recent'             => $recent,
            // Stats JSON pour JS
            'sexeCount'          => $sexeCount,
            'niveauCount'        => $niveauCount,
            'activiteCount'      => $activiteCount,
            'inscriptionsByMonth'=> $inscriptionsByMonth,
            'suspicionDist'      => $suspicionDist,
            'statusByMonth'      => $statusByMonth,
        ]);
    }
}
