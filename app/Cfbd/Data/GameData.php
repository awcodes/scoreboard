<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

use App\Enums\SeasonType;
use Illuminate\Support\Carbon;

/**
 * A game from `/games`: the schedule of record and the source of final results.
 */
final readonly class GameData
{
    use ReadsPayload;

    public function __construct(
        public int $providerId,
        public int $season,
        public int $week,
        public SeasonType $seasonType,
        public Carbon $startAt,
        public bool $startTimeTbd,
        public bool $completed,
        public bool $neutralSite,
        public bool $conferenceGame,
        public ?string $venue,
        public GameTeamData $home,
        public GameTeamData $away,
    ) {}

    public static function fromPayload(array $payload): self
    {
        return new self(
            providerId: (int) self::required($payload, 'id'),
            season: (int) self::required($payload, 'season'),
            week: (int) self::required($payload, 'week'),
            seasonType: SeasonType::from(self::required($payload, 'seasonType')),
            startAt: self::date($payload, 'startDate'),
            startTimeTbd: (bool) ($payload['startTimeTBD'] ?? false),
            completed: (bool) ($payload['completed'] ?? false),
            neutralSite: (bool) ($payload['neutralSite'] ?? false),
            conferenceGame: (bool) ($payload['conferenceGame'] ?? false),
            venue: self::string($payload, 'venue'),
            home: new GameTeamData(
                providerId: (int) self::required($payload, 'homeId'),
                name: (string) self::required($payload, 'homeTeam'),
                conference: self::string($payload, 'homeConference'),
                classification: self::classification($payload, 'homeClassification'),
                points: self::int($payload, 'homePoints'),
            ),
            away: new GameTeamData(
                providerId: (int) self::required($payload, 'awayId'),
                name: (string) self::required($payload, 'awayTeam'),
                conference: self::string($payload, 'awayConference'),
                classification: self::classification($payload, 'awayClassification'),
                points: self::int($payload, 'awayPoints'),
            ),
        );
    }

    public static function supportsSeasonType(array $payload): bool
    {
        return SeasonType::tryFrom($payload['seasonType'] ?? '') !== null;
    }
}
