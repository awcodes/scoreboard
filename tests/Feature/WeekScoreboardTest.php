<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Enums\Poll;
use App\Enums\SeasonType;
use App\Models\Game;
use App\Models\SeasonWeek;
use App\Models\Team;
use App\Models\TeamRanking;
use App\Support\WeekScoreboard;

beforeEach(function (): void {
    $this->georgia = Team::factory()->create(['name' => 'Georgia']);
    $this->tennessee = Team::factory()->create(['name' => 'Tennessee']);
    $this->peay = Team::factory()->fcs()->create(['name' => 'Austin Peay']);
    $this->week2 = SeasonWeek::factory()->week(2)->create();
    $this->week3 = SeasonWeek::factory()->week(3)->create();
});

function rank(Team $team, int $week, int $rank, SeasonType $type = SeasonType::Regular): void
{
    TeamRanking::create(['team_id' => $team->id, 'season' => 2026, 'season_type' => $type, 'week' => $week, 'poll' => Poll::Ap, 'rank' => $rank]);
}

it('computes overall records through the viewed week', function (): void {
    Game::factory()->between($this->georgia, $this->peay)->final(48, 7)->create(['week' => 1]);
    Game::factory()->between($this->tennessee, $this->peay)->final(10, 13)->create(['week' => 2]);
    Game::factory()->between($this->georgia, $this->tennessee)->final(34, 20)->create(['week' => 3]);

    $week2 = new WeekScoreboard($this->week2);
    $week3 = new WeekScoreboard($this->week3);

    expect($week2->record($this->tennessee->id))->toBe('0-1')
        ->and($week2->record($this->peay->id))->toBe('1-1')
        ->and($week3->record($this->georgia->id))->toBe('2-0')
        ->and($week3->record($this->tennessee->id))->toBe('0-2');
});

it('ignores unfinished games in records', function (): void {
    Game::factory()->between($this->georgia, $this->tennessee)->create(['week' => 3, 'status' => GameStatus::InProgress, 'home_score' => 21, 'away_score' => 0]);

    expect((new WeekScoreboard($this->week3))->record($this->georgia->id))->toBe('0-0');
});

it('uses the ranking in effect for the viewed week, not the latest', function (): void {
    Game::factory()->between($this->georgia, $this->tennessee)->create(['week' => 2]);
    rank($this->georgia, 2, 5);
    rank($this->georgia, 3, 4);
    rank($this->georgia, 4, 1);

    expect((new WeekScoreboard($this->week2))->rank($this->georgia->id))->toBe(5)
        ->and((new WeekScoreboard($this->week3))->rank($this->georgia->id))->toBe(4)
        ->and((new WeekScoreboard($this->week3))->rank($this->tennessee->id))->toBeNull();
});

it('falls back to the most recent earlier poll', function (): void {
    rank($this->georgia, 1, 3);

    expect((new WeekScoreboard($this->week3))->rank($this->georgia->id))->toBe(3);
});

it('uses the last regular-season poll for the postseason', function (): void {
    $bowls = SeasonWeek::factory()->create(['season_type' => SeasonType::Postseason, 'week' => 1, 'starts_at' => '2026-12-13', 'ends_at' => '2027-01-20']);
    rank($this->georgia, 15, 2);
    rank($this->georgia, 1, 1, SeasonType::Postseason);

    expect((new WeekScoreboard($bowls))->rank($this->georgia->id))->toBe(2);
});

it('includes FBS vs FCS games and excludes games without an FBS team', function (): void {
    $samford = Team::factory()->fcs()->create(['name' => 'Samford']);
    $fbsFcs = Game::factory()->between($this->georgia, $this->peay)->create(['week' => 3]);
    Game::factory()->between($samford, $this->peay)->create(['week' => 3]);

    expect((new WeekScoreboard($this->week3))->games->pluck('id')->all())->toBe([$fbsFcs->id]);
});

