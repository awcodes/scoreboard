<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

use App\Enums\SeasonType;
use Illuminate\Support\Carbon;

final readonly class CalendarWeekData
{
    use ReadsPayload;

    public function __construct(
        public int $season,
        public int $week,
        public SeasonType $seasonType,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public ?Carbon $firstGameAt,
        public ?Carbon $lastGameAt,
    ) {}

    public static function fromPayload(array $payload): self
    {
        return new self(
            season: (int) self::required($payload, 'season'),
            week: (int) self::required($payload, 'week'),
            seasonType: SeasonType::from(self::required($payload, 'seasonType')),
            startsAt: self::date($payload, 'startDate'),
            endsAt: self::date($payload, 'endDate'),
            firstGameAt: isset($payload['firstGameStart']) ? self::date($payload, 'firstGameStart') : null,
            lastGameAt: isset($payload['lastGameStart']) ? self::date($payload, 'lastGameStart') : null,
        );
    }
}
