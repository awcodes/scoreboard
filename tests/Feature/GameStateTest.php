<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Models\Game;

use function Pest\Laravel\travelTo;

it('labels game states', function (array $attributes, string $label): void {
    expect(Game::factory()->make($attributes)->stateLabel())->toBe($label);
})->with([
    'upcoming' => [['start_at' => '2026-09-12 23:30:00'], 'Sat 7:30 PM'],
    'TBD kickoff' => [['start_at' => '2026-09-12 04:00:00', 'start_time_tbd' => true], 'Sat TBD'],
    'live' => [['status' => GameStatus::InProgress, 'period' => 3, 'clock' => '04:32'], '3rd · 4:32'],
    'long clock format' => [['status' => GameStatus::InProgress, 'period' => 1, 'clock' => '00:12:05'], '1st · 12:05'],
    'halftime' => [['status' => GameStatus::InProgress, 'period' => 2, 'clock' => '00:00'], 'Halftime'],
    'overtime' => [['status' => GameStatus::InProgress, 'period' => 6, 'clock' => null], '2OT'],
    'live without detail' => [['status' => GameStatus::InProgress], 'Live'],
    'final' => [['status' => GameStatus::Final, 'period' => 4], 'Final'],
    'final in overtime' => [['status' => GameStatus::Final, 'period' => 5], 'Final/OT'],
]);

it('only shows delays and moves for upcoming games', function (): void {
    $moved = ['start_at' => '2026-09-13 17:00:00', 'original_start_at' => '2026-09-12 23:30:00'];

    expect(Game::factory()->make(['start_delayed' => true])->isDelayed())->toBeTrue()
        ->and(Game::factory()->make(['start_delayed' => true, 'status' => GameStatus::InProgress])->isDelayed())->toBeFalse()
        ->and(Game::factory()->make($moved)->movedFromLabel())->toBe('Sat 7:30 PM')
        ->and(Game::factory()->final(21, 14)->make($moved)->movedFromLabel())->toBeNull()
        ->and(Game::factory()->make()->movedFromLabel())->toBeNull();
});

it('shows TV TBD when no network is known', function (): void {
    expect(Game::factory()->make()->networkLabel())->toBe('TV TBD')
        ->and(Game::factory()->make(['network' => 'ESPN'])->networkLabel())->toBe('ESPN');
});

it('knows which games are in the live polling window', function (): void {
    travelTo('2026-09-12 20:00:00');

    $live = Game::factory()->create(['status' => GameStatus::InProgress, 'start_at' => '2026-09-12 04:00:00', 'start_time_tbd' => true]);
    $kickingOff = Game::factory()->create(['start_at' => '2026-09-12 20:05:00']);
    Game::factory()->create(['start_at' => '2026-09-12 23:30:00']);
    Game::factory()->final(21, 14)->create(['start_at' => '2026-09-12 16:00:00']);

    expect(Game::inLiveWindow()->pluck('id')->all())->toBe([$live->id, $kickingOff->id]);
});
