<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sync\SyncTeams;

final class SyncTeamsCommand extends CfbdCommand
{
    use ResolvesSeason;

    protected $signature = 'cfbd:sync-teams {--season= : Season year (defaults to the current season)}';

    protected $description = 'Sync FBS and FCS teams from CFBD';

    public function handle(SyncTeams $sync): int
    {
        return $this->attempt(function () use ($sync): void {
            $count = $sync($this->season());
            $this->info("Synced {$count} teams.");
        });
    }
}
