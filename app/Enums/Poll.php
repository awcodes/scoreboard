<?php

declare(strict_types=1);

namespace App\Enums;

enum Poll: string
{
    case Ap = 'ap';

    /**
     * The poll name as CFBD reports it.
     */
    public function providerName(): string
    {
        return match ($this) {
            self::Ap => 'AP Top 25',
        };
    }
}
