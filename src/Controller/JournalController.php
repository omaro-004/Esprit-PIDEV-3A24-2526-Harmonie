<?php

namespace App\Controller;

use App\Entity\JournalHumeur;
use App\Enum\Humeur;
use App\Form\JournalHumeurType;
use App\Repository\JournalHumeurRepository;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/journal')]
class JournalController extends AbstractController
{
    public function __construct(
        private readonly JournalHumeurRepository $repo,
        private readonly EntityManagerInterface  $em,
        private readonly GroqService             $groq,
    ) {}

    #[Route('', name: 'journal', methods: ['GET'])]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $entries = $this->repo->findByUser($user);
        $humeurs = Humeur::cases();
        $stats   = $this->repo->moodStats($user);
        $trend   = $this->repo->scoreTrend($user, 30);
        $dist    = $this->repo->moodDistribution($user);

        return $this->render('journal/index.html.twig', compact('entries', 'humeurs', 'stats', 'trend', 'dist'));
    }

    #[Route('/search', name: 'journal_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q      = (string) $request->query->get('q', '');
        $humeur = (string) $request->query->get('humeur', '');

        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $entries = $this->repo->searchByUser($user, $q, $humeur);

        $data = array_map(fn(JournalHumeur $j) => [
            'id'             => $j->getId(),
            'date'           => $j->getDateJournal()?->format('d/m/Y'),
            'humeur'         => $j->getHumeur()?->value,
            'humeurLabel'    => $j->getHumeur()?->label(),
            'humeurEmoji'    => $j->getHumeur()?->emoji(),
            'score'          => $j->getScore(),
            'contenu'        => $j->getContenu(),
            'avatarImageUrl' => $j->getAvatarImageUrl(),
        ], $entries);

        return new JsonResponse($data);
    }

    #[Route('/stats', name: 'journal_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user  = $this->getUser();
        $stats = $this->repo->moodStats($user);
        $trend = $this->repo->scoreTrend($user, 30);
        $dist  = $this->repo->moodDistribution($user);

        return new JsonResponse([
            'stats' => $stats,
            'trend' => $trend,
            'dist'  => $dist,
        ]);
    }

    #[Route('/transcribe', name: 'journal_transcribe', methods: ['POST'])]
    public function transcribe(Request $request): JsonResponse
    {
        $file = $request->files->get('audio');
        if (!$file) {
            return new JsonResponse(['error' => 'Aucun fichier audio reçu.'], 400);
        }

        $tmpPath = sys_get_temp_dir() . '/groq_audio_' . uniqid() . '.webm';
        copy($file->getRealPath(), $tmpPath);

        try {
            $transcription = $this->groq->transcribeAudio($tmpPath, 'audio/webm');
            $today         = (new \DateTime())->format('Y-m-d');
            $parsed        = $this->groq->parseJournalFromSpeech($transcription, $today);

            return new JsonResponse([
                'transcription' => $transcription,
                'date'          => $parsed['date'],
                'humeur'        => $parsed['humeur'],
                'contenu'       => $parsed['contenu'],
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur de transcription : ' . $e->getMessage()], 500);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    #[Route('/new', name: 'journal_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $entry = new JournalHumeur();
        $avatarUrl = $request->query->get('avatarUrl');
        if ($avatarUrl !== null) {
            $entry->setAvatarImageUrl((string) $avatarUrl);
        }

        $form  = $this->createForm(JournalHumeurType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $entry->setUser($user);
            $this->em->persist($entry);
            $this->em->flush();

            $this->addFlash('success', 'Entrée ajoutée avec succès.');
            return $this->redirectToRoute('journal');
        }

        return $this->render('journal/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'journal_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(JournalHumeur $entry, Request $request): Response
    {
        if ($entry->getUser() !== $this->getUser()) {
            throw new AccessDeniedException('Vous ne pouvez pas modifier cette entrée.');
        }

        $form = $this->createForm(JournalHumeurType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Entrée modifiée avec succès.');
            return $this->redirectToRoute('journal');
        }

        return $this->render('journal/edit.html.twig', [
            'form'  => $form->createView(),
            'entry' => $entry,
        ]);
    }

    #[Route('/{id}/delete', name: 'journal_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(JournalHumeur $entry, Request $request): Response
    {
        if ($entry->getUser() !== $this->getUser()) {
            throw new AccessDeniedException('Vous ne pouvez pas supprimer cette entrée.');
        }

        if ($this->isCsrfTokenValid('delete' . $entry->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($entry);
            $this->em->flush();
            $this->addFlash('success', 'Entrée supprimée avec succès.');
        }

        return $this->redirectToRoute('journal');
    }
}
