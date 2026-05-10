<?php

namespace App\Controller;

use App\Service\CaptchaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly CaptchaService $captchaService,
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils, Request $request): Response
    {
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            return $this->redirectToRoute('homepage');
        }

        // Générer un CAPTCHA frais à chaque affichage de la page de login
        // (au chargement initial ET après chaque redirection depuis le listener)
        $captchaQuestion = $this->captchaService->generateCaptcha($request->getSession());

        return $this->render('security/login.html.twig', [
            'last_username'    => $authUtils->getLastUsername(),
            'error'            => $authUtils->getLastAuthenticationError(),
            'captcha_question' => $captchaQuestion,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // intercepted by Symfony firewall
    }
}
