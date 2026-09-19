<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SeasonType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class SeasonWeek extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * The week being played (or next to be played) at the given moment.
     * Before the season starts this is week one; after it ends, the final week.
     */
    public static function current(?Carbon $now = null): ?self
    {
        $now ??= now();

        return self::query()->where('ends_at', '>=', $now)->ordered()->first()
            ?? self::query()->ordered('desc')->first();
    }

    /**
     * The season to sync when none is given. January belongs to the previous
     * season's postseason; from March on, the upcoming season.
     */
    public static function defaultSeason(?Carbon $now = null): int
    {
        $now ??= now();

        return $now->month >= 3 ? $now->year : $now->year - 1;
    }

    /**
     * Regular weeks are numbered (`3`); postseason weeks are `post`, `post-2`, ...
     *
     * @return array{0: SeasonType, 1: int}
     */
    public static function parseSlug(string $slug): array
    {
        if (preg_match('/^post(?:-(\d+))?$/', $slug, $m)) {
            return [SeasonType::Postseason, (int) ($m[1] ?? 1)];
        }

        return [SeasonType::Regular, (int) $slug];
    }

    public function scopeOrdered(Builder $query, string $direction = 'asc'): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy('season', $direction)
            ->orderByRaw("case season_type when 'regular' then 0 else 1 end {$direction}")
            ->orderBy('week', $direction);
    }

    public function scopeForSlug(Builder $query, int $season, string $slug): void
    {
        [$type, $week] = self::parseSlug($slug);

        $query->where('season', $season)->where('season_type', $type)->where('week', $week);
    }

    public function slug(): string
    {
        if ($this->season_type === SeasonType::Regular) {
            return (string) $this->week;
        }

        return $this->week === 1 ? 'post' : "post-{$this->week}";
    }

    public function label(): string
    {
        if ($this->season_type === SeasonType::Regular) {
            return "Week {$this->week}";
        }

        return $this->week === 1 ? 'Postseason' : "Postseason {$this->week}";
    }

    /**
     * Compact label for the week navigation on narrow screens.
     */
    public function shortLabel(): string
    {
        if ($this->season_type === SeasonType::Regular) {
            return "Wk {$this->week}";
        }

        return $this->week === 1 ? 'Post' : "Post {$this->week}";
    }

    public function url(): string
    {
        return route('week', ['season' => $this->season, 'week' => $this->slug()]);
    }

    public function previous(): ?self
    {
        return self::query()->ordered()->get()
            ->takeUntil(fn (self $week) => $week->is($this))
            ->last();
    }

    public function next(): ?self
    {
        return self::query()->ordered()->get()
            ->skipUntil(fn (self $week) => $week->is($this))
            ->skip(1)
            ->first();
    }

    /**
     * "September 10–12, 2026", based on actual kickoffs when known.
     */
    public function dateRange(): string
    {
        $tz = config('app.display_timezone');
        $start = ($this->first_game_at ?? $this->starts_at)->copy()->setTimezone($tz);
        $end = ($this->last_game_at ?? $this->ends_at)->copy()->setTimezone($tz);

        if ($start->isSameDay($end)) {
            return $start->format('F j, Y');
        }

        if ($start->year !== $end->year) {
            return $start->format('F j, Y').' – '.$end->format('F j, Y');
        }

        if ($start->month !== $end->month) {
            return $start->format('F j').' – '.$end->format('F j, Y');
        }

        return $start->format('F j').'–'.$end->format('j, Y');
    }

    protected function casts(): array
    {
        return [
            'season_type' => SeasonType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'first_game_at' => 'datetime',
            'last_game_at' => 'datetime',
        ];
    }
}
