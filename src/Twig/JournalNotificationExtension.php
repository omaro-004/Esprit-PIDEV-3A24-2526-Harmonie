<?php

namespace App\Twig;

use App\Repository\JournalHumeurRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class JournalNotificationExtension extends AbstractExtension
{
    public function __construct(private readonly JournalHumeurRepository $repo) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_journal_count', [$this, 'getUnreadCount']),
        ];
    }

    public function getUnreadCount(): int
    {
        return $this->repo->countUnreadByAdmin();
    }
}
