<?php

declare(strict_types=1);

namespace App\Sync;

use App\Cfbd\CfbdClient;
use App\Cfbd\Data\PollRankData;
use App\Enums\Poll;
use App\Models\TeamRanking;
use Illuminate\Support\Facades\DB;

final readonly class SyncRankings
{
    public function __construct(
        private CfbdClient $cfbd,
        private TeamResolver $teams,
    ) {}

    /**
     * Replace the season's stored AP rankings with the provider's full history.
     */
    public function __invoke(int $season, Poll $poll = Poll::Ap): int
    {
        $ranks = collect($this->cfbd->rankings($season))
            ->filter(fn (PollRankData $rank): bool => $rank->poll === $poll->providerName() && $rank->season === $season)
            ->unique(fn (PollRankData $rank): string => "{$rank->seasonType->value}-{$rank->week}-{$rank->teamProviderId}");

        // An empty response would wipe history; treat it as "nothing new".
        if ($ranks->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($ranks, $season, $poll): void {
            TeamRanking::where('season', $season)->where('poll', $poll)->delete();

            foreach ($ranks as $rank) {
                TeamRanking::create([
                    'team_id' => $this->teams->find($rank->teamProviderId, $rank->school)->id,
                    'season' => $rank->season,
                    'season_type' => $rank->seasonType,
                    'week' => $rank->week,
                    'poll' => $poll,
                    'rank' => $rank->rank,
                ]);
            }
        });

        return $ranks->count();
    }
}
