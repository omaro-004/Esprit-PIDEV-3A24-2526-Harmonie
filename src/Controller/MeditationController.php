<?php

namespace App\Controller;

use App\Entity\SessionMeditation;
use App\Repository\SessionMeditationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/meditation')]
class MeditationController extends AbstractController
{
    public function __construct(
        private readonly SessionMeditationRepository $repo,
    ) {}

    #[Route('', name: 'meditation', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q    = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir  = $request->query->get('dir', 'DESC');

        $sessions = $this->repo->searchAndSort($q, $sort, $dir);

        return $this->render('meditation/etudiant/index.html.twig', compact('sessions', 'q', 'sort', 'dir'));
    }

    #[Route('/search', name: 'meditation_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q    = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $dir  = $request->query->get('dir', 'DESC');

        $sessions = $this->repo->searchAndSort($q, $sort, $dir);

        $data = array_map(fn(SessionMeditation $s) => [
            'id'            => $s->getId(),
            'auteur'        => $s->getAuteur(),
            'theme'         => $s->getTheme(),
            'duree'         => $s->getDuree(),
            'audioUrl'      => $s->getAudioUrl(),
            'conseilsCount' => $s->getConseils()->count(),
        ], $sessions);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'meditation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(SessionMeditation $session): Response
    {
        return $this->render('meditation/etudiant/show.html.twig', compact('session'));
    }
}
