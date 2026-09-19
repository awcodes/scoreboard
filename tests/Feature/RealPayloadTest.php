<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\SeasonWeek;
use App\Models\Team;
use App\Support\WeekScoreboard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

/*
| Real CFBD responses captured by `cfbd:spike` on Saturday 2026-09-19,
| shortly before the first kickoff of week 3 (free tier, no scoreboard).
*/

function realFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/../Fixtures/cfbd/real/{$name}.json"), true);
}

beforeEach(function (): void {
    travelTo('2026-09-19 15:45:00');

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return Http::response(match (parse_url($request->url(), PHP_URL_PATH)) {
            '/calendar' => realFixture('calendar'),
            '/games' => $query['classification'] === 'fbs' ? realFixture('games-fbs-week3') : [],
            '/games/media' => realFixture('media-week3'),
            '/rankings' => realFixture('rankings'),
            '/scoreboard' => realFixture('scoreboard'),
            default => throw new RuntimeException('No real CFBD fixture for ['.$request->url().'].'),
        });
    });

    artisan('cfbd:sync-calendar', ['--season' => 2026])->assertSuccessful();
    artisan('cfbd:sync-games', ['--season' => 2026, '--week' => '3'])->assertSuccessful();
    artisan('cfbd:sync-rankings', ['--season' => 2026])->assertSuccessful();
});

it('syncs a real week of FBS games', function (): void {
    expect(Game::fbs()->count())->toBe(75)
        ->and(Game::where('status', GameStatus::Final)->count())->toBe(4)
        ->and(Game::where('status', GameStatus::InProgress)->count())->toBe(1)
        ->and(Game::whereNull('network')->count())->toBe(0);

    $game = Game::firstWhere('provider_id', 401858225);
    expect($game->homeTeam->name)->toBe('Pittsburgh')
        ->and([$game->home_score, $game->away_score])->toBe([27, 13]);

    // FBS vs FCS games come through the FBS request.
    expect(Game::where('home_classification', 'fcs')->orWhere('away_classification', 'fcs')->count())->toBe(18)
        ->and(Team::firstWhere('name', 'Portland State')->classification)->toBe('fcs');
});

it('has no live scores from /games for a game already under way', function (): void {
    // Coastal Carolina at Delaware kicked off 15 minutes before the capture.
    $game = Game::whereHas('homeTeam', fn ($q) => $q->where('name', 'Delaware'))->first();

    expect($game->status)->toBe(GameStatus::InProgress)
        ->and($game->home_score)->toBeNull()
        ->and($game->hasScore())->toBeFalse();
});

it('collapses duplicate outlet names and prefers TV', function (): void {
    $networks = Game::pluck('network');

    expect($networks)->not->toContain('The CW Network')
        ->and($networks->filter(fn ($n): bool => str_contains($n, ' / ')))->toBeEmpty()
        ->and(Game::whereHas('homeTeam', fn ($q) => $q->where('name', 'Clemson'))->value('network'))->toBe('ESPN');
});

it('derives the week date range from real kickoffs', function (): void {
    $week = SeasonWeek::current();

    expect($week->week)->toBe(3)
        ->and($week->dateRange())->toBe('September 17–19, 2026');
});

it('uses the AP poll numbered for the week', function (): void {
    $board = new WeekScoreboard(SeasonWeek::current());

    expect($board->rank(Team::firstWhere('name', 'Texas')->id))->toBe(1)
        ->and($board->rank(Team::firstWhere('name', 'Georgia')->id))->toBe(2);
});

it('renders the real week', function (): void {
    get('/')->assertOk()
        ->assertSeeText('Week 3')
        ->assertSeeText('1 live · 4 final · 70 upcoming')
        ->assertSeeInOrder(['Syracuse', 'Pittsburgh', 'Miami', 'Wake Forest']);
});

it('applies a real live scoreboard', function (): void {
    // Captured at 16:31 UTC, early in the noon Eastern games (Tier 2 key).
    travelTo('2026-09-19 16:31:00');

    artisan('cfbd:sync-scoreboard')->assertSuccessful();

    $game = Game::firstWhere('provider_id', 401869940);
    expect($game->status)->toBe(GameStatus::InProgress)
        ->and([$game->away_score, $game->home_score])->toBe([14, 7])
        ->and($game->stateLabel())->toBe('2nd · 7:08');

    // Georgia at Arkansas: early first quarter.
    expect(Game::firstWhere('provider_id', 401856686)->stateLabel())->toBe('1st · 4:47');

    // Completed games on the scoreboard carry no period or clock.
    expect(Game::firstWhere('provider_id', 401858225)->stateLabel())->toBe('Final');

    get('/')->assertSeeText('2nd · 7:08')->assertSeeInOrder(['Coastal Carolina', '14', 'Delaware', '7']);
});
