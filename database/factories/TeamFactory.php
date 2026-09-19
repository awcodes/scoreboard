<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
final class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider_id' => fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->unique()->city(),
            'conference' => 'SEC',
            'classification' => 'fbs',
        ];
    }

    public function fcs(): static
    {
        return $this->state(['classification' => 'fcs', 'conference' => 'UAC']);
    }
}
