<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

use App\Enums\SeasonType;

final readonly class PollRankData
{
    use ReadsPayload;

    public function __construct(
        public int $season,
        public SeasonType $seasonType,
        public int $week,
        public string $poll,
        public int $rank,
        public int $teamProviderId,
        public string $school,
    ) {}

    /**
     * Flatten `/rankings` (weeks → polls → ranks) into one row per ranked team.
     *
     * @return list<self>
     */
    public static function listFromPayload(array $pollWeeks): array
    {
        $rows = [];

        foreach ($pollWeeks as $pollWeek) {
            $seasonType = SeasonType::tryFrom($pollWeek['seasonType'] ?? '');

            if ($seasonType === null) {
                continue;
            }

            foreach ($pollWeek['polls'] ?? [] as $poll) {
                foreach ($poll['ranks'] ?? [] as $rank) {
                    if (! isset($rank['rank'], $rank['teamId'])) {
                        continue;
                    }

                    $rows[] = new self(
                        season: (int) self::required($pollWeek, 'season'),
                        seasonType: $seasonType,
                        week: (int) self::required($pollWeek, 'week'),
                        poll: (string) self::required($poll, 'poll'),
                        rank: (int) $rank['rank'],
                        teamProviderId: (int) $rank['teamId'],
                        school: (string) ($rank['school'] ?? ''),
                    );
                }
            }
        }

        return $rows;
    }
}
