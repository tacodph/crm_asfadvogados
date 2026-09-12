<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'light') === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Tailwick light-first: resolve theme before paint --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? 'light' }}';
                const stored = localStorage.getItem('appearance') || appearance;
                let dark = stored === 'dark';

                if (stored === 'system') {
                    dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }

                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <style>
            html {
                background-color: #f1f5f9;
            }

            html.dark {
                background-color: #0f1824;
            }
        </style>

        <link rel="icon" href="/images/logos/logo_circular_fundo_transparente.png" type="image/png" sizes="any">
        <link rel="apple-touch-icon" href="/images/logos/logo_circulas_fundo_branco.jpeg">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
