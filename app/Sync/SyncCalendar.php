<?php

declare(strict_types=1);

namespace App\Sync;

use App\Cfbd\CfbdClient;
use App\Models\SeasonWeek;

final readonly class SyncCalendar
{
    public function __construct(private CfbdClient $cfbd) {}

    /**
     * CFBD's firstGameStart/lastGameStart just repeat the week's boundaries,
     * so real kickoff ranges are filled in by the games sync instead.
     */
    public function __invoke(int $season): int
    {
        $weeks = $this->cfbd->calendar($season);

        foreach ($weeks as $week) {
            SeasonWeek::updateOrCreate([
                'season' => $week->season,
                'season_type' => $week->seasonType,
                'week' => $week->week,
            ], [
                'starts_at' => $week->startsAt,
                'ends_at' => $week->endsAt,
            ]);
        }

        return count($weeks);
    }
}
