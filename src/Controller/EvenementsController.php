<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\User;
use App\Form\EvenementType;
use App\Repository\CalendrierRepository;
use App\Repository\EvenementRepository;
use App\Repository\SalleRepository;
use App\Service\Domain\PlanningDomainService;
use App\Service\Export\CalendarExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/evenement')]
final class EvenementsController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    #[Route('/salles-api', name: 'app_evenement_salles_api', methods: ['GET'])]
    public function sallesApi(SalleRepository $salleRepository): JsonResponse
    {
        $salles = $salleRepository->findBy(['disponible' => true], ['nom' => 'ASC']);
        $data = [];
        foreach ($salles as $salle) {
            $data[] = [
                'id' => $salle->getId(),
                'nom' => $salle->getNom(),
                'capacite' => $salle->getCapacite(),
                'label' => $salle->getNom().' · '.$salle->getCapacite().' pers.',
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/export/pdf', name: 'app_evenement_export_pdf', methods: ['POST'])]
    public function exportPdf(
        Request $request,
        EvenementRepository $evenementRepository,
        CalendarExportService $exportService,
    ): Response {
        $year = (int) $request->request->get('year', date('Y'));
        $month = (int) $request->request->get('month', date('m'));

        $evenements = $evenementRepository->findWithDateDebutInMonth($year, $month);

        return $exportService->exportToPdf($evenements, $year, $month);
    }

    #[Route('/export/excel', name: 'app_evenement_export_excel', methods: ['POST'])]
    public function exportExcel(
        Request $request,
        EvenementRepository $evenementRepository,
        CalendarExportService $exportService,
    ): StreamedResponse {
        $year = (int) $request->request->get('year', date('Y'));
        $month = (int) $request->request->get('month', date('m'));

        $evenements = $evenementRepository->findWithDateDebutInMonth($year, $month);

        return $exportService->exportToExcel($evenements, $year, $month);
    }

    #[Route(name: 'app_evenement_index', methods: ['GET'])]
    #[Route('/index', name: 'evenements', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepository,
        CalendrierRepository $calendrierRepository,
    ): Response {
        $today = new \DateTimeImmutable('today');
        $year = max(1970, (int) $request->query->get('year', $today->format('Y')));
        $month = min(12, max(1, (int) $request->query->get('month', $today->format('n'))));

        $firstOfMonth = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month))
            ?: throw new \InvalidArgumentException('Mois invalide');
        $daysInMonth = (int) $firstOfMonth->format('t');
        $firstWeekday = (int) $firstOfMonth->format('N');

        $evenements = $evenementRepository->findWithDateDebutInMonth($year, $month);
        $eventsByDay = [];
        foreach ($evenements as $ev) {
            $debut = $ev->getDateDebut();
            if (null === $debut) {
                continue;
            }
            if ((int) $debut->format('Y') !== $year || (int) $debut->format('n') !== $month) {
                continue;
            }
            $d = (int) $debut->format('j');
            $eventsByDay[$d][] = $ev;
        }

        $pad = $firstWeekday - 1;
        $cells = [];
        for ($i = 0; $i < $pad; ++$i) {
            $cells[] = null;
        }
        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $cellDate = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $d))
                ?: $firstOfMonth;
            $cells[] = [
                'day' => $d,
                'date' => $cellDate,
                'events' => $eventsByDay[$d] ?? [],
                'isToday' => $cellDate->format('Y-m-d') === $today->format('Y-m-d'),
                'isSunday' => '7' === $cellDate->format('N'),
            ];
        }
        while (0 !== \count($cells) % 7) {
            $cells[] = null;
        }
        $weeks = array_chunk($cells, 7);

        $draftEvenement = new Evenement();
        if ($cal = $calendrierRepository->findPrimary()) {
            $draftEvenement->setCalendrier($cal);
        }
        $evenementFormNew = $this->createForm(EvenementType::class, $draftEvenement);

        return $this->render('evenement/index.html.twig', [
            'weeks' => $weeks,
            'year' => $year,
            'month' => $month,
            'monthLabel' => $this->formatFrenchMonthYear($firstOfMonth),
            'prev' => $firstOfMonth->modify('-1 month'),
            'next' => $firstOfMonth->modify('+1 month'),
            'weatherLine' => $this->fetchWeatherLine(),
            'evenementFormNew' => $evenementFormNew->createView(),
        ]);
    }

    #[Route('/export/csv', name: 'app_evenement_export_csv', methods: ['GET'])]
    public function exportCsv(EvenementRepository $evenementRepository): StreamedResponse
    {
        $rows = $evenementRepository->findAllWithDateDebutOrdered();

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'titre', 'date_debut', 'date_fin', 'lieu', 'type'], ';');
            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->getId(),
                    $e->getTitre() ?? '',
                    $e->getDateDebut() ? $e->getDateDebut()->format('Y-m-d H:i:s') : '',
                    $e->getDateFin() ? $e->getDateFin()->format('Y-m-d H:i:s') : '',
                    $e->getLieu() ?? '',
                    $e->getTypeEvenement() ?? '',
                ], ';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="evenements-harmony.csv"');

        return $response;
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PlanningDomainService $domainService): Response
    {
        $evenement = new Evenement();
        $dateStr = $request->query->get('date');
        if (\is_string($dateStr) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $evenement->setDateDebut(new \DateTime($dateStr.' 09:00:00'));
            $evenement->setDateFin(new \DateTime($dateStr.' 10:00:00'));
        }
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);
        $ajax = $request->isXmlHttpRequest();

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $demandeur = $this->getUser();
                $u = $demandeur instanceof User ? $demandeur : null;
                if (null === $evenement->getProprietaire()) {
                    $evenement->setProprietaire($u);
                }
                $domainService->saveEvenement($evenement, $u);
                $this->addFlash('success', 'Événement enregistré avec succès.');
                if ($ajax) {
                    return new JsonResponse([
                        'ok' => true,
                        'redirect' => $this->generateUrl('app_evenement_index'),
                    ]);
                }

                return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
                if ($ajax) {
                    return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid() && $ajax) {
            return $this->render('evenement/_form_panel.html.twig', [
                'form' => $form->createView(),
                'panel_mode' => 'new',
                'entity' => null,
            ]);
        }

        return $this->render('evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, PlanningDomainService $domainService): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);
        $ajax = $request->isXmlHttpRequest();

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $demandeur = $this->getUser();
                $u = $demandeur instanceof User ? $demandeur : null;
                $domainService->saveEvenement($evenement, $u);
                $this->addFlash('success', 'Événement enregistré avec succès.');
                if ($ajax) {
                    return new JsonResponse([
                        'ok' => true,
                        'redirect' => $this->generateUrl('app_evenement_index'),
                    ]);
                }

                return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
                if ($ajax) {
                    return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid() && $ajax) {
            return $this->render('evenement/_form_panel.html.twig', [
                'form' => $form->createView(),
                'panel_mode' => 'edit',
                'entity' => $evenement,
            ]);
        }

        if ('1' === $request->query->get('panel') && !$form->isSubmitted()) {
            return $this->render('evenement/_form_panel.html.twig', [
                'form' => $form->createView(),
                'panel_mode' => 'edit',
                'entity' => $evenement,
            ]);
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, PlanningDomainService $domainService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $domainService->removeEvenement($evenement);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }

    private function formatFrenchMonthYear(\DateTimeImmutable $firstOfMonth): string
    {
        $months = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];
        $m = (int) $firstOfMonth->format('n');
        $y = (int) $firstOfMonth->format('Y');

        return $months[$m].' '.$y;
    }

    private function fetchWeatherLine(): string
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.open-meteo.com/v1/forecast',
                [
                    'query' => [
                        'latitude' => 36.8065,
                        'longitude' => 10.1815,
                        'daily' => 'temperature_2m_max,temperature_2m_min,weather_code',
                        'forecast_days' => 1,
                        'timezone' => 'auto',
                    ],
                    'timeout' => 4,
                ],
            );
            $data = $response->toArray(false);
            $daily = $data['daily'] ?? null;
            if (!\is_array($daily)) {
                return "Météo aujourd'hui : indisponible";
            }
            $mins = $daily['temperature_2m_min'] ?? null;
            $maxs = $daily['temperature_2m_max'] ?? null;
            $codes = $daily['weather_code'] ?? null;
            $tMin = \is_array($mins) && isset($mins[0]) ? $mins[0] : null;
            $tMax = \is_array($maxs) && isset($maxs[0]) ? $maxs[0] : null;
            $code = \is_array($codes) && isset($codes[0]) ? (int) $codes[0] : -1;
            $label = $this->weatherCodeLabel($code);
            if (null === $tMin || null === $tMax) {
                return "Météo aujourd'hui : indisponible";
            }

            return sprintf(
                "Météo aujourd'hui : %s, %.0f° / %.0f°",
                $label,
                (float) $tMin,
                (float) $tMax,
            );
        } catch (\Throwable) {
            return "Météo aujourd'hui : indisponible";
        }
    }

    private function weatherCodeLabel(int $code): string
    {
        return match (true) {
            0 === $code => 'Dégagé',
            $code <= 3 => 'Nuageux',
            $code <= 48 => 'Brouillard',
            $code <= 57 || ($code >= 80 && $code <= 82) => 'Pluie',
            $code <= 67 => 'Pluie verglaçante',
            $code <= 77 => 'Neige',
            $code <= 86 => 'Chutes de neige',
            default => 'Variable',
        };
    }
}
