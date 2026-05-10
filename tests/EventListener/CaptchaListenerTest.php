<?php

namespace App\Tests\EventListener;

use App\EventListener\CaptchaListener;
use App\Service\CaptchaService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class CaptchaListenerTest extends TestCase
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

    public function testSubscribedEvents(): void
    {
        $events = CaptchaListener::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertSame(['onKernelRequest', 10], $events[KernelEvents::REQUEST]);
    }

    public function testIgnoreCheminNonLogin(): void
    {
        $captchaService = $this->createMock(CaptchaService::class);
        $captchaService->expects($this->never())->method('isValid');
        $captchaService->expects($this->never())->method('generateCaptcha');

        $router = $this->createMock(RouterInterface::class);
        $listener = new CaptchaListener($captchaService, $router);

        $request = Request::create('/autre', 'POST');
        $request->setSession($this->createSession());
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testIgnoreMethodeNonPost(): void
    {
        $captchaService = $this->createMock(CaptchaService::class);
        $captchaService->expects($this->never())->method('isValid');
        $captchaService->expects($this->never())->method('generateCaptcha');

        $router = $this->createMock(RouterInterface::class);
        $listener = new CaptchaListener($captchaService, $router);

        $request = Request::create('/login', 'GET');
        $request->setSession($this->createSession());
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testCaptchaValideNeRedirigePas(): void
    {
        $captchaService = $this->createMock(CaptchaService::class);
        $captchaService->expects($this->once())
            ->method('isValid')
            ->willReturn(true);
        $captchaService->expects($this->once())
            ->method('generateCaptcha');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $listener = new CaptchaListener($captchaService, $router);

        $request = Request::create('/login', 'POST', ['captcha' => 'OK']);
        $request->setSession($this->createSession());
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testCaptchaInvalideAjouteFlashEtRedirige(): void
    {
        $captchaService = $this->createMock(CaptchaService::class);
        $captchaService->expects($this->once())
            ->method('isValid')
            ->willReturn(false);
        $captchaService->expects($this->once())
            ->method('generateCaptcha');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('app_login')
            ->willReturn('/login');

        $listener = new CaptchaListener($captchaService, $router);

        $session = $this->createSession();
        $request = Request::create('/login', 'POST', ['captcha' => 'BAD']);
        $request->setSession($session);
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getTargetUrl());

        $messages = $session->getFlashBag()->peek('captcha_error');
        $this->assertCount(1, $messages);
        $this->assertStringStartsWith('Code', $messages[0]);
    }

    public function testTransmetValeurCaptchaSoumise(): void
    {
        $captchaService = $this->createMock(CaptchaService::class);
        $captchaService->expects($this->once())
            ->method('isValid')
            ->with($this->isInstanceOf(Session::class), 'xyz')
            ->willReturn(true);
        $captchaService->expects($this->once())
            ->method('generateCaptcha');

        $router = $this->createMock(RouterInterface::class);
        $listener = new CaptchaListener($captchaService, $router);

        $request = Request::create('/login', 'POST', ['captcha' => 'xyz']);
        $request->setSession($this->createSession());
        $event = $this->createRequestEvent($request);

        $listener->onKernelRequest($event);
    }
}
