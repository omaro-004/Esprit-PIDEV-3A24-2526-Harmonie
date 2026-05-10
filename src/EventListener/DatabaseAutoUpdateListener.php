<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DatabaseAutoUpdateListener implements EventSubscriberInterface
{
    private static bool $done = false;

    public function __construct(private readonly EntityManagerInterface $em) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 255]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (self::$done || !$event->isMainRequest()) {
            return;
        }
        self::$done = true;

        try {
            $this->ensureDatabaseExists();
        } catch (\Throwable) {
        }

        try {
            $schemaTool = new SchemaTool($this->em);
            $metadata   = $this->em->getMetadataFactory()->getAllMetadata();
            $schemaTool->updateSchema($metadata);
        } catch (\Throwable) {
        }
    }

    private function ensureDatabaseExists(): void
    {
        $connection = $this->em->getConnection();
        $params     = $connection->getParams();
        $dbName     = $params['dbname'] ?? null;

        if (!$dbName) {
            return;
        }

        $tmpParams = $params;
        unset($tmpParams['dbname'], $tmpParams['url']);

        $tmpConn = \Doctrine\DBAL\DriverManager::getConnection($tmpParams);
        $tmpConn->executeStatement(
            'CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        $tmpConn->close();
    }
}
