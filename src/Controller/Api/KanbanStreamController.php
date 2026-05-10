<?php

namespace App\Controller\Api;

use App\Service\Kanban\KanbanRealtimeNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class KanbanStreamController extends AbstractController
{
    #[Route('/api/kanban/stream', name: 'api_kanban_stream', methods: ['GET'])]
    public function events(Request $request, KanbanRealtimeNotifier $notifier): StreamedResponse
    {
        $lastId = (int) $request->headers->get('Last-Event-ID', '0');

        $response = new StreamedResponse(function () use ($notifier, $lastId): void {
            @set_time_limit(30);
            $seen = $lastId;
            $start = time();

            while ((time() - $start) < 25) {
                $event = $notifier->getLastEvent();
                if (is_array($event) && (int) ($event['id'] ?? 0) > $seen) {
                    $seen = (int) $event['id'];
                    echo 'id: '.$seen."\n";
                    echo "event: kanban-update\n";
                    echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE)."\n\n";
                    @ob_flush();
                    flush();
                }
                usleep(400000);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
