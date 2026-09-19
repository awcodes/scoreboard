<?php

declare(strict_types=1);

use App\Livewire\Scoreboard;
use App\Models\Game;
use App\Models\SeasonWeek;
use Livewire\Livewire;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(function (): void {
    travelTo('2026-09-12 20:00:00');
    fakeCfbd();
    artisan('cfbd:sync-calendar', ['--season' => 2026]);
    artisan('cfbd:sync-games', ['--season' => 2026]);
    artisan('cfbd:sync-rankings', ['--season' => 2026]);
    artisan('cfbd:sync-scoreboard');
});

it('opens on the current week', function (): void {
    get('/')
        ->assertOk()
        ->assertSeeText('Week 3')
        ->assertSeeText('September 10–12, 2026')
        ->assertSeeText('‹ Week 2', false)
        ->assertSeeText('Week 4');
});

it('shows every FBS game in the week, including FBS vs FCS', function (): void {
    get('/2026/3')
        ->assertOk()
        ->assertSeeText('Georgia Southern')
        ->assertSeeText('App State')
        ->assertSeeText('Austin Peay')
        ->assertDontSeeText('Samford')
        ->assertSeeText('1 live · 2 final');
});

it('renders team rows as name, record, then AP rank', function (): void {
    get('/2026/3')
        ->assertSeeInOrder(['Tennessee', '(0-1)', '#17', '20'], false)
        ->assertSeeInOrder(['Georgia', '(1-0)', '#4', '34'], false)
        ->assertSeeInOrder(['Georgia Southern', '(1-0)'], false);
});

it('shows game state and networks', function (): void {
    get('/2026/3')
        ->assertSeeText('Final')
        ->assertSeeText('3rd · 4:32')
        ->assertSeeText('ABC')
        ->assertSeeText('ESPN+');
});

it('shows kickoff times and TV TBD for upcoming games', function (): void {
    Game::where('provider_id', 401800003)->update(['status' => 'scheduled', 'network' => null, 'home_score' => null, 'away_score' => null]);

    get('/2026/3')->assertSeeText('Sat TBD')->assertSeeText('TV TBD');
    get('/2026/4')->assertOk()->assertSeeText('No FBS games this week.');
});

it('supports postseason routes and 404s unknown weeks', function (): void {
    get('/2026/post')->assertOk()->assertSeeText('Postseason');
    get('/2026/9')->assertNotFound();
    get('/2031/1')->assertNotFound();
});

it('filters by status and conference', function (): void {
    Livewire::test(Scoreboard::class, ['season' => 2026, 'week' => '3'])
        ->set('status', 'live')
        ->assertSeeText('Austin Peay')
        ->assertDontSeeText('Georgia Southern')
        ->set('status', 'final')
        ->assertSeeText('Georgia Southern')
        ->assertDontSeeText('Austin Peay')
        ->set('status', 'all')
        ->set('conference', 'Sun Belt')
        ->assertSeeText('Georgia Southern')
        ->assertDontSeeText('Tennessee')
        ->assertSeeText('1 live · 2 final');
});

it('applies filters from the query string without JavaScript', function (): void {
    get('/2026/3?status=final&conference=SEC')
        ->assertSeeText('Tennessee')
        ->assertDontSeeText('Georgia Southern')
        ->assertDontSeeText('Austin Peay');
});

it('carries filters when navigating weeks', function (): void {
    get('/2026/3?status=final')->assertSee('href="'.url('/2026/2').'?status=final"', false);
    get('/2026/3?q=georgia')->assertSee('href="'.url('/2026/4').'?q=georgia"', false);
});

it('searches teams', function (): void {
    Livewire::test(Scoreboard::class, ['season' => 2026, 'week' => '3'])
        ->set('search', 'app state')
        ->assertSeeText('Georgia Southern')
        ->assertDontSeeText('Tennessee')
        ->assertSeeText('1 live · 2 final')
        ->set('search', 'zzz')
        ->assertSeeText('No games match these filters.');
});

it('searches from the query string without JavaScript', function (): void {
    get('/2026/3?q=clemson')
        ->assertSeeText('Austin Peay')
        ->assertDontSeeText('Georgia Southern')
        ->assertSee('value="clemson"', false);
});

it('polls the local database only for the current week while games are on', function (): void {
    Livewire::test(Scoreboard::class, ['season' => 2026, 'week' => '3'])->assertSee('wire:poll.60s', false);
    Livewire::test(Scoreboard::class, ['season' => 2026, 'week' => '2'])->assertDontSee('wire:poll', false);
});

it('explains how to sync when there is no data', function (): void {
    SeasonWeek::query()->delete();

    get('/')->assertOk()->assertSeeText('php artisan cfbd:sync');
});
