<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="app-url" content="{{ url('/') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.theme-script')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        data-app-shell
        data-auth-user-id="{{ Auth::id() }}"
        @hasanyrole('faculty|faculty_researcher') data-research-assistant-url="{{ route('research-support.chat') }}" @endhasanyrole
        class="bg-white font-sans text-gray-900 antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100"
    >
        
        @include('layouts.navigation')

        <div class="flex min-h-screen flex-col bg-white transition-colors duration-300 dark:bg-slate-950 sm:pl-64">
            
            <nav class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 transition-colors duration-300 dark:border-slate-800 dark:bg-slate-900 sm:px-8">
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

                    @hasanyrole('faculty|faculty_researcher')
                        <button
                            type="button"
                            @click="$store.researchAssistant.openDrawer()"
                            class="group inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 px-3 text-xs font-black text-white shadow-sm transition hover:from-red-700 hover:to-rose-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                            aria-label="Open Athena AI research assistant"
                            title="Open Athena AI research assistant"
                        >
                            <svg class="h-4 w-4 transition group-hover:rotate-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1ZM5.2 13.2 6 11l.8 2.2L9 14l-2.2.8L6 17l-.8-2.2L3 14l2.2-.8Z" />
                            </svg>
                            <span class="hidden sm:inline">Ask Athena</span>
                        </button>
                    @endhasanyrole

                    <button id="app-theme-toggle" data-theme-toggle type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Toggle light and dark theme" title="Toggle theme">
                        <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9 9 0 118.25 2.25a7.5 7.5 0 0013.5 13.5z" />
                        </svg>
                        <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364l-1.061-1.061M6.697 6.697L5.636 5.636m12.728 0l-1.061 1.061M6.697 17.303l-1.061 1.061M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                        </svg>
                    </button>

                    <x-notification-menu />

                    <div class="h-6 w-[1px] bg-gray-200 dark:bg-slate-700"></div>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex cursor-pointer items-center gap-2.5 rounded-xl p-1.5 transition duration-150 hover:bg-gray-50 focus:outline-none dark:hover:bg-slate-800">
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

                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 z-50 mt-2 w-48 overflow-hidden rounded-2xl border border-gray-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div class="border-b border-gray-100 px-4 py-2 dark:border-slate-800">
                                <p class="text-xs text-gray-400 font-semibold uppercase">Account Profile</p>
                                <p class="text-xs font-bold text-gray-800 truncate mt-0.5">{{ Auth::user()->email }}</p>
                            </div>
                            
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                Account Profile
                            </a>
                            
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full cursor-pointer px-4 py-2.5 text-left text-sm font-bold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </nav>

            @isset($header)
                <header class="border-b border-gray-100 bg-white transition-colors duration-300 dark:border-slate-800 dark:bg-slate-900">
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

        @hasanyrole('faculty|faculty_researcher')
            <x-research-assistant-drawer />
        @endhasanyrole

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
