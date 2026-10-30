<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-favicon />
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ATHENA') }}</title>

        @include('partials.theme-script')

        <x-app-fonts />
        @vite(['resources/css/app.css', Auth::check() ? 'resources/js/app.js' : 'resources/js/guest.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased" data-auth-user-id="{{ Auth::id() }}" data-session-check-url="{{ route('dashboard') }}">
        {{ $slot }}

        @livewireScriptConfig
    </body>
</html>
