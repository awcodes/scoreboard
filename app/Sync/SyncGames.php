<?php

declare(strict_types=1);

namespace App\Sync;

use App\Cfbd\CfbdClient;
use App\Cfbd\CfbdException;
use App\Cfbd\Data\GameData;
use App\Cfbd\Data\GameMediaData;
use App\Enums\GameStatus;
use App\Enums\SeasonType;
use App\Models\Game;
use App\Models\SeasonWeek;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the schedule, kickoff times, networks and final results from
 * `/games` and `/games/media`.
 *
 * FCS games are synced alongside FBS games so that FCS opponents' records
 * are complete. The scoreboard only displays games with an FBS team.
 */
final readonly class SyncGames
{
    public function __construct(
        private CfbdClient $cfbd,
        private TeamResolver $teams,
    ) {}

    /**
     * `$scoresOnly` is the cheap game-day refresh (one call): FBS games
     * only, without media. FCS-only games and networks are left to the
     * regular sync.
     *
     * @return array{created: int, updated: int, frozen: int}
     */
    public function __invoke(int $season, ?SeasonType $seasonType = null, ?int $week = null, bool $force = false, bool $scoresOnly = false): array
    {
        $type = $seasonType->value ?? 'both';

        $games = collect($scoresOnly ? ['fbs'] : ['fbs', 'fcs'])
            ->flatMap(fn (string $classification): array => $this->cfbd->games($season, $classification, $type, $week))
            ->keyBy('providerId');

        $networks = $scoresOnly ? null : $this->networks($season, $type, $week);

        $stats = ['created' => 0, 'updated' => 0, 'frozen' => 0];

        DB::transaction(function () use ($games, $networks, $force, &$stats): void {
            $existing = Game::whereIn('provider_id', $games->keys())->get()->keyBy('provider_id');

            foreach ($games as $data) {
                $game = $existing->get($data->providerId) ?? new Game(['provider_id' => $data->providerId]);

                if ($game->isFrozen() && ! $force) {
                    $stats['frozen']++;

                    continue;
                }

                $stats[$game->exists ? 'updated' : 'created']++;

                $this->apply($game, $data, $networks);
            }

            $this->updateWeekKickoffs($games);
        });

        return $stats;
    }

    /**
     * @param  array<int, string|null>|null  $networks  keyed by game provider id
     */
    private function apply(Game $game, GameData $data, ?array $networks): void
    {
        $this->trackReschedule($game, $data);

        $game->fill([
            'season' => $data->season,
            'season_type' => $data->seasonType,
            'week' => $data->week,
            'home_team_id' => $this->teams->forGame($data->home)->id,
            'away_team_id' => $this->teams->forGame($data->away)->id,
            'home_classification' => $data->home->classification,
            'away_classification' => $data->away->classification,
            'home_conference' => $data->home->conference,
            'away_conference' => $data->away->conference,
            'start_at' => $data->startAt,
            'start_time_tbd' => $data->startTimeTbd,
            'venue' => $data->venue,
            'neutral_site' => $data->neutralSite,
            'conference_game' => $data->conferenceGame,
        ]);

        // A failed media request leaves previously known networks alone.
        if ($networks !== null && array_key_exists($data->providerId, $networks)) {
            $game->network = $networks[$data->providerId];
        }

        if ($data->completed) {
            $game->status = GameStatus::Final;
            $game->home_score = $data->home->points ?? $game->home_score;
            $game->away_score = $data->away->points ?? $game->away_score;
            $game->clock = null;
            $game->completed_at ??= now();
            $game->start_delayed = false;
        } elseif (($this->kickedOff($data) && ! $game->start_delayed) || ($data->startTimeTbd && $game->status === GameStatus::InProgress)) {
            // /games has no live status, so a game past kickoff is assumed
            // to be under way. Period and clock come from the scoreboard sync.
            $game->status = GameStatus::InProgress;
            $game->home_score = $data->home->points ?? $game->home_score;
            $game->away_score = $data->away->points ?? $game->away_score;
        } else {
            // Not started yet, rescheduled, delayed according to the
            // scoreboard, or long overdue (likely postponed).
            $game->status = GameStatus::Scheduled;
        }

        $game->synced_at = now();
        $game->save();
    }

    /**
     * Remembers the first announced kickoff when a game with a set time is
     * moved, and forgets it if the game moves back.
     */
    private function trackReschedule(Game $game, GameData $data): void
    {
        $moved = $game->exists
            && ! $game->start_time_tbd
            && ($data->startTimeTbd || $game->start_at->ne($data->startAt));

        if ($moved) {
            $game->original_start_at ??= $game->start_at;
            $game->start_delayed = false;
        }

        if (! $data->startTimeTbd && $game->original_start_at?->eq($data->startAt)) {
            $game->original_start_at = null;
        }
    }

    /**
     * Kicked off within the last 12 hours. Anything older that still isn't
     * complete was most likely postponed or cancelled upstream.
     */
    private function kickedOff(GameData $data): bool
    {
        return ! $data->startTimeTbd
            && $data->startAt->isPast()
            && $data->startAt->gt(now()->subHours(12));
    }

    /**
     * CFBD's calendar reports the week's boundaries as its first and last
     * game times, so derive the real kickoff range from FBS games.
     *
     * @param  Collection<int, GameData>  $games
     */
    private function updateWeekKickoffs(Collection $games): void
    {
        $games->map(fn (GameData $g): array => [$g->season, $g->seasonType, $g->week])
            ->unique(fn (array $key): string => "{$key[0]}-{$key[1]->value}-{$key[2]}")
            ->each(function (array $key): void {
                [$season, $type, $week] = $key;

                $fbsGames = Game::query()
                    ->where(['season' => $season, 'season_type' => $type, 'week' => $week])
                    ->fbs();

                SeasonWeek::query()
                    ->where(['season' => $season, 'season_type' => $type, 'week' => $week])
                    ->update([
                        'first_game_at' => (clone $fbsGames)->min('start_at'),
                        'last_game_at' => $fbsGames->max('start_at'),
                    ]);
            });
    }

    /**
     * @return array<int, string|null>|null keyed by game provider id
     */
    private function networks(int $season, string $type, ?int $week): ?array
    {
        try {
            $media = $this->cfbd->media($season, $type, $week);
        } catch (CfbdException $e) {
            Log::warning('CFBD media sync failed; keeping existing networks. '.$e->getMessage());

            return null;
        }

        return collect($media)
            ->groupBy('gameProviderId')
            ->map(fn (Collection $rows): ?string => GameMediaData::networkFor($rows))
            ->all();
    }
}
