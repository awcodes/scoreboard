<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\SeasonWeek;
use App\Support\WeekScoreboard;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property-read SeasonWeek|null $week
 * @property-read WeekScoreboard|null $board
 * @property-read Collection<int, Game> $games
 * @property-read bool $polling
 */
final class Scoreboard extends Component
{
    #[Locked]
    public ?int $weekId = null;

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $conference = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(?int $season = null, ?string $week = null): void
    {
        $seasonWeek = $season === null
            ? SeasonWeek::current()
            : SeasonWeek::query()->forSlug($season, $week)->first();

        abort_if($season !== null && $seasonWeek === null, 404);

        $this->weekId = $seasonWeek?->id;
    }

    #[Computed]
    public function week(): ?SeasonWeek
    {
        return $this->weekId ? SeasonWeek::find($this->weekId) : null;
    }

    #[Computed]
    public function board(): ?WeekScoreboard
    {
        return $this->week ? new WeekScoreboard($this->week) : null;
    }

    #[Computed]
    public function games()
    {
        return $this->board?->filtered(
            GameStatus::tryFrom($this->statusFilterValue()),
            in_array($this->conference, $this->board->conferences(), true) ? $this->conference : null,
            $this->search,
        ) ?? collect();
    }

    /**
     * Refresh from the local database while games are on. This never
     * reaches CFBD; the scheduler controls upstream traffic.
     */
    #[Computed]
    public function polling(): bool
    {
        return $this->week !== null
            && $this->week->is(SeasonWeek::current())
            && Game::query()->inWeek($this->week)->inLiveWindow()->exists();
    }

    /**
     * URL for the given week carrying the active filters.
     */
    public function weekUrl(SeasonWeek $week): string
    {
        return $week->url().$this->queryString();
    }

    public function filterUrl(string $status): string
    {
        return ($this->week?->url() ?? url('/')).$this->queryString(['status' => $status]);
    }

    public function render()
    {
        return view('livewire.scoreboard')
            ->title($this->week ? "{$this->week->label()} · {$this->week->season} · Scoreboard" : 'Scoreboard');
    }

    private function statusFilterValue(): string
    {
        return match ($this->status) {
            'live' => GameStatus::InProgress->value,
            'final' => GameStatus::Final->value,
            'upcoming' => GameStatus::Scheduled->value,
            default => 'all',
        };
    }

    private function queryString(array $overrides = []): string
    {
        $query = array_filter(
            array_merge(['status' => $this->status, 'conference' => $this->conference, 'q' => mb_trim($this->search)], $overrides),
            fn ($value, $key): bool => $value !== '' && ($key !== 'status' || $value !== 'all'),
            ARRAY_FILTER_USE_BOTH,
        );

        return $query !== [] ? '?'.http_build_query($query) : '';
    }
}
