<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

function cfbdFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/cfbd/{$name}.json"), true);
}

/**
 * Fake CFBD with saved fixtures. Pass overrides as [endpoint => payload|response].
 */
function fakeCfbd(array $overrides = []): void
{
    Http::preventStrayRequests();

    Http::fake(function (Request $request) use ($overrides) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if (array_key_exists($path, $overrides)) {
            $override = $overrides[$path];

            return is_array($override) ? Http::response($override) : $override;
        }

        return Http::response(match ($path) {
            '/teams' => cfbdFixture('teams'),
            '/calendar' => cfbdFixture('calendar'),
            '/games' => cfbdFixture("games-week3-{$query['classification']}"),
            '/games/media' => cfbdFixture('media-week3'),
            '/rankings' => cfbdFixture('rankings'),
            '/scoreboard' => cfbdFixture('scoreboard'),
            '/info' => cfbdFixture('info'),
            default => throw new RuntimeException("No CFBD fixture for [{$path}]."),
        });
    });
}
