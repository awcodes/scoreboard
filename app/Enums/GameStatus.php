<?php

declare(strict_types=1);

namespace App\Enums;

enum GameStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Upcoming',
            self::InProgress => 'Live',
            self::Final => 'Final',
        };
    }
}
