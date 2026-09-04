<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-url" content="{{ url('/') }}">
        <title>Revision editor · {{ config('app.name') }}</title>
        @include('partials.theme-script')
        <x-app-fonts />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="revision-embedded bg-white font-sans text-gray-900 antialiased dark:bg-slate-900 dark:text-white" data-revision-embedded data-auth-user-id="{{ Auth::id() }}">
        <main>{{ $slot }}</main>
        @livewireScriptConfig
    </body>
</html>
