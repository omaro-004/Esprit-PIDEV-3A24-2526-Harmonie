<?php

namespace App\Controller;

use App\Entity\Conseil;
use App\Entity\SessionMeditation;
use App\Form\ConseilType;
use App\Form\SessionMeditationType;
use App\Repository\SessionMeditationRepository;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/meditation')]
class AdminMeditationController extends AbstractController
{
    public function __construct(
        private readonly SessionMeditationRepository $repo,
        private readonly EntityManagerInterface      $em,
        private readonly GroqService                 $groq,
    ) {}

    #[Route('', name: 'admin_meditation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q    = (string) $request->query->get('q', '');
        $sort = (string) $request->query->get('sort', 'id');
        $dir  = (string) $request->query->get('dir', 'DESC');

        $sessions = $this->repo->searchAndSort($q, $sort, $dir);

        return $this->render('meditation/admin/index.html.twig', compact('sessions', 'q', 'sort', 'dir'));
    }

    #[Route('/search', name: 'admin_meditation_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q    = (string) $request->query->get('q', '');
        $sort = (string) $request->query->get('sort', 'id');
        $dir  = (string) $request->query->get('dir', 'DESC');

        $sessions = $this->repo->searchAndSort($q, $sort, $dir);

        $data = array_map(fn(SessionMeditation $s) => [
            'id'            => $s->getId(),
            'auteur'        => $s->getAuteur(),
            'theme'         => $s->getTheme(),
            'duree'         => $s->getDuree(),
            'audioUrl'      => $s->getAudioUrl(),
            'conseilsCount' => $s->getConseils()->count(),
        ], $sessions);

        return new JsonResponse($data);
    }

    #[Route('/new', name: 'admin_meditation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $session = new SessionMeditation();
        $form    = $this->createForm(SessionMeditationType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $session->setUser($user);
            }
            $this->em->persist($session);

            $aiConseilsJson = (string) $request->request->get('ai_conseils', '');
            if ($aiConseilsJson !== '') {
                $aiConseils = json_decode($aiConseilsJson, true);
                if (is_array($aiConseils)) {
                    foreach ($aiConseils as $contenu) {
                        $contenu = trim((string) $contenu);
                        if (strlen($contenu) >= 5) {
                            $conseil = new Conseil();
                            $conseil->setSession($session);
                            $conseil->setContenu($contenu);
                            $this->em->persist($conseil);
                        }
                    }
                }
            }

            $this->em->flush();

            $this->addFlash('success', 'Session de méditation créée avec succès.');
            return $this->redirectToRoute('admin_meditation_show', ['id' => $session->getId()]);
        }

        return $this->render('meditation/admin/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_meditation_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(SessionMeditation $session): Response
    {
        return $this->render('meditation/admin/show.html.twig', compact('session'));
    }

    #[Route('/{id}/edit', name: 'admin_meditation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(SessionMeditation $session, Request $request): Response
    {
        $form = $this->createForm(SessionMeditationType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Session modifiée avec succès.');
            return $this->redirectToRoute('admin_meditation_show', ['id' => $session->getId()]);
        }

        return $this->render('meditation/admin/edit.html.twig', [
            'form'    => $form->createView(),
            'session' => $session,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_meditation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(SessionMeditation $session, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $session->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($session);
            $this->em->flush();
            $this->addFlash('success', 'Session supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_meditation_index');
    }

    #[Route('/generate', name: 'admin_meditation_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $theme = trim((string) $request->request->get('theme', ''));
        if ($theme === '') {
            return new JsonResponse(['error' => 'Le thème est requis.'], 400);
        }

        try {
            return new JsonResponse($this->groq->generateMeditation($theme));
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur IA : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/regenerate-conseils', name: 'admin_meditation_regenerate_conseils', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function regenerateConseils(SessionMeditation $session): JsonResponse
    {
        try {
            $existing    = implode(' | ', $session->getConseils()->map(fn($c) => $c->getContenu())->toArray());
            $newConseils = $this->groq->generateConseils($session->getTheme() ?? '', $existing);

            foreach ($session->getConseils() as $old) {
                $this->em->remove($old);
            }
            $this->em->flush();

            foreach ($newConseils as $contenu) {
                if (strlen(trim($contenu)) >= 5) {
                    $conseil = new Conseil();
                    $conseil->setSession($session);
                    $conseil->setContenu($contenu);
                    $this->em->persist($conseil);
                }
            }
            $this->em->flush();

            $saved = $session->getConseils()->map(fn($c) => [
                'id'      => $c->getId(),
                'contenu' => $c->getContenu(),
            ])->toArray();

            return new JsonResponse(['conseils' => array_values($saved)]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur IA : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/regenerate-session', name: 'admin_meditation_regenerate_session', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function regenerateSession(SessionMeditation $session): JsonResponse
    {
        try {
            $data = $this->groq->generateMeditation($session->getTheme() ?? '');

            if (!empty($data['auteur']))   $session->setAuteur($data['auteur']);
            if (!empty($data['duree']))    $session->setDuree($data['duree']);
            if (!empty($data['audioUrl'])) $session->setAudioUrl($data['audioUrl']);

            $this->em->flush();

            return new JsonResponse([
                'auteur'      => $session->getAuteur(),
                'duree'       => $session->getDuree(),
                'audioUrl'    => $session->getAudioUrl(),
                'searchQuery' => $data['searchQuery'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur IA : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/conseil/new', name: 'admin_conseil_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function newConseil(SessionMeditation $session, Request $request): Response
    {
        $conseil = new Conseil();
        $conseil->setSession($session);
        $form = $this->createForm(ConseilType::class, $conseil);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($conseil);
            $this->em->flush();

            $this->addFlash('success', 'Conseil ajouté avec succès.');
            return $this->redirectToRoute('admin_meditation_show', ['id' => $session->getId()]);
        }

        return $this->render('meditation/admin/conseil_form.html.twig', [
            'form'    => $form->createView(),
            'session' => $session,
            'editing' => false,
        ]);
    }

    #[Route('/conseil/{id}/edit', name: 'admin_conseil_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editConseil(Conseil $conseil, Request $request): Response
    {
        $form = $this->createForm(ConseilType::class, $conseil);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Conseil modifié avec succès.');
            return $this->redirectToRoute('admin_meditation_show', ['id' => $conseil->getSession()?->getId()]);
        }

        return $this->render('meditation/admin/conseil_form.html.twig', [
            'form'    => $form->createView(),
            'session' => $conseil->getSession(),
            'editing' => true,
        ]);
    }

    #[Route('/conseil/{id}/delete', name: 'admin_conseil_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteConseil(Conseil $conseil, Request $request): Response
    {
        $sessionId = $conseil->getSession()?->getId();

        if ($this->isCsrfTokenValid('delete' . $conseil->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($conseil);
            $this->em->flush();
            $this->addFlash('success', 'Conseil supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_meditation_show', ['id' => $sessionId]);
    }

    #[Route('/pdf', name: 'admin_meditation_pdf_list', methods: ['GET'])]
    public function pdfList(): Response
    {
        $sessions = $this->repo->searchAndSort();
        $html     = $this->renderView('meditation/admin/pdf_list.html.twig', compact('sessions'));

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="meditations.pdf"',
        ]);
    }

    #[Route('/{id}/pdf', name: 'admin_meditation_pdf_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function pdfDetail(SessionMeditation $session): Response
    {
        $html = $this->renderView('meditation/admin/pdf_detail.html.twig', compact('session'));

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="meditation_' . $session->getId() . '.pdf"',
        ]);
    }
}
