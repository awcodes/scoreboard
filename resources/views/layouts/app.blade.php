<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#0b0d10" media="(prefers-color-scheme: dark)" data-theme-color="dark" />
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)" data-theme-color="light" />
    <title>{{ $title ?? 'Scoreboard' }}</title>

    {{--
        Applies a saved light/dark choice before first paint. With no saved
        choice the CSS follows the system preference. Inline (not bundled)
        so it runs before the page renders, avoiding a flash of the wrong theme.
    --}}
    <script>
        (() => {
            const colors = { dark: '#0b0d10', light: '#ffffff' };

            const stored = () => {
                try {
                    const theme = localStorage.getItem('theme');
                    return theme === 'light' || theme === 'dark' ? theme : 'system';
                } catch {
                    return 'system';
                }
            };

            const apply = () => {
                const theme = stored();

                // "system" is set too: CSS only overrides colors for light/dark,
                // and the toggle's icon keys off the attribute being present.
                document.documentElement.dataset.theme = theme;

                // Keep the browser chrome (e.g. iOS status bar) in step.
                document.querySelectorAll('meta[data-theme-color]').forEach((meta) => {
                    meta.content = colors[theme === 'system' ? meta.dataset.themeColor : theme];
                });
            };

            apply();

            // wire:navigate resets <html> attributes to the server's; reapply
            // during the swap so there is no flash between weeks.
            document.addEventListener('livewire:navigating', (event) => event.detail.onSwap(apply));

            const next = { system: 'light', light: 'dark', dark: 'system' };

            document.addEventListener('click', (event) => {
                if (!event.target.closest('[data-theme-toggle]')) {
                    return;
                }

                const theme = next[document.documentElement.dataset.theme] ?? 'light';

                try {
                    if (theme === 'system') {
                        localStorage.removeItem('theme');
                    } else {
                        localStorage.setItem('theme', theme);
                    }
                } catch {
                    // Storage unavailable (e.g. private mode): apply for this page only.
                    document.documentElement.dataset.theme = theme;
                    return;
                }

                apply();
            });
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    @if (app()->isProduction())
        <!-- Fathom - beautiful, simple website analytics -->
        <script src="https://cdn.usefathom.com/script.js" data-site="NXXPZQWR" data-spa="auto" defer></script>
        <!-- / Fathom -->
    @endif
</head>
<body class="min-h-screen font-sans">
    {{ $slot }}

    @livewireScripts
</body>
</html>
