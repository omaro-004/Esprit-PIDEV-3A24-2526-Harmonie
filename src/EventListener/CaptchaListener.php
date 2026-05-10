<?php

namespace App\EventListener;

use App\Service\CaptchaService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Intercepte les requêtes POST sur /login AVANT que le pare-feu Symfony
 * (priorité 8) ne les traite, et vérifie le CAPTCHA.
 *
 * Si la vérification échoue :
 *   → Flash d'erreur + redirection vers la page de login (nouveau CAPTCHA généré).
 * Si la vérification réussit :
 *   → La requête continue normalement vers le pare-feu (authentification Symfony).
 */
class CaptchaListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly CaptchaService $captchaService,
        private readonly RouterInterface $router,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Priorité 10 : s'exécute AVANT le pare-feu de sécurité (priorité 8)
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // N'intervenir que sur POST /login (soumission du formulaire de connexion)
        if ($request->getPathInfo() !== '/login' || $request->getMethod() !== 'POST') {
            return;
        }

        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session   = $request->getSession();
        $submitted = (string) $request->request->get('captcha', '');

        $isValid = $this->captchaService->isValid($session, $submitted);

        // Régénérer le CAPTCHA pour la prochaine tentative (quelle que soit l'issue)
        $this->captchaService->generateCaptcha($session);

        if (!$isValid) {
            $session->getFlashBag()->add(
                'captcha_error',
                'Code de vérification incorrect. Veuillez réessayer.'
            );

            // Interrompre la chaîne : le pare-feu ne sera jamais atteint
            $event->setResponse(new RedirectResponse(
                $this->router->generate('app_login')
            ));
        }
        // Si valide, on ne fait rien → la requête continue vers le pare-feu
    }
}
