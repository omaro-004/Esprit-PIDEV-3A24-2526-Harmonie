<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\Exercice;
use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Repository\ActiviteRepository;
use App\Repository\ExerciceRepository;
use App\Service\QrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur du Journal d'Activités — Harmony
 *
 * Toutes les routes nécessitent ROLE_USER (protège getUser()->getId()).
 */
#[IsGranted('ROLE_USER')]
#[Route('/activites')]
class ActivitesController extends AbstractController
{
    // ─── Helper : récupère l'utilisateur authentifié typé App\Entity\User ──
    /**
     * Retourne l'utilisateur connecté en tant qu'instance de User.
     *
     * PHPStan : getUser() retourne UserInterface|null.
     * Ici on est protégé par #[IsGranted('ROLE_USER')], donc l'utilisateur
     * est toujours connecté ET toujours une instance de notre entité User.
     */
    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        assert($user instanceof User);
        return $user;
    }

    /**
     * Retourne l'ID de l'utilisateur authentifié sous forme d'int garanti.
     * getId() sur notre entité User est toujours défini après flush,
     * mais PHPStan voit int|null — on force int ici une seule fois.
     */
    private function getAuthenticatedUserId(): int
    {
        return (int) $this->getAuthenticatedUser()->getId();
    }

    // ─── Main page ──────────────────────────────────────────────────
    #[Route('', name: 'activites', methods: ['GET'])]
    public function index(
        ExerciceRepository $exerciceRepo,
        ActiviteRepository $activiteRepo
    ): Response {
        $userId    = $this->getAuthenticatedUserId();
        $exercices = $exerciceRepo->findAllOrdered();
        $grouped   = $activiteRepo->findByUserGroupedByDate($userId);
        $stats     = [
            'sessions' => count($grouped),
            'minutes'  => $activiteRepo->sumMinutesByUser($userId),
            'calories' => $activiteRepo->sumCaloriesByUser($userId),
        ];

        return $this->render('activites/index.html.twig', [
            'exercices' => $exercices,
            'grouped'   => $grouped,
            'stats'     => $stats,
            'userId'    => $userId,
        ]);
    }

    // ─── API: list all activités for a user ─────────────────────────
    #[Route('/api/list', name: 'activites_api_list', methods: ['GET'])]
    public function apiList(ActiviteRepository $repo): JsonResponse
    {
        $userId  = $this->getAuthenticatedUserId();
        $grouped = $repo->findByUserGroupedByDate($userId);
        $result  = [];

        foreach ($grouped as $date => $acts) {
            $exercises = [];
            foreach ($acts as $a) {
                $exercises[] = $this->activiteToArray($a);
            }
            $result[] = [
                'date'      => $date,
                'exercises' => $exercises,
            ];
        }

        $stats = [
            'sessions' => count($grouped),
            'minutes'  => $repo->sumMinutesByUser($userId),
            'calories' => $repo->sumCaloriesByUser($userId),
        ];

        return new JsonResponse(['sessions' => $result, 'stats' => $stats]);
    }

    // ─── API: add one activité ───────────────────────────────────────
    #[Route('/api/add', name: 'activites_api_add', methods: ['POST'])]
    public function apiAdd(
        Request $request,
        EntityManagerInterface $em,
        ExerciceRepository $exerciceRepo,
        ActiviteRepository $activiteRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $errors = [];
        if (empty($data['exercice_id'])) $errors['exercice'] = 'Veuillez sélectionner un exercice.';
        if (empty($data['duree_minutes']) || (int)$data['duree_minutes'] < 1 || (int)$data['duree_minutes'] > 300) {
            $errors['duree'] = 'La durée est requise (1 – 300 min).';
        }
        if (empty($data['date_activite'])) $errors['date'] = 'La date est requise.';

        if ($errors) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 422);
        }

        $exerciceObj = $exerciceRepo->find((int)$data['exercice_id']);
        if (!$exerciceObj instanceof Exercice) {
            return new JsonResponse(['success' => false, 'errors' => ['exercice' => 'Exercice introuvable.']], 404);
        }

        $activite = new Activite();
        $activite->setExercice($exerciceObj);
        $activite->setUserId($this->getAuthenticatedUserId());
        $activite->setDateActivite(new \DateTime($data['date_activite']));
        $activite->setDureeMinutes((int)$data['duree_minutes']);
        $activite->setCaloriesBrulees(!empty($data['calories_brulees']) ? (int)$data['calories_brulees'] : null);
        $activite->setNbSeries(!empty($data['nb_series']) ? (int)$data['nb_series'] : null);
        $activite->setNbRepetitions(!empty($data['nb_repetitions']) ? (int)$data['nb_repetitions'] : null);
        $activite->setPoids(!empty($data['poids']) ? (float)$data['poids'] : null);
        $activite->setNotes(!empty($data['notes']) ? trim($data['notes']) : null);

        $em->persist($activite);
        $em->flush();

        $userId  = $this->getAuthenticatedUserId();
        $grouped = $activiteRepo->findByUserGroupedByDate($userId);
        $stats   = [
            'sessions' => count($grouped),
            'minutes'  => $activiteRepo->sumMinutesByUser($userId),
            'calories' => $activiteRepo->sumCaloriesByUser($userId),
        ];

        return new JsonResponse([
            'success'  => true,
            'activite' => $this->activiteToArray($activite),
            'stats'    => $stats,
        ]);
    }

    // ─── API: update one activité ────────────────────────────────────
    #[Route('/api/update/{id}', name: 'activites_api_update', methods: ['PUT', 'POST'])]
    public function apiUpdate(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ActiviteRepository $activiteRepo
    ): JsonResponse {
        $activite = $activiteRepo->find($id);
        if (!$activite instanceof Activite || $activite->getUserId() !== $this->getAuthenticatedUserId()) {
            return new JsonResponse(['success' => false, 'message' => 'Activité introuvable.'], 404);
        }

        $data   = json_decode($request->getContent(), true);
        $errors = [];

        if (empty($data['duree_minutes']) || (int)$data['duree_minutes'] < 1 || (int)$data['duree_minutes'] > 300) {
            $errors['duree'] = 'La durée est requise (1 – 300 min).';
        }
        if ($errors) {
            return new JsonResponse(['success' => false, 'errors' => $errors], 422);
        }

        $activite->setDureeMinutes((int)$data['duree_minutes']);
        if (isset($data['calories_brulees'])) $activite->setCaloriesBrulees($data['calories_brulees'] !== '' ? (int)$data['calories_brulees'] : null);
        if (isset($data['nb_series'])) $activite->setNbSeries($data['nb_series'] !== '' ? (int)$data['nb_series'] : null);
        if (isset($data['nb_repetitions'])) $activite->setNbRepetitions($data['nb_repetitions'] !== '' ? (int)$data['nb_repetitions'] : null);
        if (isset($data['poids'])) $activite->setPoids($data['poids'] !== '' ? (float)$data['poids'] : null);
        if (isset($data['notes'])) $activite->setNotes(trim($data['notes']) ?: null);

        $em->flush();

        $userId  = $this->getAuthenticatedUserId();
        $grouped = $activiteRepo->findByUserGroupedByDate($userId);
        $stats   = [
            'sessions' => count($grouped),
            'minutes'  => $activiteRepo->sumMinutesByUser($userId),
            'calories' => $activiteRepo->sumCaloriesByUser($userId),
        ];

        return new JsonResponse([
            'success'  => true,
            'activite' => $this->activiteToArray($activite),
            'stats'    => $stats,
        ]);
    }

    // ─── API: delete one activité ────────────────────────────────────
    #[Route('/api/delete/{id}', name: 'activites_api_delete', methods: ['DELETE', 'POST'])]
    public function apiDelete(
        int $id,
        EntityManagerInterface $em,
        ActiviteRepository $activiteRepo
    ): JsonResponse {
        $activite = $activiteRepo->find($id);
        if (!$activite instanceof Activite || $activite->getUserId() !== $this->getAuthenticatedUserId()) {
            return new JsonResponse(['success' => false, 'message' => 'Activité introuvable.'], 404);
        }

        $em->remove($activite);
        $em->flush();

        $userId  = $this->getAuthenticatedUserId();
        $grouped = $activiteRepo->findByUserGroupedByDate($userId);
        $stats   = [
            'sessions' => count($grouped),
            'minutes'  => $activiteRepo->sumMinutesByUser($userId),
            'calories' => $activiteRepo->sumCaloriesByUser($userId),
        ];

        return new JsonResponse(['success' => true, 'stats' => $stats]);
    }

    // ─── QR CODE SESSION ─────────────────────────────────────────────
    #[Route('/qr/{date}', name: 'activites_qr_session', methods: ['GET'],
        requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function qrSession(
        string $date,
        ActiviteRepository $activiteRepo,
        QrCodeService $qrCodeService
    ): JsonResponse {
        try {
            $userId = $this->getAuthenticatedUserId();

            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateObj) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Format de date invalide. Attendu : YYYY-MM-DD.',
                ], 400);
            }

            $dateStart = (clone $dateObj)->setTime(0, 0, 0);
            $dateEnd   = (clone $dateObj)->setTime(23, 59, 59);

            $activites = $activiteRepo->createQueryBuilder('a')
                ->join('a.exercice', 'e')
                ->where('a.userId = :uid')
                ->andWhere('a.dateActivite BETWEEN :date_start AND :date_end')
                ->setParameter('uid', $userId)
                ->setParameter('date_start', $dateStart)
                ->setParameter('date_end', $dateEnd)
                ->getQuery()
                ->getResult();

            if (empty($activites)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Aucune activité trouvée pour la date ' . $date . '.',
                ], 404);
            }

            $exercises = array_map([$this, 'activiteToArray'], $activites);

            $months    = [
                1  => 'janvier',  2  => 'février',  3  => 'mars',
                4  => 'avril',    5  => 'mai',       6  => 'juin',
                7  => 'juillet',  8  => 'août',      9  => 'septembre',
                10 => 'octobre',  11 => 'novembre',  12 => 'décembre',
            ];
            $dateLabel = $dateObj->format('j') . ' ' . $months[(int)$dateObj->format('n')] . ' ' . $dateObj->format('Y');

            $projectDir = is_string($dir = $this->getParameter('kernel.project_dir')) ? $dir : '';
            $logoCandidates = [
                $projectDir . '/public/image/logo.png',
                $projectDir . '/public/images/logo.png',
                $projectDir . '/public/images/harmony-logo.png',
                $projectDir . '/public/images/harmony.png',
                $projectDir . '/public/img/logo.png',
            ];
            $logoPath = null;
            foreach ($logoCandidates as $candidate) {
                if (file_exists($candidate)) {
                    $logoPath = $candidate;
                    break;
                }
            }

            $waUrl     = $qrCodeService->buildWhatsAppUrl($dateLabel, $exercises);
            $qrDataUri = $qrCodeService->generateSessionQrCode($waUrl, $logoPath);

            $totalMin = 0;
            $totalCal = 0;
            foreach ($exercises as $ex) {
                $totalMin += (int) ($ex['duree_minutes'] ?? 0);
                $totalCal += (int) ($ex['calories_brulees'] ?? 0);
            }

            return new JsonResponse([
                'success'   => true,
                'qr'        => $qrDataUri,
                'waUrl'     => $waUrl,
                'date'      => $date,
                'dateLabel' => $dateLabel,
                'nbEx'      => count($exercises),
                'totalMin'  => $totalMin,
                'totalCal'  => $totalCal,
            ]);

        } catch (\Throwable $e) {
            $isDev = $this->getParameter('kernel.environment') === 'dev';

            return new JsonResponse([
                'success' => false,
                'message' => $isDev
                    ? 'Erreur QR Code : ' . $e->getMessage() . ' [' . get_class($e) . ']'
                    : 'Impossible de générer le QR code. Vérifiez que endroid/qr-code est installé.',
            ], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // BILAN PDF — KnpSnappyBundle
    // ═══════════════════════════════════════════════════════════════
    #[Route('/bilan/pdf', name: 'activites_bilan_pdf', methods: ['GET'])]
    public function bilanPdf(
        ActiviteRepository $activiteRepo,
        Pdf $pdf
    ): Response {
        // ── 1. Vérification préalable du binaire wkhtmltopdf ──────────────
        $wkhtmltopdfBinary = array_key_exists('WKHTMLTOPDF_PATH', $_ENV)
            ? (string) $_ENV['WKHTMLTOPDF_PATH']
            : (string) (getenv('WKHTMLTOPDF_PATH') ?: '');

        $wkhtmltopdfBinary = str_replace('/', DIRECTORY_SEPARATOR, $wkhtmltopdfBinary);

        if (empty($wkhtmltopdfBinary)) {
            return $this->generateBilanWithDompdf($activiteRepo);
        }

        if (!file_exists($wkhtmltopdfBinary)) {
            return $this->generateBilanWithDompdf($activiteRepo);
        }

        if (DIRECTORY_SEPARATOR === '/' && str_ends_with(strtolower($wkhtmltopdfBinary), '.exe')) {
            return $this->generateBilanWithDompdf($activiteRepo);
        }

        if (!is_executable($wkhtmltopdfBinary)) {
            return $this->generateBilanWithDompdf($activiteRepo);
        }

        // ── 2. Récupération des données de l'utilisateur ──────────────────
        $userId  = $this->getAuthenticatedUserId();
        $grouped = $activiteRepo->findByUserGroupedByDate($userId);
        $stats   = [
            'sessions' => count($grouped),
            'minutes'  => $activiteRepo->sumMinutesByUser($userId),
            'calories' => $activiteRepo->sumCaloriesByUser($userId),
        ];

        // ── 3. Conversion des entités Activite en tableaux simples ─────────
        $groupedData = [];
        foreach ($grouped as $date => $acts) {
            $exs = [];
            foreach ($acts as $a) {
                $exs[] = $this->activiteToArray($a);
            }
            $groupedData[$date] = $exs;
        }

        // ── 4. Rendu du template Twig → HTML string ────────────────────────
        $projectDirForPdf = is_string($dir = $this->getParameter('kernel.project_dir')) ? $dir : '';
        $publicDir = $projectDirForPdf . '/public';

        $html = $this->renderView('activites/bilan_pdf.html.twig', [
            'grouped'    => $groupedData,
            'stats'      => $stats,
            'exportDate' => new \DateTime(),
            'publicDir'  => $publicDir,
        ]);

        // ── 5. Génération du PDF via KnpSnappy ─────────────────────────────
        try {
            $pdfContent = $pdf->getOutputFromHtml($html, [
                'page-size'                => 'A4',
                'margin-top'               => '0mm',
                'margin-bottom'            => '0mm',
                'margin-left'              => '0mm',
                'margin-right'             => '0mm',
                'encoding'                 => 'UTF-8',
                'enable-local-file-access' => true,
                'no-outline'               => true,
                'print-media-type'         => true,
            ]);
        } catch (\Exception $e) {
            return $this->renderWkhtmlError(
                'Erreur lors de la génération du PDF : ' . $e->getMessage(),
                "Vérifiez que :\n"
                . "1. wkhtmltopdf fonctionne : \"" . $wkhtmltopdfBinary . "\" --version\n"
                . "2. Le chemin dans .env est correct (sans espaces)\n"
                . "3. Redémarrez le serveur Symfony après toute modification du .env"
            );
        }

        // ── 6. Retour du PDF en téléchargement ────────────────────────────
        $filename = 'bilan-harmony-' . (new \DateTime())->format('Y-m-d') . '.pdf';

        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Fallback PDF via Dompdf quand wkhtmltopdf n'est pas disponible.
     */
    private function generateBilanWithDompdf(ActiviteRepository $activiteRepo): Response
    {
        $userId  = $this->getAuthenticatedUserId();
        $grouped = $activiteRepo->findByUserGroupedByDate($userId);
        $stats   = [
            'sessions' => count($grouped),
            'minutes'  => $activiteRepo->sumMinutesByUser($userId),
            'calories' => $activiteRepo->sumCaloriesByUser($userId),
        ];

        $groupedData = [];
        foreach ($grouped as $date => $acts) {
            $exs = [];
            foreach ($acts as $a) {
                $exs[] = $this->activiteToArray($a);
            }
            $groupedData[$date] = $exs;
        }

        $projectDirDompdf = is_string($dir = $this->getParameter('kernel.project_dir')) ? $dir : '';
        $publicDir = $projectDirDompdf . '/public';

        $html = $this->renderView('activites/bilan_pdf.html.twig', [
            'grouped'    => $groupedData,
            'stats'      => $stats,
            'exportDate' => new \DateTime(),
            'publicDir'  => $publicDir,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'bilan-harmony-' . (new \DateTime())->format('Y-m-d') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─── Helper : page d'erreur wkhtmltopdf ─────────────────────────
    private function renderWkhtmlError(string $message, string $detail = ''): Response
    {
        $isDev = $this->getParameter('kernel.environment') === 'dev';

        if (!$isDev) {
            return new Response(
                '<p style="font-family:sans-serif;padding:20px;color:#c0392b;">
                    Une erreur est survenue lors de la génération du PDF.
                    Veuillez contacter l\'administrateur.
                </p>',
                500,
                ['Content-Type' => 'text/html']
            );
        }

        $html = '<div style="font-family:\'Courier New\',monospace;font-size:13px;'
              . 'color:#c0392b;padding:30px;background:#fff8f8;border:2px solid #e74c3c;'
              . 'margin:20px;border-radius:6px;">'
              . '<h2 style="color:#c0392b;margin-bottom:16px;">&#9888; Erreur KnpSnappy / wkhtmltopdf</h2>'
              . '<p style="margin-bottom:12px;color:#333;">' . htmlspecialchars($message) . '</p>';

        if ($detail) {
            $html .= '<pre style="background:#fff;padding:12px;border:1px solid #f5c6cb;'
                   . 'border-radius:4px;color:#555;white-space:pre-wrap;">'
                   . htmlspecialchars($detail) . '</pre>';
        }

        $html .= '<hr style="margin:16px 0;border-color:#f5c6cb;">'
               . '<p style="font-size:11px;color:#888;">Cette erreur n\'est visible qu\'en mode dev (APP_ENV=dev).</p>'
               . '</div>';

        return new Response($html, 500, ['Content-Type' => 'text/html']);
    }

    // ─── Helper : entité → tableau ────────────────────────────────
    /**
     * @return array<string, mixed>
     */
    private function activiteToArray(Activite $a): array
    {
        return [
            'id'               => $a->getId(),
            'exercice_id'      => $a->getExercice()?->getId(),
            'exercice_nom'     => $a->getExercice()?->getNomExercice(),
            'exercice_type'    => $a->getExercice()?->getTypeExercice(),
            'exercice_video'   => $a->getExercice()?->getVideoExercice(),
            'date_activite'    => $a->getDateActivite()?->format('Y-m-d'),
            'duree_minutes'    => $a->getDureeMinutes(),
            'calories_brulees' => $a->getCaloriesBrulees(),
            'nb_series'        => $a->getNbSeries(),
            'nb_repetitions'   => $a->getNbRepetitions(),
            'poids'            => $a->getPoids(),
            'notes'            => $a->getNotes(),
        ];
    }
}