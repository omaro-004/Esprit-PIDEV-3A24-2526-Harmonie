<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticator dédié uniquement au login Google.
 * Il utilise le client OAuth "google_login" (séparé du flux Google Calendar).
 */
class GoogleLoginAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly UserRepository $userRepo,
    ) {}

    /**
     * Activation uniquement sur la route callback de login Google.
     */
    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google_login');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();
                if ($email === null) {
                    throw new AuthenticationException('Email Google manquant.');
                }
                $googleId = $googleUser->getId();

                // 1) Si utilisateur existant par googleId → connexion directe.
                $user = $this->userRepo->findOneBy(['googleId' => $googleId]);
                if ($user instanceof User) {
                    if (!$user->getOauthAvatarUrl()) {
                        $user->setOauthAvatarUrl($googleUser->getAvatar());
                        $this->em->flush();
                    }
                    return $user;
                }

                // 2) Si utilisateur existant par email → connexion directe + liaison googleId.
                if ($email) {
                    $user = $this->userRepo->findOneBy(['userEmail' => $email]);
                    if ($user instanceof User) {
                        if (!$user->getGoogleId()) {
                            $user->setGoogleId($googleId);
                        }
                        if (!$user->getOauthAvatarUrl()) {
                            $user->setOauthAvatarUrl($googleUser->getAvatar());
                        }
                        $this->em->flush();

                        return $user;
                    }
                }

                // 3) Sinon création automatique du compte.
                $name = (string) $googleUser->getName();
                if ($name === '') {
                    $name = 'Google User';
                }
                $nameParts = explode(' ', $name, 2);
                $prenom = $nameParts[0];
                $nom = $nameParts[1] ?? 'User';
                $userEmail = $email;

                $user = new User();
                $user->setGoogleId($googleId);
                $user->setUserEmail($userEmail);
                $user->setUserNom($nom);
                $user->setUserPrenom($prenom);
                $user->setUserDateDeNaissance('2000-01-01');
                $user->setDateInscription((new \DateTime())->format('Y-m-d'));
                $user->setTypeUtilisateur('ETUDIANT');
                $user->setIsActive(true);
                $user->setFaceIdEnabled(false);
                $user->setOauthAvatarUrl($googleUser->getAvatar());
                // Mot de passe inutilisable (compte OAuth).
                $user->setUserPassword('oauth_' . bin2hex(random_bytes(20)));

                $this->em->persist($user);
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirection vers une route existante après login Google.
        return new RedirectResponse($this->router->generate('homepage'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add(
            'error',
            'Connexion Google échouée : ' . strtr($exception->getMessageKey(), $exception->getMessageData())
        );

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
