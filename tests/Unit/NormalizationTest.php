<?php

declare(strict_types=1);

use App\Cfbd\Data\CalendarWeekData;
use App\Cfbd\Data\GameData;
use App\Cfbd\Data\GameMediaData;
use App\Cfbd\Data\PollRankData;
use App\Cfbd\Data\ScoreboardGameData;
use App\Cfbd\Data\TeamData;
use App\Cfbd\InvalidPayload;
use App\Enums\GameStatus;
use App\Enums\SeasonType;

it('normalizes a completed game', function (): void {
    $game = GameData::fromPayload(cfbdFixture('games-week3-fbs')[0]);

    expect($game->providerId)->toBe(401800001)
        ->and($game->seasonType)->toBe(SeasonType::Regular)
        ->and($game->completed)->toBeTrue()
        ->and($game->startAt->toIso8601String())->toBe('2026-09-12T19:30:00+00:00')
        ->and($game->home->name)->toBe('Georgia')
        ->and($game->home->points)->toBe(34)
        ->and($game->away->classification)->toBe('fbs');
});

it('normalizes an FBS vs FCS game with a TBD kickoff', function (): void {
    $game = GameData::fromPayload(cfbdFixture('games-week3-fbs')[2]);

    expect($game->startTimeTbd)->toBeTrue()
        ->and($game->completed)->toBeFalse()
        ->and($game->home->points)->toBeNull()
        ->and($game->away->classification)->toBe('fcs');
});

it('lower-cases classifications', function (): void {
    $payload = cfbdFixture('games-week3-fbs')[0];
    $payload['homeClassification'] = 'FBS';

    expect(GameData::fromPayload($payload)->home->classification)->toBe('fbs');
});

it('rejects games missing required fields', function (): void {
    $payload = cfbdFixture('games-week3-fbs')[0];
    unset($payload['homeId']);

    GameData::fromPayload($payload);
})->throws(InvalidPayload::class);

it('prefers television outlets over streaming and ignores radio', function (): void {
    $media = fn (string $type, string $outlet): GameMediaData => new GameMediaData(1, $type, $outlet);

    expect(GameMediaData::networkFor([$media('radio', 'Bulldog Radio'), $media('tv', 'ABC'), $media('web', 'ESPN+')]))->toBe('ABC')
        ->and(GameMediaData::networkFor([$media('tv', 'FOX'), $media('tv', 'FS1')]))->toBe('FOX / FS1')
        ->and(GameMediaData::networkFor([$media('web', 'ESPN+')]))->toBe('ESPN+')
        ->and(GameMediaData::networkFor([$media('radio', 'Bulldog Radio')]))->toBeNull();
});

it('maps scoreboard statuses', function (): void {
    [$live, $final, $scheduled] = array_map(ScoreboardGameData::fromPayload(...), array_slice(cfbdFixture('scoreboard'), 0, 3));

    expect($live->status)->toBe(GameStatus::InProgress)
        ->and($live->period)->toBe(3)
        ->and($live->clock)->toBe('04:32')
        ->and($live->homePoints)->toBe(21)
        ->and($final->status)->toBe(GameStatus::Final)
        ->and($scheduled->status)->toBe(GameStatus::Scheduled)
        ->and($scheduled->tv)->toBeNull();
});

it('flattens rankings into one row per ranked team', function (): void {
    $rows = PollRankData::listFromPayload(cfbdFixture('rankings'));

    expect($rows)->toHaveCount(15)
        ->and($rows[0]->poll)->toBe('AP Top 25')
        ->and($rows[0]->week)->toBe(1)
        ->and($rows[0]->teamProviderId)->toBe(61)
        ->and($rows[0]->rank)->toBe(5);
});

it('normalizes teams and calendar weeks', function (): void {
    $team = TeamData::fromPayload(cfbdFixture('teams')[0]);
    $week = CalendarWeekData::fromPayload(cfbdFixture('calendar')[2]);

    expect($team->name)->toBe('Georgia')
        ->and($team->logoUrl)->toContain('/500/61.png')
        ->and($week->week)->toBe(3)
        ->and($week->firstGameAt->toIso8601String())->toBe('2026-09-10T23:30:00+00:00');
});

it('normalizes duplicate outlet names', function (): void {
    $row = fn (string $outlet): GameMediaData => GameMediaData::fromPayload(['id' => 1, 'mediaType' => 'tv', 'outlet' => $outlet]);

    expect(GameMediaData::networkFor([$row('CW'), $row('The CW Network')]))->toBe('CW');
});

it('uses the primary outlet from pipe-joined scoreboard TV', function (): void {
    $payload = ['id' => 1, 'status' => 'scheduled', 'startDate' => '2026-09-19T16:00:00.000Z'];

    expect(ScoreboardGameData::fromPayload([...$payload, 'tv' => 'ESPN | Disney+'])->tv)->toBe('ESPN')
        ->and(ScoreboardGameData::fromPayload([...$payload, 'tv' => 'USA Net'])->tv)->toBe('USA Network')
        ->and(ScoreboardGameData::fromPayload($payload)->tv)->toBeNull();
});
