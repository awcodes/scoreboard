<?php

declare(strict_types=1);

namespace App\Sync;

use App\Cfbd\CfbdClient;
use App\Cfbd\Data\ScoreboardGameData;
use App\Enums\GameStatus;
use App\Models\Game;

/**
 * Applies live state (status, score, period, clock) from `/scoreboard` to
 * games already known locally.
 */
final readonly class SyncScoreboard
{
    public function __construct(private CfbdClient $cfbd) {}

    /**
     * @return int number of games updated
     */
    public function __invoke(): int
    {
        $live = collect($this->cfbd->scoreboard('fbs'))->keyBy('providerId');

        $games = Game::whereIn('provider_id', $live->keys())->get();

        $updated = 0;

        foreach ($games as $game) {
            if ($game->isFrozen()) {
                continue;
            }

            $this->apply($game, $live->get($game->provider_id));

            if ($game->isDirty()) {
                $game->synced_at = now();
                $game->save();
                $updated++;
            }
        }

        return $updated;
    }

    private function apply(Game $game, ScoreboardGameData $data): void
    {
        if ($data->tv && ! $game->network) {
            $game->network = $data->tv;
        }

        match ($data->status) {
            GameStatus::InProgress => $game->fill([
                'status' => GameStatus::InProgress,
                'period' => $data->period,
                'clock' => $data->clock,
                'home_score' => $data->homePoints ?? $game->home_score,
                'away_score' => $data->awayPoints ?? $game->away_score,
            ]),
            GameStatus::Final => $game->fill([
                'status' => GameStatus::Final,
                'period' => $data->period ?? $game->period,
                'clock' => null,
                'home_score' => $data->homePoints ?? $game->home_score,
                'away_score' => $data->awayPoints ?? $game->away_score,
                'completed_at' => $game->completed_at ?? now(),
            ]),
            // Never downgrade a live or final game back to scheduled.
            GameStatus::Scheduled => null,
        };
    }
}
