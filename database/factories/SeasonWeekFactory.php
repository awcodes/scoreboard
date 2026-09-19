<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SeasonType;
use App\Models\SeasonWeek;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SeasonWeek>
 */
final class SeasonWeekFactory extends Factory
{
    public function definition(): array
    {
        return [
            'season' => 2026,
            'season_type' => SeasonType::Regular,
            'week' => 1,
            'starts_at' => '2026-08-25 07:00:00',
            'ends_at' => '2026-09-01 06:59:00',
        ];
    }

    /**
     * Regular-season week N of 2026, Tuesday to Monday.
     */
    public function week(int $week): static
    {
        $start = Carbon::parse('2026-08-25 07:00:00', 'UTC')->addWeeks($week - 1);

        return $this->state([
            'week' => $week,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addWeek()->subMinute(),
        ]);
    }
}
