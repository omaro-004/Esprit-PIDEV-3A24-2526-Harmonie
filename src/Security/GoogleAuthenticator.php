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

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry        $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface        $router,
        private readonly UserRepository         $userRepo,
    ) {}

    /**
     * Cet authenticator ne s'active que sur la route de callback Google
     */
    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $googleId = $googleUser->getId();
                $email    = $googleUser->getEmail();

                // 1. L'utilisateur a déjà lié son compte Google ?
                $user = $this->userRepo->findOneBy(['googleId' => $googleId]);
                if ($user) {
                    return $user;
                }

                // 2. Un compte existe avec ce même email ?
                $user = $this->userRepo->findOneBy(['userEmail' => $email]);
                if ($user) {
                    // Lier le compte Google à l'utilisateur existant
                    $user->setGoogleId($googleId);
                    if (!$user->getOauthAvatarUrl()) {
                        $user->setOauthAvatarUrl($googleUser->getAvatar());
                    }
                    $this->em->flush();
                    return $user;
                }

                // 3. Aucun compte → créer un nouvel utilisateur
                $nameParts = explode(' ', $googleUser->getName() ?? 'Google User', 2);
                $prenom    = $nameParts[0] ?? 'Google';
                $nom       = $nameParts[1] ?? 'User';

                $user = new User();
                $user->setGoogleId($googleId);
                $user->setUserEmail($email);
                $user->setUserNom($nom);
                $user->setUserPrenom($prenom);
                $user->setUserDateDeNaissance('2000-01-01'); // valeur par défaut
                $user->setDateInscription((new \DateTime())->format('Y-m-d'));
                $user->setTypeUtilisateur('ETUDIANT');
                $user->setIsActive(true);
                $user->setFaceIdEnabled(false);
                $user->setOauthAvatarUrl($googleUser->getAvatar());
                // Pas de mot de passe local : on stocke un hash aléatoire inutilisable
                $user->setUserPassword('oauth_' . bin2hex(random_bytes(20)));

                $this->em->persist($user);
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Rediriger vers la homepage après succès
        return new RedirectResponse($this->router->generate('homepage'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add(
            'error',
            'Connexion Google échouée : ' . strtr($exception->getMessageKey(), $exception->getMessageData())
        );
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
