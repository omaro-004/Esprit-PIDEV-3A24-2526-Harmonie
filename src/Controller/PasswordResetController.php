<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\TotpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Réinitialisation de mot de passe via Google Authenticator (TOTP natif).
 *
 * Flux en 3 étapes :
 *   1. GET/POST /forgot-password         → saisie de l'email
 *   2. POST     /forgot-password/verify  → vérification du code TOTP à 6 chiffres
 *   3. GET/POST /forgot-password/reset   → saisie + confirmation du nouveau mot de passe
 */
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly UserRepository              $userRepo,
        private readonly EntityManagerInterface      $em,
        private readonly TotpService                 $totp,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 1 — Saisie de l'email
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function step1(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = strtolower(trim($request->request->get('email', '')));
            $user  = $this->userRepo->findOneBy(['userEmail' => $email]);

            if ($user) {
                // Générer un secret TOTP si l'utilisateur n'en a pas encore
                $isNew = false;
                if (!$user->getTotpSecret()) {
                    $secret = $this->totp->generateSecret();
                    $user->setTotpSecret($secret);
                    $this->em->flush();
                    $isNew = true;
                }

                // Stocker l'email en session pour les étapes suivantes
                $request->getSession()->set('reset_email', $email);

                return $this->render('security/forgot_password.html.twig', [
                    'step'     => 'verify',
                    'email'    => $email,
                    'isNew'    => $isNew,
                    'qrUri'    => $isNew
                        ? $this->totp->getProvisioningUri($user->getTotpSecret(), $email)
                        : null,
                ]);
            }

            // Email inconnu — on affiche le même écran pour ne pas divulguer l'existence du compte
            return $this->render('security/forgot_password.html.twig', [
                'step'  => 'verify',
                'email' => $email,
                'isNew' => true,
                'qrUri' => null, // pas de QR réel → l'utilisateur ne peut pas continuer
                'error' => 'Aucun compte trouvé pour cet email.',
            ]);
        }

        return $this->render('security/forgot_password.html.twig', [
            'step' => 'email',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 2 — Vérification du code TOTP
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/forgot-password/verify', name: 'app_forgot_password_verify', methods: ['POST'])]
    public function step2(Request $request): Response
    {
        $session = $request->getSession();
        $email   = $session->get('reset_email');

        if (!$email) {
            return $this->redirectToRoute('app_forgot_password');
        }

        $code = trim($request->request->get('totp_code', ''));
        $user = $this->userRepo->findOneBy(['userEmail' => $email]);

        if (!$user || !$user->getTotpSecret()) {
            return $this->redirectToRoute('app_forgot_password');
        }

        if (!$this->totp->verify($user->getTotpSecret(), $code)) {
            return $this->render('security/forgot_password.html.twig', [
                'step'  => 'verify',
                'email' => $email,
                'isNew' => false,
                'qrUri' => null,
                'error' => 'Code incorrect ou expiré. Vérifiez votre application et réessayez.',
            ]);
        }

        // ✅ Code valide → autoriser la réinitialisation
        $session->set('reset_verified', true);

        return $this->redirectToRoute('app_forgot_password_reset');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 3 — Nouveau mot de passe
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/forgot-password/reset', name: 'app_forgot_password_reset', methods: ['GET', 'POST'])]
    public function step3(Request $request): Response
    {
        $session  = $request->getSession();
        $email    = $session->get('reset_email');
        $verified = $session->get('reset_verified', false);

        if (!$email || !$verified) {
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPwd  = $request->request->get('new_password', '');
            $confirm = $request->request->get('confirm_password', '');
            $errors  = [];

            if (strlen($newPwd) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
            if (!preg_match('/[A-Z]/', $newPwd)) {
                $errors[] = 'Le mot de passe doit contenir au moins 1 lettre majuscule.';
            }
            if (!preg_match('/[a-z]/', $newPwd)) {
                $errors[] = 'Le mot de passe doit contenir au moins 1 lettre minuscule.';
            }
            if (!preg_match('/\d/', $newPwd)) {
                $errors[] = 'Le mot de passe doit contenir au moins 1 chiffre.';
            }
            if (!preg_match('/[\W_]/', $newPwd)) {
                $errors[] = 'Le mot de passe doit contenir au moins 1 caractère spécial.';
            }
            if ($newPwd !== $confirm) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (!empty($errors)) {
                return $this->render('security/forgot_password.html.twig', [
                    'step'   => 'reset',
                    'email'  => $email,
                    'errors' => $errors,
                ]);
            }

            $user = $this->userRepo->findOneBy(['userEmail' => $email]);
            if ($user) {
                $user->setUserPassword($this->hasher->hashPassword($user, $newPwd));
                $this->em->flush();
            }

            // Nettoyer la session
            $session->remove('reset_email');
            $session->remove('reset_verified');

            $this->addFlash('success', '✅ Mot de passe mis à jour ! Connectez-vous avec votre nouveau mot de passe.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'step'  => 'reset',
            'email' => $email,
        ]);
    }
}