it('orders chronologically regardless of rankings, TBD kickoffs last on their day', function (): void {
    $teams = Team::factory()->count(8)->create();
    $make = fn (int $i, string $start, bool $tbd = false) => Game::factory()
        ->between($teams[$i * 2], $teams[$i * 2 + 1])
        ->create(['week' => 3, 'start_at' => $start, 'start_time_tbd' => $tbd]);

    $late = $make(0, '2026-09-13 00:00:00');       // Sat 8 PM ET
    $tbd = $make(1, '2026-09-12 04:00:00', true);  // Sat, time TBD
    $early = $make(2, '2026-09-12 16:00:00');      // Sat noon ET
    $thursday = $make(3, '2026-09-10 23:30:00');   // Thu 7:30 PM ET
    rank($teams[0], 3, 1);

    expect((new WeekScoreboard($this->week3))->games->pluck('id')->all())
        ->toBe([$thursday->id, $early->id, $late->id, $tbd->id]);
});

it('filters by status and conference without changing the week', function (): void {
    $sunBelt = Team::factory()->count(2)->create(['conference' => 'Sun Belt']);
    $final = Game::factory()->between($this->georgia, $this->tennessee)->final(34, 20)->create(['week' => 3]);
    $upcoming = Game::factory()->between($sunBelt[0], $sunBelt[1])->create(['week' => 3]);
    $fcs = Game::factory()->between($this->georgia, $this->peay)->create(['week' => 3, 'start_at' => '2026-09-12 23:00:00']);

    $board = new WeekScoreboard($this->week3);

    expect($board->filtered(GameStatus::Final)->pluck('id')->all())->toBe([$final->id])
        ->and($board->filtered(GameStatus::Scheduled)->pluck('id')->all())->toBe([$upcoming->id, $fcs->id])
        ->and($board->filtered(conference: 'Sun Belt')->pluck('id')->all())->toBe([$upcoming->id])
        ->and($board->conferences())->toBe(['SEC', 'Sun Belt'])
        ->and($board->games)->toHaveCount(3)
        ->and($board->counts())->toBe(['scheduled' => 2, 'in_progress' => 0, 'final' => 1]);
});

it('searches team names and abbreviations without changing the week', function (): void {
    $sanJose = Team::factory()->create(['name' => 'San José State', 'abbreviation' => 'SJSU']);
    $georgiaGame = Game::factory()->between($this->georgia, $this->tennessee)->create(['week' => 3]);
    $sanJoseGame = Game::factory()->between($sanJose, $this->peay)->create(['week' => 3, 'start_at' => '2026-09-12 23:00:00']);

    $board = new WeekScoreboard($this->week3);

    expect($board->filtered(search: 'georgia')->pluck('id')->all())->toBe([$georgiaGame->id])
        ->and($board->filtered(search: ' TENN ')->pluck('id')->all())->toBe([$georgiaGame->id])
        ->and($board->filtered(search: 'san jose')->pluck('id')->all())->toBe([$sanJoseGame->id])
        ->and($board->filtered(search: 'sjsu')->pluck('id')->all())->toBe([$sanJoseGame->id])
        ->and($board->filtered(search: 'peay')->pluck('id')->all())->toBe([$sanJoseGame->id])
        ->and($board->filtered(search: 'nobody'))->toBeEmpty()
        ->and($board->filtered(search: '')->count())->toBe(2)
        ->and($board->games)->toHaveCount(2);
});

it('combines search with the other filters', function (): void {
    $final = Game::factory()->between($this->georgia, $this->tennessee)->final(34, 20)->create(['week' => 3]);
    Game::factory()->between($this->georgia, $this->peay)->create(['week' => 3, 'start_at' => '2026-09-12 23:00:00']);

    expect((new WeekScoreboard($this->week3))->filtered(GameStatus::Final, search: 'georgia')->pluck('id')->all())
        ->toBe([$final->id]);
});
