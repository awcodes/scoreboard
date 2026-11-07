<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\GameStatus;
use App\Enums\Poll;
use App\Enums\SeasonType;
use App\Models\Game;
use App\Models\SeasonWeek;
use App\Models\Team;
use App\Models\TeamRanking;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Every FBS game in a week, in kickoff order, with each team's record and
 * rank as of that week. Rankings never affect the order, and only hide
 * games when the viewer opts into the Top 25 filter.
 */
final class WeekScoreboard
{
    /** @var Collection<int, Game> */
    public readonly Collection $games;

    /** @var array<int, string> team id => "3-0" */
    private array $records;

    /** @var array<int, int> team id => rank */
    private array $ranks;

    public function __construct(public readonly SeasonWeek $week)
    {
        $this->games = $this->loadGames();
        $teamIds = $this->games->flatMap(fn (Game $g): array => [$g->home_team_id, $g->away_team_id])->unique();
        $this->records = $this->loadRecords($teamIds);
        $this->ranks = $this->loadRanks();
    }

    public function record(int $teamId): ?string
    {
        return $this->records[$teamId] ?? null;
    }

    public function rank(int $teamId): ?int
    {
        return $this->ranks[$teamId] ?? null;
    }

    /**
     * @return array<string, int> status value => count
     */
    public function counts(): array
    {
        return collect(GameStatus::cases())
            ->mapWithKeys(fn (GameStatus $s): array => [$s->value => $this->games->where('status', $s)->count()])
            ->all();
    }

    /**
     * "12 live · 37 final · 16 upcoming", omitting empty states.
     */
    public function summary(): string
    {
        if ($this->games->isEmpty()) {
            return 'No FBS games';
        }

        $counts = $this->counts();

        return collect([
            GameStatus::InProgress->value => 'live',
            GameStatus::Final->value => 'final',
            GameStatus::Scheduled->value => 'upcoming',
        ])
            ->filter(fn (string $label, string $status): bool => $counts[$status] > 0)
            ->map(fn (string $label, string $status): string => "{$counts[$status]} {$label}")
            ->implode(' · ');
    }

    /**
     * Conferences of the FBS teams playing this week.
     *
     * @return list<string>
     */
    public function conferences(): array
    {
        return $this->games
            ->flatMap(fn (Game $g): array => [
                $g->home_classification === 'fbs' ? $g->home_conference : null,
                $g->away_classification === 'fbs' ? $g->away_conference : null,
            ])
            ->filter()->unique()->sort()->values()->all();
    }

    /**
     * Filtering narrows the view only; the underlying week and its order are
     * unchanged. `$rankedOnly` keeps games with a team ranked this week.
     *
     * @return Collection<int, Game>
     */
    public function filtered(?GameStatus $status = null, ?string $conference = null, ?string $search = null, bool $rankedOnly = false): Collection
    {
        $search = $this->normalize((string) $search);

        return $this->games
            ->when($status, fn (Collection $games) => $games->where('status', $status))
            ->when($rankedOnly, fn (Collection $games) => $games->filter(
                fn (Game $g): bool => $this->rank($g->home_team_id) !== null || $this->rank($g->away_team_id) !== null
            ))
            ->when($conference, fn (Collection $games) => $games->filter(
                fn (Game $g): bool => $g->home_conference === $conference || $g->away_conference === $conference
            ))
            ->when($search !== '', fn (Collection $games) => $games->filter(
                fn (Game $g) => collect([$g->homeTeam, $g->awayTeam])->contains(
                    fn (Team $team): bool => str_contains($this->normalize($team->name), $search)
                        || $this->normalize((string) $team->abbreviation) === $search
                )
            ))
            ->values();
    }

    /**
     * Case- and accent-insensitive, so "san jose" finds "San José State".
     */
    private function normalize(string $value): string
    {
        return Str::lower(Str::squish(Str::ascii($value)));
    }

    /**
     * @return Collection<int, Game>
     */
    private function loadGames(): Collection
    {
        $tz = config('app.display_timezone');

        // Chronological; TBD kickoffs go last on their day; id breaks ties.
        return Game::query()
            ->inWeek($this->week)
            ->fbs()
            ->with(['homeTeam', 'awayTeam'])
            ->get()
            ->sortBy([
                fn (Game $a, Game $b): int => $a->start_at->copy()->setTimezone($tz)->toDateString() <=> $b->start_at->copy()->setTimezone($tz)->toDateString(),
                fn (Game $a, Game $b): int => $a->start_time_tbd <=> $b->start_time_tbd,
                fn (Game $a, Game $b): int => $a->start_at <=> $b->start_at,
                fn (Game $a, Game $b): int => $a->provider_id <=> $b->provider_id,
            ])
            ->values();
    }

    /**
     * Overall record through the end of this week (so a final shows the
     * record including that game, and future weeks show the current record).
     *
     * @return array<int, string>
     */
    private function loadRecords(Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        $games = Game::query()
            ->where('season', $this->week->season)
            ->where('status', GameStatus::Final)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->when($this->week->season_type === SeasonType::Regular, fn ($q) => $q
                ->where('season_type', SeasonType::Regular)
                ->where('week', '<=', $this->week->week))
            ->when($this->week->season_type === SeasonType::Postseason, fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('season_type', SeasonType::Regular)
                    ->orWhere('week', '<=', $this->week->week)))
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        $tally = $teamIds->mapWithKeys(fn ($id): array => [$id => ['w' => 0, 'l' => 0, 't' => 0]])->all();

        foreach ($games as $game) {
            foreach ([[$game->home_team_id, $game->home_score, $game->away_score], [$game->away_team_id, $game->away_score, $game->home_score]] as [$team, $for, $against]) {
                if (isset($tally[$team])) {
                    $tally[$team][$for > $against ? 'w' : ($for < $against ? 'l' : 't')]++;
                }
            }
        }

        return array_map(
            fn (array $r): string => "{$r['w']}-{$r['l']}".($r['t'] ? "-{$r['t']}" : ''),
            $tally,
        );
    }

    /**
     * The ranking in effect for this week: the CFP committee rankings once
     * one has been released, otherwise the AP poll. CFBD numbers each
     * release by the week it precedes, so week N uses the latest release
     * numbered N or lower. Postseason games use the last regular-season one.
     *
     * @return array<int, int>
     */
    private function loadRanks(): array
    {
        foreach ([Poll::Cfp, Poll::Ap] as $poll) {
            $releases = TeamRanking::query()
                ->where('season', $this->week->season)
                ->where('poll', $poll)
                ->where('season_type', SeasonType::Regular)
                ->when($this->week->season_type === SeasonType::Regular, fn ($q) => $q->where('week', '<=', $this->week->week));

            $releaseWeek = (clone $releases)->max('week');

            if ($releaseWeek !== null) {
                return $releases->where('week', $releaseWeek)->pluck('rank', 'team_id')->all();
            }
        }

        return [];
    }
}
