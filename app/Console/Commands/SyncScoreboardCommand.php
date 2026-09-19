<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sync\SyncScoreboard;

final class SyncScoreboardCommand extends CfbdCommand
{
    protected $signature = 'cfbd:sync-scoreboard';

    protected $description = 'Sync live status, scores, period and clock from the CFBD scoreboard';

    public function handle(SyncScoreboard $sync): int
    {
        return $this->attempt(function () use ($sync): void {
            $count = $sync();
            $this->info("Updated {$count} games.");
        });
    }
}
