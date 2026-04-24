<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DebugRequestListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST   => ['onRequest', 5],
            KernelEvents::RESPONSE  => ['onResponse', 0],
            KernelEvents::EXCEPTION => ['onException', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_contains($request->getPathInfo(), '/api/messaging/send')) {
            return;
        }
        error_log('=== MESSAGING SEND REQUEST ===');
        error_log('Method: ' . $request->getMethod());
        error_log('Content: ' . $request->getContent());
        error_log('ContentType: ' . $request->headers->get('Content-Type'));
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_contains($request->getPathInfo(), '/api/messaging/send')) {
            return;
        }
        error_log('=== MESSAGING SEND RESPONSE ===');
        error_log('Status: ' . $event->getResponse()->getStatusCode());
        error_log('Content: ' . substr($event->getResponse()->getContent(), 0, 500));
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_contains($request->getPathInfo(), '/api/messaging/send')) {
            return;
        }
        error_log('=== MESSAGING SEND EXCEPTION ===');
        error_log('Exception: ' . $event->getThrowable()->getMessage());
        error_log('Class: ' . get_class($event->getThrowable()));
        error_log('File: ' . $event->getThrowable()->getFile());
        error_log('Line: ' . $event->getThrowable()->getLine());
    }
}
