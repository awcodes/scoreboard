<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    travelTo('2026-09-13 12:00:00');
});

it('syncs games, networks and opponents outside the synced team set', function (): void {
    fakeCfbd();

    artisan('cfbd:sync-games', ['--season' => 2026])->assertSuccessful();

    expect(Game::count())->toBe(5);

    $georgia = Game::firstWhere('provider_id', 401800001);
    expect($georgia->homeTeam->name)->toBe('Georgia')
        ->and($georgia->status)->toBe(GameStatus::Final)
        ->and([$georgia->home_score, $georgia->away_score])->toBe([34, 20])
        ->and($georgia->network)->toBe('ABC')
        ->and($georgia->completed_at)->not->toBeNull()
        ->and(Game::firstWhere('provider_id', 401800002)->network)->toBe('ESPN+');

    // FBS vs FCS: the FCS opponent exists as a team with its classification.
    $clemson = Game::firstWhere('provider_id', 401800003);
    expect($clemson->awayTeam->name)->toBe('Austin Peay')
        ->and($clemson->away_classification)->toBe('fcs')
        ->and($clemson->start_time_tbd)->toBeTrue()
        ->and($clemson->network)->toBeNull()
        ->and($clemson->status)->toBe(GameStatus::Scheduled)
        ->and(Team::firstWhere('provider_id', 2433)->classification)->toBe('ii');
});

it('updates kickoff times and networks when the schedule changes', function (): void {
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);

    $games = cfbdFixture('games-week3-fbs');
    $games[2]['startTimeTBD'] = false;
    $games[2]['startDate'] = '2026-09-12T23:30:00.000Z';
    $media = [...cfbdFixture('media-week3'), [...cfbdFixture('media-week3')[0], 'id' => 401800003, 'outlet' => 'ACC Network']];

    Http::swap(new Factory);
    fakeCfbd(['/games' => $games, '/games/media' => $media]);
    artisan('cfbd:sync-games', ['--season' => 2026, '--week' => '3']);

    $game = Game::firstWhere('provider_id', 401800003);
    expect($game->start_time_tbd)->toBeFalse()
        ->and($game->start_at->toIso8601String())->toBe('2026-09-12T23:30:00+00:00')
        ->and($game->network)->toBe('ACC Network');
});

it('records final scores', function (): void {
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);

    $games = cfbdFixture('games-week3-fbs');
    $games[2] = [...$games[2], 'completed' => true, 'homePoints' => 45, 'awayPoints' => 3];

    Http::swap(new Factory);
    fakeCfbd(['/games' => $games]);
    artisan('cfbd:sync-games', ['--season' => 2026]);

    $game = Game::firstWhere('provider_id', 401800003);
    expect($game->status)->toBe(GameStatus::Final)
        ->and([$game->home_score, $game->away_score])->toBe([45, 3]);
});

it('treats a game past kickoff as in progress until it is final', function (): void {
    fakeCfbd();
    travelTo('2026-09-12 20:00:00');

    artisan('cfbd:sync-games', ['--season' => 2026]);
    expect(Game::firstWhere('provider_id', 401800004)->status)->toBe(GameStatus::Scheduled);

    travelTo('2026-09-12 23:10:00');
    artisan('cfbd:sync-games', ['--season' => 2026]);
    expect(Game::firstWhere('provider_id', 401800004)->status)->toBe(GameStatus::InProgress);

    // Still incomplete half a day later: most likely postponed.
    travelTo('2026-09-13 12:00:00');
    artisan('cfbd:sync-games', ['--season' => 2026]);
    expect(Game::firstWhere('provider_id', 401800004)->status)->toBe(GameStatus::Scheduled);
});

it('moves a rescheduled game back to scheduled', function (): void {
    fakeCfbd();
    travelTo('2026-09-12 23:10:00');
    artisan('cfbd:sync-games', ['--season' => 2026]);

    $games = cfbdFixture('games-week3-fcs');
    $games[1]['startDate'] = '2026-09-13T17:00:00.000Z';
    Http::swap(new Factory);
    fakeCfbd(['/games' => $games]);
    artisan('cfbd:sync-games', ['--season' => 2026]);

    expect(Game::firstWhere('provider_id', 401800004)->status)->toBe(GameStatus::Scheduled);
});

