<?php

namespace App\Controller;

use App\Entity\Tache;
use App\Form\TacheType;
use App\Repository\CalendrierRepository;
use App\Repository\TacheRepository;
use App\Service\Domain\PlanningDomainService;
use App\Service\Export\KanbanExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/tache')]
final class TachesController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    #[Route(name: 'app_tache_index', methods: ['GET'])]
    #[Route('/index', name: 'taches', methods: ['GET'])]
    public function index(TacheRepository $tacheRepository, CalendrierRepository $calendrierRepository): Response
    {
        $today = new \DateTimeImmutable('today');

        $draftTache = new Tache();
        $draftTache->setStatutTache('A_FAIRE');
        if ($cal = $calendrierRepository->findPrimary()) {
            $draftTache->setCalendrier($cal);
        }
        $tacheFormNew = $this->createForm(TacheType::class, $draftTache);

        return $this->render('tache/index.html.twig', [
            'columns' => $tacheRepository->groupedByKanbanStatut(),
            'today' => $today,
            'advice' => $this->fetchAdvice(),
            'tacheFormNew' => $tacheFormNew->createView(),
        ]);
    }

    #[Route('/export/csv', name: 'app_tache_export_csv', methods: ['GET'])]
    public function exportCsv(TacheRepository $tacheRepository): StreamedResponse
    {
        $rows = $tacheRepository->findAllOrderedForKanban();

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'nom', 'deadline', 'notes', 'statut_tache'], ';');
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->getId(),
                    $t->getNom(),
                    $t->getDeadline() ? $t->getDeadline()->format('Y-m-d') : '',
                    $t->getNotes() ?? '',
                    $t->getStatutTache(),
                ], ';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="taches-harmony.csv"');

        return $response;
    }

    #[Route('/export/pdf', name: 'app_tache_export_pdf', methods: ['POST'])]
    public function exportPdf(
        TacheRepository $tacheRepository,
        KanbanExportService $exportService,
    ): Response {
        $tachesByStatus = $tacheRepository->groupedByKanbanStatut();

        return $exportService->exportToPdf($tachesByStatus);
    }

    #[Route('/export/excel', name: 'app_tache_export_excel', methods: ['POST'])]
    public function exportExcel(
        TacheRepository $tacheRepository,
        KanbanExportService $exportService,
    ): StreamedResponse {
        $taches = $tacheRepository->findAllOrderedForKanban();

        return $exportService->exportToExcel($taches);
    }

    #[Route('/{id}/statut', name: 'app_tache_update_statut', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatut(
        Request $request,
        Tache $tache,
        PlanningDomainService $domainService,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['ok' => false, 'error' => 'Corps JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $token = $payload['_token'] ?? '';
        if (!$this->isCsrfTokenValid('tache_dnd', (string) $token)) {
            return new JsonResponse(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
        }

        $statut = $payload['statut'] ?? '';
        if (!\in_array($statut, ['A_FAIRE', 'EN_COURS', 'TERMINEE'], true)) {
            return new JsonResponse(['ok' => false, 'error' => 'Statut inconnu'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $tache->setStatutTache($statut);
            $domainService->saveTache($tache);
        } catch (\DomainException $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['ok' => true, 'statut' => $statut]);
    }

    #[Route('/new', name: 'app_tache_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PlanningDomainService $domainService): Response
    {
        $tache = new Tache();
        $preset = $request->query->get('statut');
        if (\is_string($preset) && \in_array($preset, ['A_FAIRE', 'EN_COURS', 'TERMINEE'], true)) {
            $tache->setStatutTache($preset);
        }
        $form = $this->createForm(TacheType::class, $tache);
        $form->handleRequest($request);
        $ajax = $request->isXmlHttpRequest();

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $domainService->saveTache($tache);
                $this->addFlash('success', 'Tâche enregistrée avec succès.');
                if ($ajax) {
                    return new JsonResponse([
                        'ok' => true,
                        'redirect' => $this->generateUrl('app_tache_index'),
                    ]);
                }

                return $this->redirectToRoute('app_tache_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
                if ($ajax) {
                    return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid() && $ajax) {
            return $this->render('tache/_form_panel.html.twig', [
                'form' => $form->createView(),
                'panel_mode' => 'new',
                'entity' => null,
            ]);
        }

        return $this->render('tache/new.html.twig', [
            'tache' => $tache,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tache_show', methods: ['GET'])]
    public function show(Tache $tache): Response
    {
        return $this->render('tache/show.html.twig', [
            'tache' => $tache,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tache_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tache $tache, PlanningDomainService $domainService): Response
    {
        $form = $this->createForm(TacheType::class, $tache);
        $form->handleRequest($request);
        $ajax = $request->isXmlHttpRequest();

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $domainService->saveTache($tache);
                $this->addFlash('success', 'Tâche enregistrée avec succès.');
                if ($ajax) {
                    return new JsonResponse([
                        'ok' => true,
                        'redirect' => $this->generateUrl('app_tache_index'),
                    ]);
                }

                return $this->redirectToRoute('app_tache_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
                if ($ajax) {
                    return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid() && $ajax) {
            return $this->render('tache/_form_panel.html.twig', [
                'form' => $form->createView(),
                'panel_mode' => 'edit',
                'entity' => $tache,
            ]);
        }

        if ('1' === $request->query->get('panel') && !$form->isSubmitted()) {
            return $this->render('tache/_form_panel.html.twig', [
                'form' => $form->createView(),
                'panel_mode' => 'edit',
                'entity' => $tache,
            ]);
        }

        return $this->render('tache/edit.html.twig', [
            'tache' => $tache,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tache_delete', methods: ['POST'])]
    public function delete(Request $request, Tache $tache, PlanningDomainService $domainService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tache->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $domainService->removeTache($tache);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_tache_index', [], Response::HTTP_SEE_OTHER);
    }

    private function fetchAdvice(): string
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.adviceslip.com/advice',
                ['timeout' => 4],
            );
            $data = $response->toArray(false);
            $advice = $data['slip']['advice'] ?? null;

            return \is_string($advice) && '' !== $advice
                ? $advice
                : "Il semble impossible, jusqu'à ce que ce soit fait.";
        } catch (\Throwable) {
            return "Il semble impossible, jusqu'à ce que ce soit fait.";
        }
    }
}
