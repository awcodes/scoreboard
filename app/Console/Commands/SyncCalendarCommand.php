<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sync\SyncCalendar;

final class SyncCalendarCommand extends CfbdCommand
{
    use ResolvesSeason;

    protected $signature = 'cfbd:sync-calendar {--season= : Season year (defaults to the current season)}';

    protected $description = 'Sync the season week calendar from CFBD';

    public function handle(SyncCalendar $sync): int
    {
        return $this->attempt(function () use ($sync): void {
            $count = $sync($this->season());
            $this->info("Synced {$count} weeks.");
        });
    }
}
