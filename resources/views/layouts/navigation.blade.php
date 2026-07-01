<aside class="fixed inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col z-40">
    <div class="h-16 flex items-center gap-2 px-6 border-b border-gray-100">
        <a href="{{ route('dashboard') }}" class="text-xl font-black tracking-widest text-red-600">
            ATHENA
        </a>
        <span class="text-[10px] px-1.5 py-0.5 bg-red-500/10 text-red-600 rounded font-bold uppercase tracking-wider">Portal</span>
    </div>

    <div class="px-4 py-6 space-y-1 overflow-y-auto grow">
        
        @role('research_head')
            <a href="{{ route('research_head.dashboard') }}" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('research_head.dashboard') ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/>
                </svg>
                Research Head Dashboard
            </a>
            
            @endrole

        @hasanyrole('faculty|faculty_researcher')
            <a href="{{ route('faculty.dashboard') }}" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('faculty.dashboard') ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/>
                </svg>
                Faculty Dashboard
            </a>
            
            @endhasanyrole

        @role('faculty_researcher')
            <a href="{{ route('research.index') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('research.*') ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                Research
            </a>
        @endrole

    </div>
</aside>
