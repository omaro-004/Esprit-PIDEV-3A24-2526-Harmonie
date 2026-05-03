<?php

namespace App\Controller;

use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleCalendarController extends AbstractController
{
    #[Route('/oauth/google/connect', name: 'app_google_calendar_connect')]
    public function connect(GoogleCalendarService $googleService): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $authUrl = $googleService->getAuthUrl();
        if ('#' === $authUrl || '' === $authUrl) {
            $this->addFlash('error', 'Google Calendar non configuré (bibliothèque manquante ou identifiants OAuth absents).');
            return $this->redirectToRoute('app_evenement_index');
        }

        return $this->redirect($authUrl);
    }

    #[Route('/oauth/google/callback', name: 'app_google_calendar_callback')]
    public function callback(Request $request, GoogleCalendarService $googleService): Response
    {
        $code = $request->query->get('code');
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();

        if ($code && $user) {
            $success = $googleService->authenticate($code, $user);
            if ($success) {
                $this->addFlash('success', 'Connecté à Google Calendar avec succès !');
            } else {
                $this->addFlash('error', 'Erreur lors de la connexion à Google Calendar.');
            }
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/oauth/google/disconnect', name: 'app_google_calendar_disconnect')]
    public function disconnect(EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if ($user) {
            $user->setGoogleAccessToken(null);
            $user->setGoogleRefreshToken(null);
            $user->setGoogleTokenExpiresAt(null);
            $em->flush();
            $this->addFlash('success', 'Déconnexion de Google Calendar réussie.');
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/oauth/google/pull', name: 'app_google_calendar_pull')]
    public function pull(GoogleCalendarService $googleService): Response
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if ($user && $googleService->pullEventsFromGoogle($user)) {
            $this->addFlash('success', 'Calendrier synchronisé avec succès depuis Google !');
        } else {
            $this->addFlash('error', 'Impossible de récupérer les événements Google.');
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/webhook/google-calendar', name: 'app_google_calendar_webhook', methods: ['POST'])]
    public function webhook(Request $request, EntityManagerInterface $em): Response
    {
        $channelId = $request->headers->get('X-Goog-Channel-ID');

        if ($channelId) {
            return new Response('OK', 200);
        }

        return new Response('Missing headers', 400);
    }
}
