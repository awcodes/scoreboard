<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

use App\Enums\GameStatus;
use Illuminate\Support\Carbon;

/**
 * A game from `/scoreboard`: current state for games around today.
 */
final readonly class ScoreboardGameData
{
    use ReadsPayload;

    public function __construct(
        public int $providerId,
        public GameStatus $status,
        public Carbon $startAt,
        public bool $startTimeTbd,
        public ?int $period,
        public ?string $clock,
        public ?string $tv,
        public ?int $homePoints,
        public ?int $awayPoints,
    ) {}

    public static function fromPayload(array $payload): self
    {
        return new self(
            providerId: (int) self::required($payload, 'id'),
            status: match (self::required($payload, 'status')) {
                'in_progress' => GameStatus::InProgress,
                'completed' => GameStatus::Final,
                default => GameStatus::Scheduled,
            },
            startAt: self::date($payload, 'startDate'),
            startTimeTbd: (bool) ($payload['startTimeTBD'] ?? false),
            period: self::int($payload, 'period'),
            clock: self::string($payload, 'clock'),
            tv: self::network(self::string($payload, 'tv')),
            homePoints: self::int($payload, 'homeTeam.points'),
            awayPoints: self::int($payload, 'awayTeam.points'),
        );
    }

    /**
     * The scoreboard joins simulcasts with pipes ("ESPN | Disney+"); the
     * first outlet is the primary broadcast.
     */
    private static function network(?string $tv): ?string
    {
        return $tv === null ? null : GameMediaData::normalizeOutlet(explode('|', $tv)[0]);
    }
}
