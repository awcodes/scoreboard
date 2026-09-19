@php
    $week = $this->week;
    $board = $this->board;
    $filters = ['all' => 'All', 'live' => 'Live', 'final' => 'Final', 'upcoming' => 'Upcoming'];
@endphp

<div @if ($this->polling) wire:poll.60s @endif>
    <header class="border-line bg-page/95 sticky top-0 z-10 border-b pt-[env(safe-area-inset-top)] backdrop-blur">
        <nav
            aria-label="Weeks"
            class="mx-auto grid h-12 max-w-6xl grid-cols-[1fr_auto_1fr] items-center gap-2 px-4 text-sm"
        >
            <div class="justify-self-start">
                @if ($prev = $this->previousWeek)
                    <a
                        href="{{ $this->weekUrl($prev) }}"
                        wire:navigate
                        class="text-muted hover:text-fg -mx-2 px-2 py-2 whitespace-nowrap"
                        rel="prev"
                    >
                        <span aria-hidden="true">‹</span>
                        <span class="hidden min-[400px]:inline">{{ $prev->label() }}</span>
                        <span class="min-[400px]:hidden" aria-hidden="true">{{ $prev->shortLabel() }}</span>
                        <span class="sr-only min-[400px]:hidden">{{ $prev->label() }}</span>
                    </a>
                @endif
            </div>

            <h1 class="font-semibold tracking-wider whitespace-nowrap uppercase">
                {{ $week?->label() ?? 'Scoreboard' }}
            </h1>

            <div class="flex items-center gap-2 justify-self-end">
                @if ($next = $this->nextWeek)
                    <a
                        href="{{ $this->weekUrl($next) }}"
                        wire:navigate
                        class="text-muted hover:text-fg -mx-2 px-2 py-2 whitespace-nowrap"
                        rel="next"
                    >
                        <span class="hidden min-[400px]:inline">{{ $next->label() }}</span>
                        <span class="min-[400px]:hidden" aria-hidden="true">{{ $next->shortLabel() }}</span>
                        <span class="sr-only min-[400px]:hidden">{{ $next->label() }}</span>
                        <span aria-hidden="true">›</span>
                    </a>
                @endif

                <x-theme-toggle class="-mr-1.5" />
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 pb-16">
        @if (! $week)
            <p class="text-muted py-16 text-center">
                No schedule has been synced yet. Run <code class="text-fg">php artisan cfbd:sync</code>.
            </p>
        @else
            <div class="py-3 text-center">
                <p class="text-muted text-sm">{{ $week->dateRange() }}</p>

                <p class="text-faint mt-0.5 text-xs" aria-live="polite">{{ $board->summary() }}</p>
            </div>

            <form
                method="get"
                action="{{ $week->url() }}"
                class="border-line flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t py-2"
                wire:submit.prevent
            >
                <nav aria-label="Filter by status" class="-ml-2 flex">
                    @foreach ($filters as $value => $name)
                        <a
                            href="{{ $this->filterUrl($value) }}"
                            wire:click.prevent="$set('status', '{{ $value }}')"
                            @if ($status === $value || ($value === 'all' && ! array_key_exists($status, $filters))) aria-current="true" @endif
                            @class([
                                'rounded px-2 py-1.5 text-sm',
                                'font-semibold text-fg underline decoration-2 underline-offset-[6px]' => $status === $value,
                                'text-muted hover:text-fg' => $status !== $value,
                            ])
                        >{{ $name }}</a>
                    @endforeach
                </nav>

                @if ($status !== 'all')
                    <input type="hidden" name="status" value="{{ $status }}" />
                @endif

                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <label for="search" class="sr-only">Search teams</label>
                    <div class="relative min-w-0 flex-1 sm:w-44 sm:flex-none">
                        <input
                            id="search"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Search teams"
                            autocomplete="off"
                            wire:model.live.debounce.250ms="search"
                            class="border-line bg-control text-fg placeholder:text-faint w-full rounded border py-1.5 pr-8 pl-2 text-sm"
                        />

                        @if ($search !== '')
                            <a
                                href="{{ $this->clearSearchUrl() }}"
                                wire:click.prevent="$set('search', '')"
                                class="text-faint hover:text-fg absolute inset-y-0 right-0 flex w-8 items-center justify-center rounded"
                                aria-label="Clear search"
                            >
                                <svg
                                    class="size-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    aria-hidden="true"
                                >
                                    <path d="M18 6 6 18M6 6l12 12" />
                                </svg>
                            </a>
                        @endif
                    </div>

                    <label for="conference" class="sr-only">Conference</label>
                    <select
                        id="conference"
                        name="conference"
                        wire:model.live="conference"
                        class="border-line bg-control text-fg max-w-44 shrink-0 rounded border px-2 py-1.5 text-sm sm:max-w-52"
                    >
                        <option value="">All conferences</option>
                        @foreach ($board->conferences() as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <noscript
                        ><button type="submit" class="border-line rounded border px-2 py-1.5 text-sm">
                            Apply
                        </button></noscript>
                </div>
            </form>

            @if ($this->games->isEmpty())
                <p class="border-line text-muted border-t py-12 text-center text-sm">
                    {{ $board->games->isEmpty() ? 'No FBS games this week.' : 'No games match these filters.' }}
                </p>
            @else
                <div class="grid grid-cols-1 gap-x-8 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->games as $game)
                        <x-game :$game :$board />
                    @endforeach
                </div>
            @endif
        @endif
    </main>
</div>
