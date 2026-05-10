<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use App\Repository\UserRepository;
use App\Service\Domain\PlanningDomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/evenements')]
#[IsGranted('ROLE_ADMIN')]
final class AdminEvenementController extends AbstractController
{
    private const LIMIT = 10;

    #[Route('', name: 'admin_evenement_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepository, UserRepository $userRepository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $typeFiltre = $request->query->getString('type');
        $typeFiltre = '' !== $typeFiltre ? $typeFiltre : null;
        $userId = $request->query->get('user');
        $proprietaireId = null !== $userId && '' !== $userId ? (int) $userId : null;
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        $d0 = \is_string($dateDebut) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut) ? new \DateTime($dateDebut.' 00:00:00') : null;
        $d1 = \is_string($dateFin) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin) ? new \DateTime($dateFin.' 23:59:59') : null;
        $search = $request->query->getString('q');
        $search = '' !== $search ? $search : null;

        $total = $evenementRepository->countAdmin($typeFiltre, $proprietaireId, $d0, $d1, $search);
        $evenements = $evenementRepository->findAdminPaginated($page, self::LIMIT, $typeFiltre, $proprietaireId, $d0, $d1, $search);
        $pages = (int) max(1, (int) ceil($total / self::LIMIT));

        return $this->render('admin/evenement/index.html.twig', [
            'evenements' => $evenements,
            'users' => $userRepository->findBy([], ['userNom' => 'ASC', 'userPrenom' => 'ASC']),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'typeFiltre' => $typeFiltre ?? '',
            'proprietaireId' => $proprietaireId,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'searchQuery' => $search ?? '',
        ]);
    }

    #[Route('/new', name: 'admin_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PlanningDomainService $domainService): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement, ['admin_mode' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $admin = $this->getUser();
                $domainService->saveEvenement($evenement, $admin instanceof \App\Entity\User ? $admin : null);
                $this->addFlash('success', 'Événement créé.');

                return $this->redirectToRoute('admin_evenement_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/evenement/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('admin/evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_evenement_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, PlanningDomainService $domainService): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement, ['admin_mode' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $admin = $this->getUser();
                $domainService->saveEvenement($evenement, $admin instanceof \App\Entity\User ? $admin : null);
                $this->addFlash('success', 'Événement mis à jour.');

                return $this->redirectToRoute('admin_evenement_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_evenement_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, PlanningDomainService $domainService): Response
    {
        $token = $request->request->getString('_token') ?: $request->getPayload()->getString('_token');
        if ($this->isCsrfTokenValid('admin_delete_evenement'.$evenement->getId(), $token)) {
            try {
                $domainService->removeEvenement($evenement);
                $this->addFlash('success', 'Événement supprimé.');
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_evenement_index', [], Response::HTTP_SEE_OTHER);
    }
}
