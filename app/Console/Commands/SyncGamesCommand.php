<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SeasonType;
use App\Models\SeasonWeek;
use App\Sync\SyncGames;

final class SyncGamesCommand extends CfbdCommand
{
    use ResolvesSeason;

    protected $signature = 'cfbd:sync-games
        {--season= : Season year (defaults to the current season)}
        {--week= : Week slug, e.g. 3 or post (defaults to the whole season)}
        {--current : Only sync the current week}
        {--scores : Game-day refresh: FBS games only, no networks (one API call)}
        {--force : Also update final games that have been frozen}';

    protected $description = 'Sync schedules, kickoff times, networks and final scores from CFBD';

    public function handle(SyncGames $sync): int
    {
        $season = $this->season();
        $type = null;
        $week = null;

        if ($this->option('current')) {
            $current = SeasonWeek::current();

            if (! $current instanceof SeasonWeek) {
                $this->warn('No calendar synced yet; run cfbd:sync-calendar first.');

                return self::FAILURE;
            }

            [$season, $type, $week] = [$current->season, $current->season_type, $current->week];
        } elseif ($this->option('week')) {
            [$type, $week] = SeasonWeek::parseSlug($this->option('week'));
        }

        return $this->attempt(function () use ($sync, $season, $type, $week): void {
            $stats = $sync($season, $type, $week, (bool) $this->option('force'), (bool) $this->option('scores'));

            $scope = $week ? ($type === SeasonType::Postseason ? "postseason week {$week}" : "week {$week}") : 'full season';
            $this->info("Synced {$season} {$scope}: {$stats['created']} created, {$stats['updated']} updated, {$stats['frozen']} frozen.");
        });
    }
}
