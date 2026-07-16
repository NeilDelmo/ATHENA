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
        x-data="{ sidebarOpen: window.innerWidth >= 640 }"
        @keydown.escape.window="sidebarOpen = false"
        @resize.window="$store.researchAssistant.syncPageScroll()"
        data-app-shell
        data-auth-user-id="{{ Auth::id() }}"
        @auth data-research-assistant-url="{{ route('research-support.chat') }}" @endauth
        @if (Auth::user()?->isUsingWorkspace(['faculty', 'faculty_researcher'])) data-literature-search-url="{{ route('research-support.literature-search') }}" @endif
        @if (Auth::user()?->isUsingWorkspace(['faculty', 'faculty_researcher'])) data-conference-search-url="{{ route('research-support.conference-search') }}" @endif
        class="bg-white font-sans text-gray-900 antialiased transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100"
    >
        @auth
            <script>
                window.athenaResearchAssistantContexts = {{ Illuminate\Support\Js::from($researchAssistantContexts ?? collect()) }};
                window.athenaResearchAssistantActiveContextId = {{ Illuminate\Support\Js::from($activeResearchAssistantContextId ?? null) }};
            </script>
        @endauth

        @include('layouts.navigation')

        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-slate-950/45 backdrop-blur-[1px] sm:hidden"
            aria-hidden="true"
        ></div>

        <div
            :class="{ 'sm:pl-64': sidebarOpen, 'xl:pr-[28rem]': $store.researchAssistant.drawerOpen }"
            class="flex min-h-screen flex-col bg-white pl-16 transition-[padding,background-color] duration-200 ease-out dark:bg-slate-950"
        >
            
            <nav class="sticky top-0 z-30 flex h-[120px] items-end justify-between border-b border-red-200/60 bg-white px-4 pb-3 shadow-sm transition-colors duration-300 dark:border-red-950 dark:bg-slate-900 sm:px-8">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-red-700 via-red-700 to-red-950 md:from-transparent md:via-red-700/70 md:to-red-950" aria-hidden="true"></div>

                <div
                    class="pointer-events-none absolute inset-y-0 left-0 hidden w-[30rem] overflow-hidden md:block"
                    style="-webkit-mask-image: linear-gradient(to right, #000 0%, #000 72%, transparent 100%); mask-image: linear-gradient(to right, #000 0%, #000 72%, transparent 100%);"
                    aria-hidden="true"
                >
                    <img src="{{ asset('images/front.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover object-center" />
                    <div class="absolute inset-0 bg-gradient-to-r from-red-950/65 via-red-700/25 to-red-600/60"></div>
                    <div class="absolute -bottom-14 right-20 h-32 w-32 rotate-45 rounded-3xl border border-white/20 bg-white/10"></div>
                    <div class="absolute -top-16 left-24 h-36 w-36 rounded-full border-[18px] border-white/10"></div>
                </div>

                <svg class="pointer-events-none absolute inset-y-0 right-0 h-full w-[55%] text-white/[0.11]" aria-hidden="true">
                    <defs>
                        <pattern id="athena-header-hexagons" width="52" height="45" patternUnits="userSpaceOnUse">
                            <path d="M13 1h26l12 21.5L39 44H13L1 22.5 13 1Z" fill="none" stroke="currentColor" stroke-width="1" />
                        </pattern>
                        <linearGradient id="athena-header-pattern-fade" x1="0" x2="1">
                            <stop offset="0" stop-color="white" stop-opacity="0" />
                            <stop offset="0.38" stop-color="white" stop-opacity="0.55" />
                            <stop offset="1" stop-color="white" stop-opacity="1" />
                        </linearGradient>
                        <mask id="athena-header-pattern-mask">
                            <rect width="100%" height="100%" fill="url(#athena-header-pattern-fade)" />
                        </mask>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#athena-header-hexagons)" mask="url(#athena-header-pattern-mask)" />
                </svg>

                <div class="absolute right-8 top-3 z-10 hidden min-w-0 items-center gap-3 md:flex">
                    <div class="rounded-xl border border-white/15 bg-red-950/25 px-4 py-2 text-xs font-medium text-red-100 shadow-sm backdrop-blur-sm">
                        Philippine Time:
                        <time
                            id="manila-system-time"
                            class="font-bold tabular-nums text-white"
                            data-timezone="Asia/Manila"
                            aria-live="off"
                        >{{ now()->format('M d, Y | h:i:s A') }}</time>
                        <span class="ml-1 text-[10px] font-black uppercase tracking-wider text-red-200">PHT</span>
                    </div>
                </div>

                <div class="athena-header-actions relative z-20 ml-auto flex items-center gap-4">

                    @auth
                        <button
                            type="button"
                            @click="$store.researchAssistant.toggleDrawer($event.currentTarget)"
                            :aria-expanded="$store.researchAssistant.drawerOpen"
                            aria-controls="research-assistant-panel"
                            class="group inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 px-3 text-[13px] font-black text-white shadow-sm transition hover:from-red-700 hover:to-rose-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                            aria-label="Open Athena AI research assistant"
                            title="Open Athena AI research assistant"
                        >
                            <svg class="h-4 w-4 transition group-hover:rotate-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1ZM5.2 13.2 6 11l.8 2.2L9 14l-2.2.8L6 17l-.8-2.2L3 14l2.2-.8Z" />
                            </svg>
                            <span class="hidden sm:inline">Ask ATHENA</span>
                        </button>
                    @endauth

                    <button id="app-theme-toggle" data-theme-toggle type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Toggle light and dark theme" title="Toggle theme">
                        <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9 9 0 118.25 2.25a7.5 7.5 0 0013.5 13.5z" />
                        </svg>
                        <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364l-1.061-1.061M6.697 6.697L5.636 5.636m12.728 0l-1.061 1.061M6.697 17.303l-1.061 1.061M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                        </svg>
                    </button>

                    <x-notification-menu />

                    <div class="h-6 w-px bg-white/25"></div>

                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex cursor-pointer items-center gap-2.5 rounded-xl p-1.5 transition duration-150 hover:bg-white/10 focus:outline-none">
                            @if(Auth::user()->avatar ?? false)
                                <img src="{{ Auth::user()->avatar }}" class="w-8 h-8 rounded-full border border-gray-200 object-cover shadow-sm" alt="Google Profile">
                            @else
                                <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white font-black text-xs uppercase shadow-sm">
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                </div>
                            @endif
                            <span class="hidden max-w-[120px] truncate text-sm font-bold text-white sm:block">{{ Auth::user()->name }}</span>
                            <svg class="hidden h-4 w-4 text-red-100 sm:block" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-gray-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div class="border-b border-gray-100 px-4 py-2 dark:border-slate-800">
                                <p class="text-xs text-gray-400 font-semibold uppercase">Account Profile</p>
                                <p class="text-xs font-bold text-gray-800 truncate mt-0.5">{{ Auth::user()->email }}</p>
                                <p class="mt-1 text-[11px] font-bold text-red-600 dark:text-red-300">Using {{ Auth::user()->activeWorkspaceLabel() }}</p>
                            </div>
                            
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                Account Profile
                            </a>

                            @if (Auth::user()->hasMultipleWorkspaces())
                                <a href="{{ route('workspace.select') }}" class="block px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:text-slate-200 dark:hover:bg-slate-800">
                                    Switch Workspace
                                </a>
                            @endif
                            
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

        @auth
            <x-research-assistant-drawer />
        @endauth

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
