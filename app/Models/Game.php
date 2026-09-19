<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GameStatus;
use App\Enums\SeasonType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Game extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /**
     * Games involving at least one FBS team (including FBS vs. FCS).
     */
    public function scopeFbs(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('home_classification', 'fbs')
            ->orWhere('away_classification', 'fbs'));
    }

    public function scopeInWeek(Builder $query, SeasonWeek $week): void
    {
        $query->where('season', $week->season)
            ->where('season_type', $week->season_type)
            ->where('week', $week->week);
    }

    /**
     * Games that are live, or should be about to start or finish. Drives
     * whether the scheduler polls the upstream scoreboard at all.
     */
    public function scopeInLiveWindow(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('status', GameStatus::InProgress)
            ->orWhere(fn (Builder $q) => $q
                ->where('status', GameStatus::Scheduled)
                ->where('start_time_tbd', false)
                ->whereBetween('start_at', [now()->subHours(6), now()->addMinutes(10)])));
    }

    /**
     * Final games are treated as immutable once they have settled.
     */
    public function isFrozen(): bool
    {
        return $this->status === GameStatus::Final
            && $this->completed_at !== null
            && $this->completed_at->lte(now()->subHours(config('services.cfbd.freeze_after_hours')));
    }

    public function stateLabel(): string
    {
        return match ($this->status) {
            GameStatus::Final => $this->period > 4 ? 'Final/'.$this->periodLabel() : 'Final',
            GameStatus::InProgress => $this->liveLabel(),
            GameStatus::Scheduled => $this->kickoffLabel(),
        };
    }

    public function kickoffLabel(): string
    {
        $start = $this->start_at->copy()->setTimezone(config('app.display_timezone'));

        return $start->format('D').' '.($this->start_time_tbd ? 'TBD' : $start->format('g:i A'));
    }

    public function networkLabel(): string
    {
        return $this->network ?: 'TV TBD';
    }

    public function hasScore(): bool
    {
        return $this->status !== GameStatus::Scheduled
            && $this->home_score !== null
            && $this->away_score !== null;
    }

    protected function casts(): array
    {
        return [
            'season_type' => SeasonType::class,
            'status' => GameStatus::class,
            'start_at' => 'datetime',
            'start_time_tbd' => 'boolean',
            'neutral_site' => 'boolean',
            'conference_game' => 'boolean',
            'completed_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    private function liveLabel(): string
    {
        if ($this->period === null) {
            return 'Live';
        }

        $clock = $this->clockLabel();

        if ($this->period === 2 && $clock === '0:00') {
            return 'Halftime';
        }

        return $clock === null || $this->period > 4
            ? $this->periodLabel()
            : $this->periodLabel().' · '.$clock;
    }

    private function periodLabel(): string
    {
        return match (true) {
            $this->period === 1 => '1st',
            $this->period === 2 => '2nd',
            $this->period === 3 => '3rd',
            $this->period === 4 => '4th',
            $this->period === 5 => 'OT',
            default => ($this->period - 4).'OT',
        };
    }

    /**
     * CFBD clocks look like "04:32" or "00:04:32"; display "4:32".
     */
    private function clockLabel(): ?string
    {
        if (! $this->clock) {
            return null;
        }

        $parts = array_slice(explode(':', $this->clock), -2);

        if (count($parts) !== 2) {
            return $this->clock;
        }

        return ((int) $parts[0]).':'.mb_str_pad((string) (int) $parts[1], 2, '0', STR_PAD_LEFT);
    }
}
