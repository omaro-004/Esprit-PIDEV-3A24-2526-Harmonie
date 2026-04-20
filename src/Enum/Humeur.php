<?php

namespace App\Enum;

enum Humeur: string
{
    case TRES_BIEN = 'TRES_BIEN';
    case BIEN      = 'BIEN';
    case NEUTRE    = 'NEUTRE';
    case MAL       = 'MAL';
    case TRES_MAL  = 'TRES_MAL';

    public function label(): string
    {
        return match ($this) {
            self::TRES_BIEN => 'Très bien',
            self::BIEN      => 'Bien',
            self::NEUTRE    => 'Neutre',
            self::MAL       => 'Mal',
            self::TRES_MAL  => 'Très mal',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::TRES_BIEN => '😄',
            self::BIEN      => '🙂',
            self::NEUTRE    => '😐',
            self::MAL       => '😔',
            self::TRES_MAL  => '😢',
        };
    }

    public function score(): int
    {
        return match ($this) {
            self::TRES_BIEN => 5,
            self::BIEN      => 4,
            self::NEUTRE    => 3,
            self::MAL       => 2,
            self::TRES_MAL  => 1,
        };
    }
}
