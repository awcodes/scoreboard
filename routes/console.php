<?php

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Support\Facades\Schedule;

/*
| Upstream traffic is driven only by this schedule; page views never call CFBD.
| Times are UTC. See README "API budget" for the resulting monthly volume.
*/

$liveWindow = fn () => Game::query()->inLiveWindow()->exists();

// Season setup data changes rarely.
Schedule::command('cfbd:sync-calendar')->weeklyOn(1, '9:00')->withoutOverlapping();
Schedule::command('cfbd:sync-teams')->weeklyOn(1, '9:05')->withoutOverlapping();

// Whole season, once a day (3 calls): kickoff times, TBDs, networks, finals.
Schedule::command('cfbd:sync-games')->dailyAt('10:00')->withoutOverlapping();

// The AP poll is released Sunday afternoon Eastern; Monday is a safety net.
Schedule::command('cfbd:sync-rankings')->sundays()->at('20:00')->withoutOverlapping();
Schedule::command('cfbd:sync-rankings')->mondays()->at('12:00')->withoutOverlapping();

if (config('services.cfbd.live_scoreboard')) {
    // Live status, score, period and clock (1 call).
    Schedule::command('cfbd:sync-scoreboard')
        ->cron('*/'.max(1, config('services.cfbd.live_interval')).' * * * *')
        ->when($liveWindow)
        ->withoutOverlapping();

    // Late network/time changes and confirmed finals (3 calls).
    Schedule::command('cfbd:sync-games --current')
        ->everyThirtyMinutes()
        ->when($liveWindow)
        ->withoutOverlapping();
} else {
    // No scoreboard on this tier: refresh the current week's FBS games (1 call).
    Schedule::command('cfbd:sync-games --current --scores')
        ->cron('*/'.max(1, config('services.cfbd.scores_interval')).' * * * *')
        ->when($liveWindow)
        ->withoutOverlapping();
}
