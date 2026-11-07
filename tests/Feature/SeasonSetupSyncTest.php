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

function withCfpRankings(): array
{
    $weeks = cfbdFixture('rankings');
    $weeks[2]['polls'][] = ['poll' => 'Playoff Committee Rankings', 'isFinal' => null, 'ranks' => [
        ['rank' => 2, 'teamId' => 61, 'school' => 'Georgia', 'conference' => 'SEC', 'firstPlaceVotes' => null, 'points' => null],
    ]];

    return $weeks;
}

it('stores AP and CFP rankings per week, ignoring other polls', function (): void {
    Http::swap(new Factory);
    fakeCfbd(['/rankings' => withCfpRankings()]);
    artisan('cfbd:sync-rankings', ['--season' => 2026])->assertSuccessful();

    expect(TeamRanking::where('poll', Poll::Ap)->count())->toBe(9)
        ->and(TeamRanking::where('poll', Poll::Cfp)->count())->toBe(1);

    $georgia = Team::firstWhere('provider_id', 61);
    expect($georgia->rankings()->where('poll', Poll::Ap)->where('week', 2)->value('rank'))->toBe(5)
        ->and($georgia->rankings()->where('poll', Poll::Ap)->where('week', 3)->value('rank'))->toBe(4)
        ->and($georgia->rankings()->where('poll', Poll::Cfp)->where('week', 3)->value('rank'))->toBe(2);
});

it('keeps stored CFP rankings when a response has none', function (): void {
    Http::swap(new Factory);
    fakeCfbd(['/rankings' => withCfpRankings()]);
    artisan('cfbd:sync-rankings', ['--season' => 2026]);

    Http::swap(new Factory);
    fakeCfbd();
    artisan('cfbd:sync-rankings', ['--season' => 2026]);

    expect(TeamRanking::where('poll', Poll::Cfp)->count())->toBe(1);
});

it('keeps rankings when CFBD returns none', function (): void {
    artisan('cfbd:sync-rankings', ['--season' => 2026]);

    Http::swap(new Factory);
    fakeCfbd(['/rankings' => []]);
    artisan('cfbd:sync-rankings', ['--season' => 2026]);

    expect(TeamRanking::count())->toBe(9);
});
