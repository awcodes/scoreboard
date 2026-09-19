<?php

declare(strict_types=1);

namespace App\Cfbd;

use App\Cfbd\Data\CalendarWeekData;
use App\Cfbd\Data\GameData;
use App\Cfbd\Data\GameMediaData;
use App\Cfbd\Data\PollRankData;
use App\Cfbd\Data\ScoreboardGameData;
use App\Cfbd\Data\TeamData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use ValueError;

/**
 * The only place that talks to CollegeFootballData. Everything leaving this
 * class is a DTO; raw payloads never reach models or views.
 */
final class CfbdClient
{
    /**
     * @return list<TeamData>
     */
    public function teams(int $season): array
    {
        return $this->mapList($this->get('/teams', ['year' => $season]), TeamData::fromPayload(...));
    }

    /**
     * @return list<CalendarWeekData>
     */
    public function calendar(int $season): array
    {
        return $this->mapList($this->get('/calendar', ['year' => $season]), CalendarWeekData::fromPayload(...));
    }

    /**
     * Games involving at least one team of the given classification.
     * Pass a week to limit to that week; omit it for the whole season.
     *
     * @return list<GameData>
     */
    public function games(int $season, string $classification, string $seasonType = 'both', ?int $week = null): array
    {
        $payload = $this->get('/games', array_filter([
            'year' => $season,
            'seasonType' => $seasonType,
            'week' => $week,
            'classification' => $classification,
        ]));

        // Ignore all-star and spring games.
        $payload = array_values(array_filter($payload, GameData::supportsSeasonType(...)));

        return $this->mapList($payload, GameData::fromPayload(...));
    }

    /**
     * @return list<GameMediaData>
     */
    public function media(int $season, string $seasonType = 'both', ?int $week = null): array
    {
        return $this->mapList($this->get('/games/media', array_filter([
            'year' => $season,
            'seasonType' => $seasonType,
            'week' => $week,
        ])), GameMediaData::fromPayload(...));
    }

    /**
     * @return list<PollRankData>
     */
    public function rankings(int $season): array
    {
        return PollRankData::listFromPayload($this->get('/rankings', [
            'year' => $season,
            'seasonType' => 'both',
        ]));
    }

    /**
     * Live state for games around the current day. Requires a CFBD tier
     * that includes scoreboard access.
     *
     * @return list<ScoreboardGameData>
     */
    public function scoreboard(string $classification = 'fbs'): array
    {
        return $this->mapList(
            $this->get('/scoreboard', ['classification' => $classification]),
            ScoreboardGameData::fromPayload(...),
        );
    }

    /**
     * Tier, remaining calls and feature access for the configured key.
     */
    public function info(): ?array
    {
        return $this->get('/info', [], expectList: false);
    }

    /**
     * Raw response body, used only by the provider spike command.
     */
    public function raw(string $endpoint, array $query = []): mixed
    {
        return $this->get($endpoint, $query, expectList: false);
    }

    private function get(string $endpoint, array $query, bool $expectList = true): mixed
    {
        try {
            $response = $this->request()->get($endpoint, $query);
        } catch (ConnectionException $e) {
            throw new CfbdException("Could not reach CFBD for [{$endpoint}]: {$e->getMessage()}", $e->getCode(), previous: $e);
        } catch (RequestException $e) {
            throw CfbdException::requestFailed($endpoint, $e->response->status(), $e->response->body());
        }

        if ($response->failed()) {
            throw CfbdException::requestFailed($endpoint, $response->status(), $response->body());
        }

        $json = $response->json();

        if ($expectList && (! is_array($json) || ! array_is_list($json))) {
            throw CfbdException::unexpectedResponse($endpoint);
        }

        return $json;
    }

    private function request(): PendingRequest
    {
        $key = config('services.cfbd.key');

        if (blank($key)) {
            throw CfbdException::missingKey();
        }

        return Http::baseUrl(config('services.cfbd.base_url'))
            ->withToken($key)
            ->acceptJson()
            ->connectTimeout(config('services.cfbd.connect_timeout'))
            ->timeout(config('services.cfbd.timeout'))
            ->retry(
                times: 3,
                sleepMilliseconds: fn (int $attempt): int => $attempt * 1000,
                when: fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->status() === 429 || $e->response->serverError())),
                throw: false,
            );
    }

    /**
     * Map payload rows to DTOs, skipping (and logging) individual bad rows.
     *
     * @template T
     *
     * @param  callable(array): T  $map
     * @return list<T>
     */
    private function mapList(array $rows, callable $map): array
    {
        $items = [];

        foreach ($rows as $row) {
            try {
                $items[] = $map(is_array($row) ? $row : []);
            } catch (InvalidPayload|ValueError $e) {
                Log::warning('Skipping invalid CFBD record: '.$e->getMessage());
            }
        }

        return $items;
    }
}
