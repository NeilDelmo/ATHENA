<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ATHENA') }}</title>

        <script>
            (() => {
                const savedTheme = localStorage.getItem('athena-theme') || localStorage.getItem('athena-auth-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', savedTheme ? savedTheme === 'dark' : prefersDark);
                if (savedTheme) localStorage.setItem('athena-theme', savedTheme);
            })();
        </script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />
        @vite(['src/resources/css/app.css', 'src/resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        {{ $slot }}
    </body>
</html>
