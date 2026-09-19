@props(['game', 'board'])

@php
    use App\Enums\GameStatus;

    $live = $game->status === GameStatus::InProgress;
    $final = $game->status === GameStatus::Final;
    $showScore = $game->hasScore();

    $sides = collect([
        ['team' => $game->awayTeam, 'score' => $game->away_score, 'other' => $game->home_score],
        ['team' => $game->homeTeam, 'score' => $game->home_score, 'other' => $game->away_score],
    ])->map(fn ($side) => $side + [
        'record' => $board->record($side['team']->id),
        'rank' => $board->rank($side['team']->id),
        'lost' => $final && $showScore && $side['score'] < $side['other'],
    ]);

    $label = $game->awayTeam->name.($game->neutral_site ? ' vs. ' : ' at ').$game->homeTeam->name;
@endphp

<article
    {{ $attributes->class('border-t border-line py-2.5') }}
    aria-label="{{ $label }}"
    wire:key="game-{{ $game->id }}"
>
    <div class="text-muted flex items-baseline justify-between gap-3 text-[11px] font-semibold tracking-wide uppercase">
        <span @class(['flex items-center gap-1.5', 'text-live' => $live])>
            @if ($live)
                <span class="bg-live size-1.5 translate-y-[-1px] rounded-full" aria-hidden="true"></span>
                <span class="sr-only">Live:</span>
            @endif
            {{ $game->stateLabel() }}
        </span>
        <span class="truncate">{{ $game->networkLabel() }}</span>
    </div>

    <div class="mt-1 space-y-0.5">
        @foreach ($sides as $side)
            <div class="flex items-baseline gap-3">
                <p @class(['flex min-w-0 flex-1 items-baseline gap-1', 'text-muted' => $side['lost']])>
                    <span class="truncate font-medium">{{ $side['team']->name }}</span>
                    @if ($side['record'])
                        <span class="text-faint shrink-0 text-sm">({{ $side['record'] }})</span>
                    @endif
                    @if ($side['rank'])
                        <span class="text-rank shrink-0 text-sm font-semibold"><span class="sr-only">AP rank </span>#{{ $side['rank'] }}</span>
                    @endif
                </p>
                @if ($showScore)
                    <span @class(['w-8 shrink-0 text-right text-lg leading-6 font-bold tabular-nums', 'text-muted' => $side['lost']])>{{ $side['score'] }}</span>
                @endif
            </div>
        @endforeach
    </div>
</article>
