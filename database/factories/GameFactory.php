<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Enums\SeasonType;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
final class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => fake()->unique()->numberBetween(100000, 999999),
            'season' => 2026,
            'season_type' => SeasonType::Regular,
            'week' => 1,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'home_classification' => 'fbs',
            'away_classification' => 'fbs',
            'home_conference' => 'SEC',
            'away_conference' => 'SEC',
            'start_at' => '2026-08-29 19:30:00',
            'status' => GameStatus::Scheduled,
        ];
    }

    public function between(Team $home, Team $away): static
    {
        return $this->state([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'home_classification' => $home->classification,
            'away_classification' => $away->classification,
            'home_conference' => $home->conference,
            'away_conference' => $away->conference,
        ]);
    }

    public function final(int $home, int $away): static
    {
        return $this->state([
            'status' => GameStatus::Final,
            'home_score' => $home,
            'away_score' => $away,
            'completed_at' => now(),
        ]);
    }
}
