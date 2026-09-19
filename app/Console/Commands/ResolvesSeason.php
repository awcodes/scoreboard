<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SeasonWeek;

/**
 * For commands with a `{--season=}` option.
 */
trait ResolvesSeason
{
    protected function season(): int
    {
        return (int) ($this->option('season') ?: SeasonWeek::defaultSeason());
    }
}
