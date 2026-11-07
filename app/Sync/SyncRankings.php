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
     * Replace the season's stored AP and CFP rankings with the provider's
     * full history, from a single `/rankings` call.
     */
    public function __invoke(int $season): int
    {
        $response = collect($this->cfbd->rankings($season));
        $count = 0;

        foreach (Poll::cases() as $poll) {
            $ranks = $response
                ->filter(fn (PollRankData $rank): bool => $rank->poll === $poll->providerName() && $rank->season === $season)
                ->unique(fn (PollRankData $rank): string => "{$rank->seasonType->value}-{$rank->week}-{$rank->teamProviderId}");

            // An empty poll would wipe its history; treat it as "nothing new".
            if ($ranks->isEmpty()) {
                continue;
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

            $count += $ranks->count();
        }

        return $count;
    }
}
