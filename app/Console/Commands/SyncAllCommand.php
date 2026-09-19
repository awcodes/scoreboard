<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class SyncAllCommand extends CfbdCommand
{
    use ResolvesSeason;

    protected $signature = 'cfbd:sync {--season= : Season year (defaults to the current season)}';

    protected $description = 'Full season setup: calendar, teams, games and rankings';

    public function handle(): int
    {
        $season = ['--season' => $this->season()];

        $results = collect(['cfbd:sync-calendar', 'cfbd:sync-teams', 'cfbd:sync-games', 'cfbd:sync-rankings'])
            ->map(fn (string $command) => $this->call($command, $season));

        return $results->contains(self::FAILURE) ? self::FAILURE : self::SUCCESS;
    }
}
