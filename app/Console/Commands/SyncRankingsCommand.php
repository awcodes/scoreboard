<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sync\SyncRankings;

final class SyncRankingsCommand extends CfbdCommand
{
    use ResolvesSeason;

    protected $signature = 'cfbd:sync-rankings {--season= : Season year (defaults to the current season)}';

    protected $description = 'Sync weekly AP Top 25 rankings from CFBD';

    public function handle(SyncRankings $sync): int
    {
        return $this->attempt(function () use ($sync): void {
            $count = $sync($this->season());
            $this->info("Synced {$count} AP rankings.");
        });
    }
}
