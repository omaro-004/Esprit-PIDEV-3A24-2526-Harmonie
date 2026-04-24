<?php

namespace App\EventListener;

use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class FaceAuthListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly UserRepository  $userRepo,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => ['onLogin', 0],
            KernelEvents::REQUEST             => ['onRequest', 7],
        ];
    }

    /**
     * Après un login réussi : si face ID activé → stocker userId en session
     * et marquer que la vérification est en attente
     */
    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof \App\Entity\User) {
            return;
        }

        if ($user->isFaceIdEnabled() && $user->getFaceImagePath()) {
            $session = $event->getRequest()->getSession();
            $session->set('pending_face_user_id', $user->getUserId());
            $session->set('face_verified', false);
        }
    }

    /**
     * Sur chaque requête : si pending_face_user_id existe et face_verified = false
     * → rediriger vers la page de vérification faciale
     */
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $pendingUserId  = $session->get('pending_face_user_id');
        $faceVerified   = $session->get('face_verified', true);

        if (!$pendingUserId || $faceVerified) {
            return;
        }

        $path = $request->getPathInfo();

        // Autoriser ces routes pendant la vérification
        $allowed = [
            '/face-verify',
            '/face-verify/check',
            '/face-verify/success',
            '/logout',
        ];

        foreach ($allowed as $allowedPath) {
            if (str_starts_with($path, $allowedPath)) {
                return;
            }
        }

        // Autoriser aussi les assets
        if (str_starts_with($path, '/_') || str_starts_with($path, '/css') ||
            str_starts_with($path, '/js') || str_starts_with($path, '/images') ||
            str_starts_with($path, '/face_data')) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->router->generate('face_verify')));
    }
}
