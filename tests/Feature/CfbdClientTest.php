<?php

declare(strict_types=1);

use App\Cfbd\CfbdClient;
use App\Cfbd\CfbdException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(fn () => Sleep::fake());

it('sends the API key as a bearer token', function (): void {
    fakeCfbd();

    app(CfbdClient::class)->calendar(2026);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
        && str_contains($request->url(), '/calendar?year=2026'));
});

it('refuses to call CFBD without a key', function (): void {
    config(['services.cfbd.key' => null]);
    Http::preventStrayRequests();

    app(CfbdClient::class)->calendar(2026);
})->throws(CfbdException::class, 'CFBD_API_KEY');

it('retries rate limiting and server errors before failing', function (): void {
    fakeCfbd(['/calendar' => Http::response('slow down', 429)]);

    expect(fn () => app(CfbdClient::class)->calendar(2026))->toThrow(CfbdException::class, 'HTTP 429');

    Http::assertSentCount(3);
});

it('retries and reports connection failures', function (): void {
    fakeCfbd(['/calendar' => Http::failedConnection()]);

    expect(fn () => app(CfbdClient::class)->calendar(2026))->toThrow(CfbdException::class, 'Could not reach CFBD');

    Http::assertSentCount(3);
});

it('rejects a response that is not a list', function (): void {
    fakeCfbd(['/calendar' => ['message' => 'oops']]);

    app(CfbdClient::class)->calendar(2026);
})->throws(CfbdException::class, 'unexpected response');

it('skips invalid records instead of failing the batch', function (): void {
    $games = cfbdFixture('games-week3-fbs');
    unset($games[1]['startDate']);
    fakeCfbd(['/games' => $games]);

    expect(app(CfbdClient::class)->games(2026, 'fbs'))->toHaveCount(2);
});

it('ignores all-star games', function (): void {
    fakeCfbd();

    $ids = collect(app(CfbdClient::class)->games(2026, 'fcs'))->pluck('providerId');

    expect($ids)->not->toContain(401899999)->toHaveCount(3);
});
