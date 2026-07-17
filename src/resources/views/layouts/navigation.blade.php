<aside
    id="app-sidebar"
    :class="sidebarOpen ? 'w-64 shadow-xl sm:shadow-none' : 'w-16 shadow-none'"
    class="fixed inset-y-0 left-0 z-40 flex flex-col overflow-hidden border-r border-gray-200 bg-white transition-[width,background-color] duration-200 ease-out dark:border-slate-800 dark:bg-slate-900"
>
    <div :class="sidebarOpen ? 'px-4 pb-3 pt-2' : 'px-2 pb-2 pt-3'" class="relative flex shrink-0 flex-col items-center">
        <a x-show="sidebarOpen" href="{{ route('dashboard') }}" class="flex w-full justify-center" title="ATHENA dashboard" aria-label="ATHENA dashboard">
            <img
                src="{{ asset('images/athenalogo-transparent.png') }}"
                alt="ATHENA logo"
                class="h-28 w-28 shrink-0 object-contain"
            />
        </a>

        <button
            x-show="sidebarOpen"
            type="button"
            @click="sidebarOpen = false"
            :aria-expanded="sidebarOpen"
            aria-controls="app-sidebar-navigation"
            class="absolute right-3 top-3 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-red-300"
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
            class="group inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-slate-800"
            aria-label="Expand navigation menu"
            title="Expand menu"
        >
            <img src="{{ asset('images/athenalogo-transparent.png') }}" alt="" class="h-11 w-11 object-contain group-hover:hidden" />
            <svg class="hidden h-5 w-5 text-red-600 group-hover:block dark:text-red-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <rect width="18" height="18" x="3" y="3" rx="2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v18m5-6 3-3-3-3" />
            </svg>
        </button>
    </div>

    <div
        id="app-sidebar-navigation"
        :class="sidebarOpen ? 'px-4' : 'px-2 [&>a]:justify-center [&>a]:px-0'"
        class="grow space-y-1 overflow-x-hidden overflow-y-auto py-3"
    >
        
        @if (Auth::user()->isUsingWorkspace('research_head'))
            <a href="{{ route('research_head.dashboard') }}" aria-label="Research Head Dashboard" title="Research Head Dashboard"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('research_head.dashboard') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Research Head Dashboard</span>
            </a>

            <a href="{{ route('research_head.faculty-directory.index') }}" aria-label="Faculty Directory" title="Faculty Directory"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('research_head.faculty-directory.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.68 0-5.22-.59-7.5-1.65Z" /></svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Faculty Directory</span>
            </a>

            <a href="{{ route('research_head.projects.index') }}" aria-label="Project Monitoring" title="Project Monitoring"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('research_head.projects.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5h4.5v6.75h-4.5V13.5Zm6-4.5h4.5v11.25h-4.5V9Zm6-5.25h4.5v16.5h-4.5V3.75Z" /></svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Project Monitoring</span>
            </a>

            <a href="{{ route('research_head.proposal-templates.index') }}" aria-label="Proposal Templates" title="Proposal Templates"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('research_head.proposal-templates.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Proposal Templates</span>
            </a>

            <a href="{{ route('research_head.assistant-knowledge.index') }}"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('research_head.assistant-knowledge.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a4.5 4.5 0 0 0-4.5 4.5c0 1.72.966 3.214 2.385 3.972V18h4.23v-2.778A4.502 4.502 0 0 0 12 6.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 21h4.5M12 3V1.5M4.575 5.325 3.45 4.2m16.1 0-1.125 1.125M4.5 12H3m18 0h-1.5" /></svg>
                Athena Knowledge
            </a>
            
        @endif

        @role('research_coordinator')
            @if (session('active_role') !== 'faculty')
            <a href="{{ route('research_coordinator.dashboard') }}" aria-label="Faculty Members" title="Faculty Members"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('research_coordinator.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.72m.94 3.2v-.01c0-1.2-.34-2.32-.94-3.19m.94 3.2v.13A11.9 11.9 0 0 1 12 20.4c-2.17 0-4.2-.58-5.94-1.6v-.12a6 6 0 0 1 11-3.17M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Faculty Members</span>
            </a>
            @endif
        @endrole

        @if (session('active_role') !== 'research_coordinator' && Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']))
            <a href="{{ route('faculty.dashboard') }}" aria-label="Faculty Dashboard" title="Faculty Dashboard"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('faculty.dashboard') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/>
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Faculty Dashboard</span>
            </a>

            <a href="{{ route('faculty.proposal-drafts.index') }}" aria-label="Proposal Workspace" title="Proposal Workspace"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('faculty.proposal-drafts.*', 'faculty.topics.create') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l-3 3m3-3l3 3M6.75 19.5h10.5A2.25 2.25 0 0019.5 17.25V6.75A2.25 2.25 0 0017.25 4.5H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Proposal Workspace</span>
            </a>

            <a href="{{ route('research-support.index') }}" aria-label="Research Help Facility" title="Research Help Facility"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('research-support.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.66 5.66a2.12 2.12 0 01-3-3l5.66-5.66m3-3l5.66-5.66a2.12 2.12 0 013 3l-5.66 5.66m-6 0l3 3m-1.5-7.5l3 3" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Research Help Facility</span>
            </a>
            
        @endif

        @if (Auth::user()->isUsingWorkspace('faculty_researcher'))
            <a href="{{ route('research.index') }}" aria-label="Research" title="Research"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('research.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Research</span>
            </a>

        @endif

        @if (Auth::user()->isUsingWorkspace('expert'))
            <a href="{{ route('expert.dashboard') }}" aria-label="Co-evaluations" title="Co-evaluations" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('expert.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-show="sidebarOpen" class="whitespace-nowrap">Co-evaluations</span>
            </a>
        @endif

        <a href="{{ route('research-calls.index') }}" aria-label="Research Calls" title="Research Calls" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold transition {{ request()->routeIs('research-calls.*') ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 18.75V7.5A2.25 2.25 0 016 5.25h12a2.25 2.25 0 012.25 2.25v11.25M3.75 18.75A2.25 2.25 0 006 21h12a2.25 2.25 0 002.25-2.25M3.75 18.75v-7.5h16.5v7.5"/></svg>
            <span x-show="sidebarOpen" class="whitespace-nowrap">Research Calls</span>
        </a>

    </div>

</aside>
