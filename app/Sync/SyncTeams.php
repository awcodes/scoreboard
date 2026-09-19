<?php

declare(strict_types=1);

namespace App\Sync;

use App\Cfbd\CfbdClient;
use App\Cfbd\Data\TeamData;
use App\Models\Team;

final readonly class SyncTeams
{
    public function __construct(private CfbdClient $cfbd) {}

    /**
     * Store FBS and FCS teams, and refresh any lower-division team already
     * known locally because it appeared as an opponent.
     */
    public function __invoke(int $season): int
    {
        $known = Team::pluck('provider_id')->flip();

        $teams = collect($this->cfbd->teams($season))->filter(
            fn (TeamData $team): bool => in_array($team->classification, ['fbs', 'fcs'], true)
                || $known->has($team->providerId)
        );

        foreach ($teams as $team) {
            Team::updateOrCreate(['provider_id' => $team->providerId], [
                'name' => $team->name,
                'abbreviation' => $team->abbreviation,
                'conference' => $team->conference,
                'classification' => $team->classification,
                'logo_url' => $team->logoUrl,
            ]);
        }

        return $teams->count();
    }
}
