<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

/**
 * One side of a game, as embedded in game and scoreboard payloads.
 */
final readonly class GameTeamData
{
    public function __construct(
        public int $providerId,
        public string $name,
        public ?string $conference,
        public ?string $classification,
        public ?int $points,
    ) {}
}
