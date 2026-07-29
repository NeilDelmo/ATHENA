<aside
    id="app-sidebar"
    :class="sidebarOpen ? 'w-[280px] shadow-2xl sm:shadow-xl' : 'w-[76px] shadow-lg'"
    class="fixed inset-y-0 left-0 z-40 flex flex-col overflow-hidden rounded-r-[28px] border-r border-slate-200/80 bg-white/95 shadow-slate-900/10 backdrop-blur-xl transition-[width,background-color] duration-300 ease-out dark:border-slate-800/80 dark:bg-slate-950/95"
>
    <svg
        class="pointer-events-none absolute inset-0 h-full w-full text-[#7A0019]/[0.035] dark:text-white/[0.025]"
        aria-hidden="true"
    >
        <defs>
            <pattern
                id="athena-sidebar-hexagons"
                width="52"
                height="45"
                patternUnits="userSpaceOnUse"
            >
                <path
                    d="M13 1h26l12 21.5L39 44H13L1 22.5 13 1Z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.15"
                />
            </pattern>

            <linearGradient
                id="athena-sidebar-pattern-fade"
                x1="0"
                y1="0"
                x2="1"
                y2="0"
            >
                <stop offset="0" stop-color="white" stop-opacity="0.18" />
                <stop offset="0.38" stop-color="white" stop-opacity="0.72" />
                <stop offset="1" stop-color="white" stop-opacity="1" />
            </linearGradient>

            <mask id="athena-sidebar-pattern-mask">
                <rect
                    width="100%"
                    height="100%"
                    fill="url(#athena-sidebar-pattern-fade)"
                />
            </mask>
        </defs>

        <rect
            width="100%"
            height="100%"
            fill="url(#athena-sidebar-hexagons)"
            mask="url(#athena-sidebar-pattern-mask)"
        />
    </svg>

    <div
        class="pointer-events-none absolute inset-y-0 right-0 z-[1] w-px bg-gradient-to-b
               from-transparent via-[#7A0019]/15 to-transparent dark:via-white/10"
    ></div>

    <div
        :class="sidebarOpen ? 'px-5 pb-5 pt-5' : 'px-3 pb-4 pt-4'"
        class="relative z-10 flex shrink-0 flex-col border-b border-slate-200/70 dark:border-slate-800/80"
    >
        <a
            x-show="sidebarOpen"
            href="{{ route('dashboard') }}"
            class="flex min-w-0 items-center gap-3 rounded-2xl px-1"
            title="ATHENA dashboard"
            aria-label="ATHENA dashboard"
        >
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#7A0019]/8 ring-1 ring-[#7A0019]/10 dark:bg-white/5 dark:ring-white/10">
                <img
                    src="{{ asset('images/athenalogo-transparent.png') }}"
                    alt="ATHENA logo"
                    class="h-10 w-10 object-contain"
                />
            </span>

            <span class="min-w-0">
                <span class="block truncate text-lg font-extrabold tracking-[0.12em] text-[#7A0019] dark:text-white">
                    ATHENA
                </span>
                <span class="block truncate text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Research Management
                </span>
            </span>
        </a>

        <button
            x-show="sidebarOpen"
            type="button"
            @click="sidebarOpen = false"
            :aria-expanded="sidebarOpen"
            aria-controls="app-sidebar-navigation"
            class="absolute right-3 top-6 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                   text-slate-400 transition hover:bg-slate-100 hover:text-[#7A0019]
                   focus:outline-none focus:ring-2 focus:ring-[#7A0019]/20
                   dark:text-slate-500 dark:hover:bg-slate-900 dark:hover:text-white"
            aria-label="Collapse navigation menu"
            title="Collapse menu"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <rect width="18" height="18" x="3" y="3" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v18m7-6-3-3 3-3" />
            </svg>
        </button>

        <button
            x-show="!sidebarOpen"
            type="button"
            @click="sidebarOpen = true"
            :aria-expanded="sidebarOpen"
            aria-controls="app-sidebar-navigation"
            class="group inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl
                   bg-[#7A0019]/8 ring-1 ring-[#7A0019]/10 transition
                   hover:bg-[#7A0019]/12 focus:outline-none focus:ring-2 focus:ring-[#7A0019]/20
                   dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10"
            aria-label="Expand navigation menu"
            title="Expand menu"
        >
            <img
                src="{{ asset('images/athenalogo-transparent.png') }}"
                alt=""
                class="h-11 w-11 object-contain group-hover:hidden"
            />

            <svg
                class="hidden h-5 w-5 text-[#7A0019] group-hover:block dark:text-[#E7A5B2]"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <rect width="18" height="18" x="3" y="3" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v18m5-6 3-3-3-3" />
            </svg>
        </button>
    </div>

    <div
        id="app-sidebar-navigation"
        :class="sidebarOpen
            ? 'px-4'
            : 'px-3 [&>a]:justify-center [&>a]:gap-0 [&>a]:px-0 [&>div>button]:justify-center [&>div>button]:gap-0 [&>div>button]:px-0'"
        class="relative z-10 grow space-y-1 overflow-x-hidden overflow-y-auto py-4
               scrollbar-thin scrollbar-track-transparent scrollbar-thumb-slate-300
               dark:scrollbar-thumb-slate-700"
    >
        @if (Auth::user()->isUsingWorkspace('research_head'))
            <a
                href="{{ route('research_head.dashboard') }}"
                aria-label="Research Head Dashboard"
                title="Research Head Dashboard"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('research_head.dashboard')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Research Head Dashboard</span>
            </a>

            <a
                href="{{ route('research_head.faculty-directory.index') }}"
                aria-label="Faculty Directory"
                title="Faculty Directory"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('research_head.faculty-directory.*')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.68 0-5.22-.59-7.5-1.65Z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Faculty Directory</span>
            </a>

            <a
                href="{{ route('research_head.proposal-submissions.index') }}"
                aria-label="Proposal Submissions"
                title="Proposal Submissions"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('research_head.proposal-submissions.*')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4.5 2.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Proposal Submissions</span>
            </a>

            <a
                href="{{ route('research_head.projects.index') }}"
                aria-label="Project Monitoring"
                title="Project Monitoring"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('research_head.projects.*')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5h4.5v6.75h-4.5V13.5Zm6-4.5h4.5v11.25h-4.5V9Zm6-5.25h4.5v16.5h-4.5V3.75Z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Project Monitoring</span>
            </a>

            <a
                href="{{ route('research_head.proposal-templates.index') }}"
                aria-label="Proposal Templates"
                title="Proposal Templates"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('research_head.proposal-templates.*')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Proposal Templates</span>
            </a>

            <a
                href="{{ route('research_head.assistant-knowledge.index') }}"
                aria-label="Athena Knowledge"
                title="Athena Knowledge"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('research_head.assistant-knowledge.*')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a4.5 4.5 0 0 0-4.5 4.5c0 1.72.966 3.214 2.385 3.972V18h4.23v-2.778A4.502 4.502 0 0 0 12 6.75Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 21h4.5M12 3V1.5M4.575 5.325 3.45 4.2m16.1 0-1.125 1.125M4.5 12H3m18 0h-1.5" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Athena Knowledge</span>
            </a>
        @endif


        @role('research_coordinator')
            @if (session('active_role') !== 'faculty')
                <a
                    href="{{ route('research_coordinator.dashboard') }}"
                    aria-label="Dashboard"
                    title="Dashboard"
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                           transition-all duration-200 ease-out hover:translate-x-0.5
                           {{ request()->routeIs('research_coordinator.dashboard')
                                ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                                : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                    </svg>
                    <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
                </a>

                <a
                    href="{{ route('research_coordinator.members.index') }}"
                    aria-label="Faculty Members"
                    title="Faculty Members"
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                           transition-all duration-200 ease-out hover:translate-x-0.5
                           {{ request()->routeIs('research_coordinator.members.*')
                                ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                                : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.72m.94 3.2v-.01c0-1.2-.34-2.32-.94-3.19m.94 3.2v.13A11.9 11.9 0 0 1 12 20.4c-2.17 0-4.2-.58-5.94-1.6v-.12a6 6 0 0 1 11-3.17M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                    <span x-show="sidebarOpen" class="whitespace-nowrap">Faculty Members</span>
                </a>
            @endif
        @endrole

        @if (session('active_role') !== 'research_coordinator' && Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']))
            <a
                href="{{ route('faculty.dashboard') }}"
                aria-label="Faculty Dashboard"
                title="Faculty Dashboard"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('faculty.dashboard')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Faculty Dashboard</span>
            </a>

            <a
                href="{{ route('faculty.proposal-drafts.index') }}"
                aria-label="Proposal Workspace"
                title="Proposal Workspace"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('faculty.proposal-drafts.*', 'faculty.topics.create')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l-3 3m3-3l3 3M6.75 19.5h10.5A2.25 2.25 0 0019.5 17.25V6.75A2.25 2.25 0 0017.25 4.5H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Proposal Workspace</span>
            </a>

            <div
                x-data="{
                    researchHelpOpen: @js(request()->routeIs('research-support.*')),
                    activeResearchHelpSection: window.location.hash || '#rrl-finder',
                }"
                @hashchange.window="activeResearchHelpSection = window.location.hash || '#rrl-finder'"
                data-research-help-menu
            >
                <button
                    type="button"
                    @click="
                        if (!sidebarOpen) {
                            sidebarOpen = true;
                            researchHelpOpen = true;
                        } else {
                            researchHelpOpen = !researchHelpOpen;
                        }
                    "
                    :aria-expanded="researchHelpOpen"
                    aria-controls="research-help-feature-links"
                    aria-label="Research Help Facility"
                    title="Research Help Facility"
                    :class="sidebarOpen ? 'px-4' : 'justify-center gap-0 px-0'"
                    class="flex w-full items-center gap-3 rounded-2xl py-3 text-[13px] font-semibold
                           transition-all duration-200 ease-out hover:translate-x-0.5
                           {{ request()->routeIs('research-support.*')
                                ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                                : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.66 5.66a2.12 2.12 0 01-3-3l5.66-5.66m3-3l5.66-5.66a2.12 2.12 0 013 3l-5.66 5.66m-6 0l3 3m-1.5-7.5l3 3" />
                    </svg>

                    <span
                        x-show="sidebarOpen"
                        class="min-w-0 flex-1 whitespace-nowrap text-left"
                    >
                        Research Help Facility
                    </span>

                    <svg
                        x-show="sidebarOpen"
                        :class="researchHelpOpen ? 'rotate-180' : ''"
                        class="h-4 w-4 shrink-0 transition-transform duration-200"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                <div
                    id="research-help-feature-links"
                    x-cloak
                    x-show="sidebarOpen && researchHelpOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="-translate-y-1 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-1 opacity-0"
                    class="relative mt-1 space-y-1 pl-8 before:absolute before:bottom-2 before:left-[18px]
                           before:top-2 before:w-px before:bg-slate-200 dark:before:bg-slate-800"
                >
                    <a
                        href="{{ route('research-support.index') }}#rrl-finder"
                        @click="
                            activeResearchHelpSection = '#rrl-finder';
                            if (window.innerWidth < 640) sidebarOpen = false;
                        "
                        :class="activeResearchHelpSection === '#rrl-finder'
                            ? 'bg-[#7A0019]/8 text-[#7A0019] ring-1 ring-[#7A0019]/10 dark:bg-white/5 dark:text-white dark:ring-white/10'
                            : 'text-slate-500 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-500 dark:hover:bg-slate-900 dark:hover:text-white'"
                        class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>
                        <span>RRL Finder</span>
                    </a>

                    <a
                        href="{{ route('research-support.index') }}#conference-finder"
                        @click="
                            activeResearchHelpSection = '#conference-finder';
                            if (window.innerWidth < 640) sidebarOpen = false;
                        "
                        :class="activeResearchHelpSection === '#conference-finder'
                            ? 'bg-[#7A0019]/8 text-[#7A0019] ring-1 ring-[#7A0019]/10 dark:bg-white/5 dark:text-white dark:ring-white/10'
                            : 'text-slate-500 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-500 dark:hover:bg-slate-900 dark:hover:text-white'"
                        class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>
                        <span>Conference Finder</span>
                    </a>
                </div>
            </div>
        @endif

        @if (Auth::user()->isUsingWorkspace('faculty_researcher'))
            <a
                href="{{ route('research.index') }}"
                aria-label="Research"
                title="Research"
                class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                       transition-all duration-200 ease-out hover:translate-x-0.5
                       {{ request()->routeIs('research.*')
                            ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                            : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Research</span>
            </a>
        @endif

        <a
            href="{{ route('research-calls.index') }}"
            aria-label="Research Calls"
            title="Research Calls"
            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-[13px] font-semibold
                   transition-all duration-200 ease-out hover:translate-x-0.5
                   {{ request()->routeIs('research-calls.*')
                        ? 'relative bg-white text-[#7A0019] shadow-[0_10px_30px_rgba(15,23,42,0.08)] ring-1 ring-slate-200 before:absolute before:left-0 before:top-1/2 before:h-7 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-[#7A0019] dark:bg-slate-900 dark:text-white dark:ring-slate-800 dark:shadow-[0_12px_30px_rgba(0,0,0,0.28)]'
                        : 'text-slate-600 hover:bg-slate-100/90 hover:text-[#7A0019] dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white' }}"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 18.75V7.5A2.25 2.25 0 016 5.25h12a2.25 2.25 0 012.25 2.25v11.25M3.75 18.75A2.25 2.25 0 006 21h12a2.25 2.25 0 002.25-2.25M3.75 18.75v-7.5h16.5v7.5" />
            </svg>
            <span x-show="sidebarOpen" class="whitespace-nowrap">Research Calls</span>
        </a>
    </div>
</aside>
