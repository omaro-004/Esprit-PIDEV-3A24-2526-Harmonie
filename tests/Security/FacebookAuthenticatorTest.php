<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\FacebookAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class FacebookAuthenticatorTest extends TestCase
{
    private function createSession(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    private function createRequest(string $route): Request
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        $request->setSession($this->createSession());
        return $request;
    }

    private function createClientWithUser(object $oauthUser): OAuth2ClientInterface
    {
        return new class($oauthUser) implements OAuth2ClientInterface {
            private object $user;

            public function __construct(object $user)
            {
                $this->user = $user;
            }

            public function fetchUserFromToken($accessToken): object
            {
                return $this->user;
            }

            public function setAsStateless()
            {
            }

            public function redirect(array $scopes, array $options)
            {
                return new \Symfony\Component\HttpFoundation\RedirectResponse('/');
            }

            public function getAccessToken(array $options = [])
            {
                return new AccessToken(['access_token' => 'token']);
            }

            public function fetchUser()
            {
                return $this->user;
            }

            public function getOAuth2Provider()
            {
                throw new \RuntimeException('Not used in tests.');
            }
        };
    }

    private function createAuthenticator(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $em,
        RouterInterface $router,
        UserRepository $repo
    ): FacebookAuthenticator {
        return new class($clientRegistry, $em, $router, $repo) extends FacebookAuthenticator {
            protected function fetchAccessToken(\KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface $client, array $options = [])
            {
                return new AccessToken(['access_token' => 'token']);
            }
        };
    }

    public function testSupportsUniquementRouteCallback(): void
    {
        $auth = $this->createAuthenticator(
            $this->createMock(ClientRegistry::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(RouterInterface::class),
            $this->createMock(UserRepository::class)
        );

        $this->assertTrue($auth->supports($this->createRequest('connect_facebook_check')));
        $this->assertFalse($auth->supports($this->createRequest('autre_route')));
    }

    public function testAuthenticateRetourneUtilisateurExistantParFacebookId(): void
    {
        $fbUser = $this->createMock(FacebookUser::class);
        $fbUser->method('getId')->willReturn('fb-123');
        $fbUser->method('getEmail')->willReturn('a@b.com');

        $client = $this->createClientWithUser($fbUser);

        $clientRegistry = $this->createMock(ClientRegistry::class);
        $clientRegistry->method('getClient')->with('facebook')->willReturn($client);

        $existing = new User();
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findOneBy')->willReturnCallback(function (array $criteria) use ($existing) {
            if (isset($criteria['facebookId'])) {
                return $existing;
            }
            return null;
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $router = $this->createMock(RouterInterface::class);

        $auth = $this->createAuthenticator($clientRegistry, $em, $router, $repo);
        $passport = $auth->authenticate($this->createRequest('connect_facebook_check'));

        $badge = $passport->getBadge(UserBadge::class);
        $user = $badge->getUser();

        $this->assertSame($existing, $user);
    }

    public function testAuthenticateLieUtilisateurParEmail(): void
    {
        $fbUser = $this->createMock(FacebookUser::class);
        $fbUser->method('getId')->willReturn('fb-456');
        $fbUser->method('getEmail')->willReturn('user@example.com');
        $fbUser->method('getPictureUrl')->willReturn('http://img/avatar.png');

        $client = $this->createClientWithUser($fbUser);

        $clientRegistry = $this->createMock(ClientRegistry::class);
        $clientRegistry->method('getClient')->with('facebook')->willReturn($client);

        $existing = new User();
        $existing->setUserEmail('user@example.com');

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findOneBy')->willReturnCallback(function (array $criteria) use ($existing) {
            if (isset($criteria['facebookId'])) {
                return null;
            }
            if (isset($criteria['userEmail'])) {
                return $existing;
            }
            return null;
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $router = $this->createMock(RouterInterface::class);

        $auth = $this->createAuthenticator($clientRegistry, $em, $router, $repo);
        $passport = $auth->authenticate($this->createRequest('connect_facebook_check'));

        $user = $passport->getBadge(UserBadge::class)->getUser();

        $this->assertSame('fb-456', $user->getFacebookId());
        $this->assertSame('http://img/avatar.png', $user->getOauthAvatarUrl());
    }

    public function testAuthenticateCreeNouvelUtilisateurSiAucunMatch(): void
    {
        $fbUser = $this->createMock(FacebookUser::class);
        $fbUser->method('getId')->willReturn('fb-789');
        $fbUser->method('getEmail')->willReturn(null);
        $fbUser->method('getFirstName')->willReturn(null);
        $fbUser->method('getLastName')->willReturn(null);
        $fbUser->method('getPictureUrl')->willReturn('http://img/new.png');

        $client = $this->createClientWithUser($fbUser);

        $clientRegistry = $this->createMock(ClientRegistry::class);
        $clientRegistry->method('getClient')->with('facebook')->willReturn($client);

        $repo = $this->createMock(UserRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $em->expects($this->once())->method('flush');

        $router = $this->createMock(RouterInterface::class);

        $auth = $this->createAuthenticator($clientRegistry, $em, $router, $repo);
        $passport = $auth->authenticate($this->createRequest('connect_facebook_check'));

        $user = $passport->getBadge(UserBadge::class)->getUser();

        $this->assertSame('fb-789', $user->getFacebookId());
        $this->assertSame('fb_fb-789@noemail.harmony', $user->getUserEmail());
        $this->assertSame('Facebook', $user->getUserPrenom());
        $this->assertSame('User', $user->getUserNom());
    }

    public function testOnAuthenticationSuccessRedirige(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('homepage')->willReturn('/');

        $auth = $this->createAuthenticator(
            $this->createMock(ClientRegistry::class),
            $this->createMock(EntityManagerInterface::class),
            $router,
            $this->createMock(UserRepository::class)
        );

        $response = $auth->onAuthenticationSuccess(
            $this->createRequest('connect_facebook_check'),
            $this->createMock(TokenInterface::class),
            'main'
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureAjouteFlashEtRedirige(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('app_login')->willReturn('/login');

        $auth = $this->createAuthenticator(
            $this->createMock(ClientRegistry::class),
            $this->createMock(EntityManagerInterface::class),
            $router,
            $this->createMock(UserRepository::class)
        );

        $request = $this->createRequest('connect_facebook_check');
        $response = $auth->onAuthenticationFailure($request, new AuthenticationException('oops'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getTargetUrl());

        $messages = $request->getSession()->getFlashBag()->peek('error');
        $this->assertCount(1, $messages);
        $this->assertStringStartsWith('Connexion', $messages[0]);
    }
}
