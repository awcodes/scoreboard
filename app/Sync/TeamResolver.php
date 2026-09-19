<?php

declare(strict_types=1);

namespace App\Sync;

use App\Cfbd\Data\GameTeamData;
use App\Models\Team;

/**
 * Finds or creates local teams by CFBD id. Games may reference teams that
 * the teams sync never saw (FCS and lower opponents), so games can create
 * minimal team records on their own.
 */
final class TeamResolver
{
    /** @var array<int, Team> */
    private array $teams = [];

    public function forGame(GameTeamData $side): Team
    {
        return $this->teams[$side->providerId] ??= Team::firstOrCreate(
            ['provider_id' => $side->providerId],
            [
                'name' => $side->name,
                'conference' => $side->conference,
                'classification' => $side->classification,
            ],
        );
    }

    public function find(int $providerId, string $name): Team
    {
        return $this->teams[$providerId] ??= Team::firstOrCreate(
            ['provider_id' => $providerId],
            ['name' => $name],
        );
    }
}
