<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-white dark:bg-gray-900">
        
        @include('layouts.navigation')

        <div class="sm:pl-64 min-h-screen flex flex-col bg-white dark:bg-gray-900">
            
            <nav class="h-16 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 sticky top-0 z-30 px-4 sm:px-8 flex items-center justify-between">
                <div class="text-xs text-gray-400 font-medium hidden md:block">
                    System Time: {{ date('H:i') }}
                </div>

                <div class="flex items-center gap-4 ml-auto">
                    
                    <button type="button" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition duration-150 cursor-pointer" title="Toggle Theme">
                        <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364-6.364l-1.414 1.414M7.05 16.95l-1.414 1.414M18.364 18.364l-1.414-1.414M7.05 7.05L5.636 8.464M12 8a4 4 0 100 8 4 4 0 000-8z" />
                        </svg>
                        <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    </button>

                    <button type="button" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition duration-150 relative cursor-pointer" title="Notifications">
                        <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-red-600 ring-2 ring-white dark:ring-gray-900"></span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                    </button>

                    <div class="h-6 w-[1px] bg-gray-200 dark:bg-gray-800"></div>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2.5 focus:outline-none p-1.5 hover:bg-gray-50 dark:hover:bg-gray-800/60 rounded-xl transition duration-150 cursor-pointer">
                            @if(Auth::user()->avatar ?? false)
                                <img src="{{ Auth::user()->avatar }}" class="w-8 h-8 rounded-full border border-gray-200 dark:border-gray-700 object-cover shadow-sm" alt="Google Profile">
                            @else
                                <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white font-black text-xs uppercase shadow-sm">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                            @endif
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300 hidden sm:block truncate max-w-[120px]">{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-xl overflow-hidden py-1 z-50">
                            <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-xs text-gray-400 font-semibold uppercase">Account Profile</p>
                                <p class="text-xs font-bold text-gray-800 dark:text-gray-200 truncate mt-0.5">{{ Auth::user()->email }}</p>
                            </div>
                            
                            <a href="#settings" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                Settings
                            </a>
                            
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left block px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 cursor-pointer">
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </nav>

            @isset($header)
                <header class="bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-1 py-6 px-4 sm:px-6 lg:px-8">
                <div class="max-w-7xl mx-auto">
                    {{ $slot }}
                </div>
            </main>

        </div>
    </body>
</html>