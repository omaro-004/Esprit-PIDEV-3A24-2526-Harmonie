<?php

namespace App\Service\Telegram;

use App\Entity\Evenement;
use App\Entity\Tache;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class TelegramNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(string:TELEGRAM_BOT_TOKEN)%')]
        private readonly string $botToken,
        #[Autowire('%env(string:TELEGRAM_CHAT_ID)%')]
        private readonly string $chatId,
    ) {
    }

    public function notifyTaskCreated(Tache $task, bool $viaAssistant = false): void
    {
        $lines = [
            '✅ <b>Nouvelle tâche créée</b>',
            '📌 Titre : '.$this->e((string) $task->getNom()),
            '📋 Colonne : '.$this->statusLabel((string) $task->getStatutTache()),
        ];

        if ($task->getDeadline()) {
            $lines[] = '📅 Échéance : '.$task->getDeadline()->format('Y-m-d');
        }

        if ($task->getGithubIssueNumber()) {
            $lines[] = '🔗 GitHub Issue : #'.$task->getGithubIssueNumber();
        }

        $this->notify(implode("\n", $lines), $viaAssistant);
    }

    public function notifyTaskMoved(Tache $task, string $fromStatus, string $toStatus, bool $viaAssistant = false): void
    {
        $message = "🔄 <b>Tâche déplacée</b>\n"
            .'📌 Titre : '.$this->e((string) $task->getNom())."\n"
            .'➡️ '.$this->statusLabel($fromStatus).' → '.$this->statusLabel($toStatus);

        $this->notify($message, $viaAssistant);
    }

    public function notifyTaskDone(Tache $task, bool $viaAssistant = false): void
    {
        $message = "🎉 <b>Tâche terminée !</b>\n"
            .'📌 Titre : '.$this->e((string) $task->getNom())."\n"
            .'✅ Marquée comme terminée';

        $this->notify($message, $viaAssistant);
    }

    public function notifyTaskUpdated(Tache $task, bool $viaAssistant = false): void
    {
        $message = "✏️ <b>Tâche modifiée</b>\n"
            .'📌 Titre : '.$this->e((string) $task->getNom())."\n"
            .'🔄 Modifiée le : '.(new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->notify($message, $viaAssistant);
    }

    public function notifyTaskDeleted(string $title, bool $viaAssistant = false): void
    {
        $message = "🗑️ <b>Tâche supprimée</b>\n"
            .'📌 Titre : '.$this->e($title);

        $this->notify($message, $viaAssistant);
    }

    public function notifyEventCreated(Evenement $event, bool $viaAssistant = false): void
    {
        $lines = [
            '📅 <b>Nouvel événement créé</b>',
            '📌 Titre : '.$this->e((string) $event->getTitre()),
            '🗓 Date : '.$this->formatDate($event->getDateDebut()),
            '🕐 Heure : '.$this->formatTime($event->getDateDebut()).' → '.$this->formatTime($event->getDateFin()),
        ];

        if ($event->getLieu()) {
            $lines[] = '📍 Lieu : '.$this->e((string) $event->getLieu());
        }

        $this->notify(implode("\n", $lines), $viaAssistant);
    }

    public function notifyEventUpdated(Evenement $event, bool $viaAssistant = false): void
    {
        $message = "✏️ <b>Événement modifié</b>\n"
            .'📌 Titre : '.$this->e((string) $event->getTitre())."\n"
            .'🗓 Nouvelle date : '.$this->formatDate($event->getDateDebut())."\n"
            .'🕐 Nouvelle heure : '.$this->formatTime($event->getDateDebut()).' → '.$this->formatTime($event->getDateFin())."\n"
            .'🔄 Modifié le : '.(new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->notify($message, $viaAssistant);
    }

    public function notifyEventDeleted(string $title, ?\DateTimeInterface $startAt = null, bool $viaAssistant = false): void
    {
        $lines = [
            '🗑️ <b>Événement supprimé</b>',
            '📌 Titre : '.$this->e($title),
        ];

        if ($startAt) {
            $lines[] = '🗓 Date : '.$startAt->format('Y-m-d');
        }

        $this->notify(implode("\n", $lines), $viaAssistant);
    }

    private function notify(string $message, bool $viaAssistant = false): void
    {
        if ('' === trim($this->botToken) || '' === trim($this->chatId)) {
            return;
        }

        $fullMessage = $viaAssistant
            ? "🤖 <b>Action via Harmonie Assistant</b>\n".$message
            : $message;

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', rawurlencode($this->botToken));

        $attempt = 0;
        $maxAttempts = 2;

        while ($attempt < $maxAttempts) {
            ++$attempt;
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'chat_id' => $this->chatId,
                        'text' => $fullMessage,
                        'parse_mode' => 'HTML',
                    ],
                    'timeout' => 4,
                ]);

                $status = $response->getStatusCode();
                if ($status >= 200 && $status < 300) {
                    return;
                }

                if ($attempt >= $maxAttempts) {
                    $this->logger->warning('Telegram notification failed with status code.', [
                        'status' => $status,
                    ]);
                }
            } catch (TransportExceptionInterface $e) {
                if ($attempt >= $maxAttempts) {
                    $this->logger->warning('Telegram transport error.', ['error' => $e->getMessage()]);
                }
                usleep(200_000);
            } catch (\Throwable $e) {
                $this->logger->warning('Telegram notification error.', ['error' => $e->getMessage()]);
                return;
            }
        }
    }

    private function statusLabel(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'TERMINEE', 'TERMINE', 'DONE' => 'DONE',
            'EN_COURS', 'DOING' => 'DOING',
            default => 'TODO',
        };
    }

    private function formatDate(?\DateTimeInterface $dt): string
    {
        return $dt ? $dt->format('Y-m-d') : '-';
    }

    private function formatTime(?\DateTimeInterface $dt): string
    {
        return $dt ? $dt->format('H:i') : '-';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
