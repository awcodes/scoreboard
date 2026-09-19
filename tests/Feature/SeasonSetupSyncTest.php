<?php

declare(strict_types=1);

use App\Enums\Poll;
use App\Enums\SeasonType;
use App\Models\SeasonWeek;
use App\Models\Team;
use App\Models\TeamRanking;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

beforeEach(fn () => fakeCfbd());

it('syncs FBS and FCS teams only', function (): void {
    artisan('cfbd:sync-teams', ['--season' => 2026])->assertSuccessful();

    expect(Team::pluck('name')->sort()->values()->all())
        ->toBe(['App State', 'Austin Peay', 'Clemson', 'Georgia', 'Georgia Southern', 'Samford', 'Tennessee'])
        ->and(Team::firstWhere('provider_id', 61))->abbreviation->toBe('UGA')->conference->toBe('SEC')->classification->toBe('fbs');
});

it('refreshes lower-division opponents already known locally', function (): void {
    Team::factory()->create(['provider_id' => 2433, 'name' => 'Valdosta St', 'classification' => 'ii']);

    artisan('cfbd:sync-teams', ['--season' => 2026]);

    expect(Team::firstWhere('provider_id', 2433)->name)->toBe('Valdosta State');
});

it('syncs the season calendar', function (): void {
    artisan('cfbd:sync-calendar', ['--season' => 2026])->assertSuccessful();

    expect(SeasonWeek::count())->toBe(5)
        ->and(SeasonWeek::where('season_type', SeasonType::Postseason)->count())->toBe(1);
});

it('stores only AP rankings, per week', function (): void {
    artisan('cfbd:sync-rankings', ['--season' => 2026])->assertSuccessful();

    expect(TeamRanking::count())->toBe(9)
        ->and(TeamRanking::pluck('poll')->unique()->all())->toBe([Poll::Ap]);

    $georgia = Team::firstWhere('provider_id', 61);
    expect($georgia->rankings()->where('week', 2)->value('rank'))->toBe(5)
        ->and($georgia->rankings()->where('week', 3)->value('rank'))->toBe(4);
});

it('keeps rankings when CFBD returns none', function (): void {
    artisan('cfbd:sync-rankings', ['--season' => 2026]);

    Http::swap(new Factory);
    fakeCfbd(['/rankings' => []]);
    artisan('cfbd:sync-rankings', ['--season' => 2026]);

    expect(TeamRanking::count())->toBe(9);
});
