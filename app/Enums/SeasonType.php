<?php

declare(strict_types=1);

namespace App\Enums;

enum SeasonType: string
{
    case Regular = 'regular';
    case Postseason = 'postseason';
}
