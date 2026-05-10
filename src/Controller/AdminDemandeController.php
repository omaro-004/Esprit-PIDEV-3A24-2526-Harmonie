<?php

namespace App\Controller;

use App\Entity\DemandeReservation;
use App\Repository\DemandeReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/demandes')]
#[IsGranted('ROLE_ADMIN')]
final class AdminDemandeController extends AbstractController
{
    private const LIMIT = 10;

    private function csrfFromRequest(Request $request): string
    {
        $t = $request->request->getString('_token');
        if ('' !== $t) {
            return $t;
        }

        return $request->getPayload()->getString('_token');
    }

    #[Route('', name: 'admin_demande_index', methods: ['GET'])]
    public function index(Request $request, DemandeReservationRepository $demandeReservationRepository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $statut = $request->query->getString('statut');
        $statutFiltre = '' !== $statut ? $statut : null;
        $search = $request->query->getString('q');
        $search = '' !== $search ? $search : null;

        $total = $demandeReservationRepository->countAdmin($statutFiltre, $search);
        $demandes = $demandeReservationRepository->findAdminPaginated($page, self::LIMIT, $statutFiltre, $search);
        $pages = (int) max(1, (int) ceil($total / self::LIMIT));

        return $this->render('admin/demande/index.html.twig', [
            'demandes' => $demandes,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'statutFiltre' => $statutFiltre ?? '',
            'searchQuery' => $search ?? '',
        ]);
    }

    #[Route('/{id}/accepter', name: 'admin_demande_accepter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accepter(Request $request, DemandeReservation $demande, EntityManagerInterface $em): Response
    {
        $token = $this->csrfFromRequest($request);
        if (!$this->isCsrfTokenValid('admin_demande_action'.$demande->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_demande_index', [], Response::HTTP_SEE_OTHER);
        }

        if (DemandeReservation::STATUT_EN_ATTENTE !== $demande->getStatut()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'Statut invalide'], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('danger', 'Cette demande n’est plus en attente.');

            return $this->redirectToRoute('admin_demande_index', [], Response::HTTP_SEE_OTHER);
        }

        $demande->setStatut(DemandeReservation::STATUT_ACCEPTEE);
        $demande->setCommentaireAdmin(null);
        $em->persist($demande);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'ok' => true,
                'statut' => DemandeReservation::STATUT_ACCEPTEE,
                'label' => 'Acceptée',
            ]);
        }

        $this->addFlash('success', 'Demande acceptée.');

        return $this->redirectToRoute('admin_demande_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/refuser', name: 'admin_demande_refuser', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuser(Request $request, DemandeReservation $demande, EntityManagerInterface $em): Response
    {
        $token = $this->csrfFromRequest($request);
        if (!$this->isCsrfTokenValid('admin_demande_action'.$demande->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'CSRF'], Response::HTTP_FORBIDDEN);
            }
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin_demande_index', [], Response::HTTP_SEE_OTHER);
        }

        if (DemandeReservation::STATUT_EN_ATTENTE !== $demande->getStatut()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['ok' => false, 'error' => 'Statut invalide'], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('danger', 'Cette demande n’est plus en attente.');

            return $this->redirectToRoute('admin_demande_index', [], Response::HTTP_SEE_OTHER);
        }

        $commentaire = $request->request->getString('commentaire_admin');
        if ('' === $commentaire && $request->getContentTypeFormat() === 'json') {
            $data = json_decode($request->getContent(), true);
            if (\is_array($data) && isset($data['commentaire_admin'])) {
                $commentaire = (string) $data['commentaire_admin'];
            }
        }

        $demande->setStatut(DemandeReservation::STATUT_REFUSEE);
        $demande->setCommentaireAdmin('' !== $commentaire ? $commentaire : null);
        $em->persist($demande);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'ok' => true,
                'statut' => DemandeReservation::STATUT_REFUSEE,
                'label' => 'Refusée',
                'commentaire' => $demande->getCommentaireAdmin(),
            ]);
        }

        $this->addFlash('success', 'Demande refusée.');

        return $this->redirectToRoute('admin_demande_index', [], Response::HTTP_SEE_OTHER);
    }
}
