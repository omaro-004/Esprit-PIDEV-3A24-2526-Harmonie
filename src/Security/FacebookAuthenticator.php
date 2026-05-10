<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\FacebookUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class FacebookAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry         $clientRegistry,
        private readonly EntityManagerInterface  $em,
        private readonly RouterInterface         $router,
        private readonly UserRepository          $userRepo,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_facebook_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client      = $this->clientRegistry->getClient('facebook');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var FacebookUser $fbUser */
                $fbUser = $client->fetchUserFromToken($accessToken);

                $facebookId = $fbUser->getId();
                $email      = $fbUser->getEmail();

                // 1. Compte déjà lié à Facebook ?
                /** @var User|null $user */
                $user = $this->userRepo->findOneBy(['facebookId' => $facebookId]);
                if ($user instanceof User) {
                    return $user;
                }

                // 2. Compte existant avec cet email ?
                if ($email) {
                    /** @var User|null $user */
                    $user = $this->userRepo->findOneBy(['userEmail' => $email]);
                    if ($user instanceof User) {
                        $user->setFacebookId($facebookId);
                        if (!$user->getOauthAvatarUrl()) {
                            $user->setOauthAvatarUrl($fbUser->getPictureUrl());
                        }
                        $this->em->flush();
                        return $user;
                    }
                }

                // 3. Nouveau compte
                $firstName = $fbUser->getFirstName() ?? 'Facebook';
                $lastName  = $fbUser->getLastName()  ?? 'User';

                // Facebook peut ne pas fournir l'email (permissions)
                $userEmail = $email ?? ('fb_' . $facebookId . '@noemail.harmony');

                $user = new User();
                $user->setFacebookId($facebookId);
                $user->setUserEmail($userEmail);
                $user->setUserNom($lastName);
                $user->setUserPrenom($firstName);
                $user->setUserDateDeNaissance('2000-01-01');
                $user->setDateInscription((new \DateTime())->format('Y-m-d'));
                $user->setTypeUtilisateur('ETUDIANT');
                $user->setIsActive(true);
                $user->setFaceIdEnabled(false);
                $user->setOauthAvatarUrl($fbUser->getPictureUrl());
                $user->setUserPassword('oauth_' . bin2hex(random_bytes(20)));

                $this->em->persist($user);
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('homepage'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add(
            'error',
            'Connexion Facebook échouée : ' . strtr($exception->getMessageKey(), $exception->getMessageData())
        );
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
