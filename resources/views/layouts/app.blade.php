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
    <body class="font-sans antialiased bg-white">
        
        @include('layouts.navigation')

        <div class="sm:pl-64 min-h-screen flex flex-col bg-white">
            
            <nav class="h-16 border-b border-gray-200 bg-white sticky top-0 z-30 px-4 sm:px-8 flex items-center justify-between">
                <div class="hidden text-xs font-medium text-gray-400 md:block">
                    Philippine Time:
                    <time
                        id="manila-system-time"
                        class="font-bold tabular-nums text-gray-600"
                        data-timezone="Asia/Manila"
                        aria-live="off"
                    >{{ now()->format('M d, Y | h:i:s A') }}</time>
                    <span class="ml-1 text-[10px] font-black uppercase tracking-wider text-red-600">PHT</span>
                </div>

                <div class="flex items-center gap-4 ml-auto">

                    <button type="button" class="p-2 text-gray-500 hover:bg-gray-100 rounded-xl transition duration-150 relative cursor-pointer" title="Notifications">
                        <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-red-600 ring-2 ring-white"></span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                    </button>

                    <div class="h-6 w-[1px] bg-gray-200"></div>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2.5 focus:outline-none p-1.5 hover:bg-gray-50 rounded-xl transition duration-150 cursor-pointer">
                            @if(Auth::user()->avatar ?? false)
                                <img src="{{ Auth::user()->avatar }}" class="w-8 h-8 rounded-full border border-gray-200 object-cover shadow-sm" alt="Google Profile">
                            @else
                                <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white font-black text-xs uppercase shadow-sm">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                            @endif
                            <span class="text-sm font-bold text-gray-700 hidden sm:block truncate max-w-[120px]">{{ Auth::user()->name }}</span>
                            <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-2xl border border-gray-200 shadow-xl overflow-hidden py-1 z-50">
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-xs text-gray-400 font-semibold uppercase">Account Profile</p>
                                <p class="text-xs font-bold text-gray-800 truncate mt-0.5">{{ Auth::user()->email }}</p>
                            </div>
                            
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Account Profile
                            </a>
                            
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left block px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 cursor-pointer">
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </nav>

            @isset($header)
                <header class="bg-white border-b border-gray-100">
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

        <script>
            function initializeManilaClock() {
                const clock = document.getElementById('manila-system-time');
                if (!clock) return;

                const formatter = new Intl.DateTimeFormat('en-PH', {
                    timeZone: 'Asia/Manila',
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                });

                const updateClock = () => {
                    const now = new Date();
                    clock.textContent = formatter.format(now).replace(',', ' |');
                    clock.dateTime = now.toISOString();
                };

                updateClock();
                if (window.manilaClockInterval) clearInterval(window.manilaClockInterval);
                window.manilaClockInterval = setInterval(updateClock, 1000);
            }

            initializeManilaClock();
            document.addEventListener('livewire:navigated', initializeManilaClock);
        </script>
    </body>
</html>
