<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "dark" }}';
                const storedAppearance = window.localStorage.getItem('appearance');
                const firstGuindaVisit = window.localStorage.getItem('appearance-palette-v2') !== '1'
                    && (storedAppearance === null || storedAppearance === 'light');

                if (firstGuindaVisit) {
                    document.documentElement.classList.add('dark');
                } else if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f6f1f2;
            }

            html.dark {
                background-color: #3b0a12;
            }
        </style>

        <meta name="theme-color" content="#3B0A12">
        <link rel="icon" href="/brand/dsi-seoane.jpg" type="image/jpeg">
        <link rel="apple-touch-icon" href="/brand/dsi-seoane.jpg">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
