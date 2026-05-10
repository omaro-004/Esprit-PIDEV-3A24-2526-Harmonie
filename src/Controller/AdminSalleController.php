<?php

namespace App\Controller;

use App\Entity\Salle;
use App\Form\AdminSalleType;
use App\Repository\SalleRepository;
use App\Service\Domain\PlanningDomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/salles')]
#[IsGranted('ROLE_ADMIN')]
final class AdminSalleController extends AbstractController
{
    private const LIMIT = 10;

    #[Route('', name: 'admin_salle_index', methods: ['GET'])]
    public function index(Request $request, SalleRepository $salleRepository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $search = $request->query->getString('q');
        $search = '' !== $search ? $search : null;
        $total = $salleRepository->countAdminFiltered($search);
        $salles = $salleRepository->findAdminPaginated($page, self::LIMIT, $search);
        $pages = (int) max(1, (int) ceil($total / self::LIMIT));

        return $this->render('admin/salle/index.html.twig', [
            'salles' => $salles,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'searchQuery' => $search ?? '',
        ]);
    }

    #[Route('/new', name: 'admin_salle_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PlanningDomainService $domainService): Response
    {
        $salle = new Salle();
        $form = $this->createForm(AdminSalleType::class, $salle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $domainService->saveSalle($salle);
                $this->addFlash('success', 'Salle créée.');

                return $this->redirectToRoute('admin_salle_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/salle/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_salle_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Salle $salle, PlanningDomainService $domainService): Response
    {
        $form = $this->createForm(AdminSalleType::class, $salle);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $domainService->saveSalle($salle);
                $this->addFlash('success', 'Salle mise à jour.');

                return $this->redirectToRoute('admin_salle_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/salle/edit.html.twig', [
            'salle' => $salle,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle', name: 'admin_salle_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, Salle $salle, PlanningDomainService $domainService): Response
    {
        $token = $request->request->getString('_token') ?: $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('admin_toggle_salle'.$salle->getId(), $token)) {
        if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
            }

            return $this->redirectToRoute('admin_salle_index', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $salle->setDisponible(!$salle->isDisponible());
            $domainService->saveSalle($salle);
        if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'ok' => true,
                    'disponible' => $salle->isDisponible(),
                ]);
            }
            $this->addFlash('success', $salle->isDisponible() ? 'Salle marquée disponible.' : 'Salle marquée indisponible.');
        } catch (\DomainException $e) {
            /** @phpstan-ignore-next-line */
        if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_salle_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'admin_salle_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Salle $salle, PlanningDomainService $domainService): Response
    {
        $token = $request->request->getString('_token') ?: $request->getPayload()->getString('_token');
        if ($this->isCsrfTokenValid('admin_delete_salle'.$salle->getId(), $token)) {
            try {
                $domainService->removeSalle($salle);
                $this->addFlash('success', 'Salle supprimée.');
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_salle_index', [], Response::HTTP_SEE_OTHER);
    }
}
