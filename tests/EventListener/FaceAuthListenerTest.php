<?php

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\FaceAuthListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\HttpKernel\KernelEvents;

class FaceAuthListenerTest extends TestCase
{
    private function createSession(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    private function createRequestEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        return new RequestEvent($kernel, $request, $type);
    }

    private function setUserId(User $user, int $id): void
    {
        $prop = new \ReflectionProperty(User::class, 'userId');
        $prop->setValue($user, $id);
    }

    public function testSubscribedEvents(): void
    {
        $events = FaceAuthListener::getSubscribedEvents();
        $this->assertArrayHasKey(SecurityEvents::INTERACTIVE_LOGIN, $events);
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testOnLoginIgnoreUtilisateurInvalide(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $listener = new FaceAuthListener($router);

        $request = new Request();
        $request->setSession($this->createSession());

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->createMock(UserInterface::class));

        $event = new InteractiveLoginEvent($request, $token, 'main');
        $listener->onLogin($event);

        $this->assertNull($request->getSession()->get('pending_face_user_id'));
    }

    public function testOnLoginDefinitSessionSiFaceActive(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $listener = new FaceAuthListener($router);

        $user = new User();
        $user->setFaceIdEnabled(true);
        $user->setFaceImagePath('face.png');
        $this->setUserId($user, 123);

        $request = new Request();
        $request->setSession($this->createSession());

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $event = new InteractiveLoginEvent($request, $token, 'main');
        $listener->onLogin($event);

        $session = $request->getSession();
        $this->assertSame(123, $session->get('pending_face_user_id'));
        $this->assertFalse($session->get('face_verified'));
    }

    public function testOnLoginNeDefinitPasSessionSiFaceInactive(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $listener = new FaceAuthListener($router);

        $user = new User();
        $user->setFaceIdEnabled(false);
        $user->setFaceImagePath('face.png');
        $this->setUserId($user, 55);

        $request = new Request();
        $request->setSession($this->createSession());

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $event = new InteractiveLoginEvent($request, $token, 'main');
        $listener->onLogin($event);

        $this->assertNull($request->getSession()->get('pending_face_user_id'));
    }

    public function testOnRequestIgnoreSousRequete(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $listener = new FaceAuthListener($router);

        $request = Request::create('/profile', 'GET');
        $request->setSession($this->createSession());
        $event = $this->createRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        $listener->onRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnRequestRedirigeSiVerificationEnAttente(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->with('face_verify')->willReturn('/face-verify');
        $listener = new FaceAuthListener($router);

        $session = $this->createSession();
        $session->set('pending_face_user_id', 12);
        $session->set('face_verified', false);

        $request = Request::create('/profile', 'GET');
        $request->setSession($session);
        $event = $this->createRequestEvent($request);

        $listener->onRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/face-verify', $response->getTargetUrl());
    }

    public function testOnRequestAutoriseCheminsExemptsEtAssets(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $listener = new FaceAuthListener($router);

        $session = $this->createSession();
        $session->set('pending_face_user_id', 12);
        $session->set('face_verified', false);

        $request = Request::create('/face-verify', 'GET');
        $request->setSession($session);
        $event = $this->createRequestEvent($request);

        $listener->onRequest($event);
        $this->assertNull($event->getResponse());

        $assetRequest = Request::create('/css/app.css', 'GET');
        $assetRequest->setSession($session);
        $assetEvent = $this->createRequestEvent($assetRequest);

        $listener->onRequest($assetEvent);
        $this->assertNull($assetEvent->getResponse());
    }
}
