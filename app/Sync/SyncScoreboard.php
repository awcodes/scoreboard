<?php

declare(strict_types=1);

namespace App\Sync;

use App\Cfbd\CfbdClient;
use App\Cfbd\Data\ScoreboardGameData;
use App\Enums\GameStatus;
use App\Models\Game;

/**
 * Applies live state (status, score, period, clock) from `/scoreboard` to
 * games already known locally, and flags games that are past kickoff but
 * haven't started.
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

        $game->start_delayed = $this->isDelayed($data);

        // The games sync assumes a game is live once kickoff passes. If the
        // scoreboard says it hasn't started, and no live state has been
        // recorded, put it back to scheduled so the delay can be shown.
        if ($game->start_delayed && $game->status === GameStatus::InProgress && $game->period === null) {
            $game->status = GameStatus::Scheduled;
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

    private function isDelayed(ScoreboardGameData $data): bool
    {
        return $data->status === GameStatus::Scheduled
            && ! $data->startTimeTbd
            && $data->startAt->lte(now()->subMinutes(Game::DELAYED_AFTER_MINUTES));
    }
}
