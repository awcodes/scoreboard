{{--
    Cycles System → Light → Dark. The layout script sets <html data-theme>
    to the current choice, and the icon and label shown here follow that
    attribute via CSS, so Livewire re-renders never reset the button.
    Hidden until the script has run, since it needs JavaScript.
--}}
@php
    // Full class names (not interpolated) so Tailwind can find them.
    $states = [
        'system' => ['label' => 'Theme: system', 'next' => 'light', 'class' => 'in-data-[theme=system]:inline-flex'],
        'light' => ['label' => 'Theme: light', 'next' => 'dark', 'class' => 'in-data-[theme=light]:inline-flex'],
        'dark' => ['label' => 'Theme: dark', 'next' => 'system', 'class' => 'in-data-[theme=dark]:inline-flex'],
    ];
@endphp

<button
    type="button"
    data-theme-toggle
    {{ $attributes->class('text-muted hover:text-fg hidden size-8 items-center justify-center rounded in-data-theme:inline-flex') }}
>
    @foreach ($states as $state => $meta)
        <span class="hidden {{ $meta['class'] }}" title="{{ $meta['label'] }} (click for {{ $meta['next'] }})">
            <span class="sr-only">{{ $meta['label'] }}. Switch to {{ $meta['next'] }}.</span>
            <svg
                class="size-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                @switch ($state)
                    @case ('system')
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <path d="M8 21h8M12 17v4" />
                        @break
                    @case ('light')
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                        @break
                    @case ('dark')
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                        @break

                @endswitch
            </svg>
        </span>
    @endforeach
</button>