it('remembers the original kickoff when a game is moved', function (): void {
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);
    Game::where('provider_id', 401800004)->update(['start_delayed' => true]);

    $resync = function (string $startDate): Game {
        $games = cfbdFixture('games-week3-fcs');
        $games[1]['startDate'] = $startDate;
        Http::swap(new Factory);
        fakeCfbd(['/games' => $games]);
        artisan('cfbd:sync-games', ['--season' => 2026]);

        return Game::firstWhere('provider_id', 401800004);
    };

    $game = $resync('2026-09-13T17:00:00.000Z');
    expect($game->original_start_at->toIso8601String())->toBe('2026-09-12T23:00:00+00:00')
        ->and($game->start_delayed)->toBeFalse()
        ->and($game->movedFromLabel())->toBe('Sat 7:00 PM');

    // Moving again keeps the first announced kickoff.
    expect($resync('2026-09-13T19:00:00.000Z')->original_start_at->toIso8601String())->toBe('2026-09-12T23:00:00+00:00')
        ->and($resync('2026-09-12T23:00:00.000Z')->original_start_at)->toBeNull();
});

it('does not treat announcing a TBD kickoff time as a move', function (): void {
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);

    $games = cfbdFixture('games-week3-fbs');
    $games[2] = [...$games[2], 'startTimeTBD' => false, 'startDate' => '2026-09-12T23:30:00.000Z'];
    Http::swap(new Factory);
    fakeCfbd(['/games' => $games]);
    artisan('cfbd:sync-games', ['--season' => 2026]);

    expect(Game::firstWhere('provider_id', 401800003)->original_start_at)->toBeNull();
});

it('refreshes scores with a single call in --scores mode', function (): void {
    fakeCfbd();

    artisan('cfbd:sync-games', ['--season' => 2026, '--week' => '3', '--scores' => true])->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'classification=fbs'));
    expect(Game::count())->toBe(3)->and(Game::whereNotNull('network')->count())->toBe(0);
});

it('does not downgrade a live TBD game reported as incomplete', function (): void {
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);
    Game::where('provider_id', 401800003)->update(['status' => GameStatus::InProgress, 'home_score' => 14, 'away_score' => 0, 'period' => 2]);

    artisan('cfbd:sync-games', ['--season' => 2026]);

    $game = Game::firstWhere('provider_id', 401800003);
    expect($game->status)->toBe(GameStatus::InProgress)
        ->and($game->home_score)->toBe(14);
});

it('stops updating final games once they settle', function (): void {
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);
    Game::where('provider_id', 401800001)->update(['completed_at' => now()->subDays(3), 'network' => 'Settled']);

    artisan('cfbd:sync-games', ['--season' => 2026])
        ->expectsOutputToContain('1 frozen')
        ->assertSuccessful();
    expect(Game::firstWhere('provider_id', 401800001)->network)->toBe('Settled');

    artisan('cfbd:sync-games', ['--season' => 2026, '--force' => true]);
    expect(Game::firstWhere('provider_id', 401800001)->network)->toBe('ABC');
});

it('keeps existing networks when the media request fails', function (): void {
    Sleep::fake();
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);

    Http::swap(new Factory);
    fakeCfbd(['/games/media' => Http::response('down', 503)]);
    artisan('cfbd:sync-games', ['--season' => 2026])->assertSuccessful();

    expect(Game::firstWhere('provider_id', 401800001)->network)->toBe('ABC');
});

it('leaves local data intact when CFBD is down', function (): void {
    Sleep::fake();
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);

    Http::swap(new Factory);
    fakeCfbd(['/games' => Http::response('down', 503)]);

    artisan('cfbd:sync-games', ['--season' => 2026])->assertFailed();
    expect(Game::count())->toBe(5);
});

it('limits --current to the current week', function (): void {
    fakeCfbd();
    artisan('cfbd:sync-calendar', ['--season' => 2026]);

    artisan('cfbd:sync-games', ['--current' => true])->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/games?')
        && str_contains($request->url(), 'week=3')
        && str_contains($request->url(), 'seasonType=regular'));
});
