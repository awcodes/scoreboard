<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Models\Game;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    travelTo('2026-09-12 20:00:00');
    fakeCfbd();
    artisan('cfbd:sync-games', ['--season' => 2026]);
});

it('applies live score, period and clock', function (): void {
    artisan('cfbd:sync-scoreboard')->assertSuccessful();

    $game = Game::firstWhere('provider_id', 401800003);
    expect($game->status)->toBe(GameStatus::InProgress)
        ->and([$game->home_score, $game->away_score])->toBe([21, 17])
        ->and($game->period)->toBe(3)
        ->and($game->clock)->toBe('04:32')
        ->and($game->stateLabel())->toBe('3rd · 4:32');
});

it('fills a missing network from the scoreboard without overriding known ones', function (): void {
    artisan('cfbd:sync-scoreboard');

    expect(Game::firstWhere('provider_id', 401800003)->network)->toBe('ACC Network')
        ->and(Game::firstWhere('provider_id', 401800001)->network)->toBe('ABC');
});

it('marks completed games final', function (): void {
    Game::where('provider_id', 401800001)->update(['status' => GameStatus::InProgress, 'completed_at' => null]);

    artisan('cfbd:sync-scoreboard');

    $game = Game::firstWhere('provider_id', 401800001);
    expect($game->status)->toBe(GameStatus::Final)
        ->and($game->clock)->toBeNull()
        ->and($game->completed_at)->not->toBeNull();
});

it('never moves a game backwards to scheduled', function (): void {
    Game::where('provider_id', 401800004)->update(['status' => GameStatus::InProgress, 'home_score' => 7]);

    artisan('cfbd:sync-scoreboard');

    expect(Game::firstWhere('provider_id', 401800004)->status)->toBe(GameStatus::InProgress);
});

it('ignores scoreboard games that are not synced locally', function (): void {
    artisan('cfbd:sync-scoreboard')->expectsOutputToContain('Updated 2 games.');

    expect(Game::where('provider_id', 409999999)->exists())->toBeFalse();
});
