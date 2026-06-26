<aside class="fixed inset-y-0 left-0 w-64 bg-white border-r border-gray-200 flex flex-col justify-between z-40">
    <div>
        <div class="h-16 flex items-center gap-2 px-6 border-b border-gray-100">
            <a href="{{ route('dashboard') }}" class="text-xl font-black tracking-widest text-red-600">
                ATHENA
            </a>
            <span class="text-[10px] px-1.5 py-0.5 bg-red-500/10 text-red-600 rounded font-bold uppercase tracking-wider">Portal</span>
        </div>

        <div class="px-4 py-6 space-y-1">
            <a href="{{ route('dashboard') }}" 
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition duration-150 ease-in-out {{ request()->routeIs('dashboard') ? 'bg-red-50 text-red-600' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/>
                </svg>
                Dashboard
            </a>
            
            </div>
    </div>

    <div class="p-4 border-t border-gray-100 bg-gray-50/50">
        <div class="flex items-center gap-2 px-2 mb-3">
            <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></div>
            <div class="text-xs font-bold text-gray-700 truncate max-w-[180px]">{{ Auth::user()->name }}</div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-bold text-red-600 bg-white border border-red-200 hover:bg-red-50 rounded-xl cursor-pointer transition duration-150 ease-in-out">
                Log Out
            </button>
        </form>
    </div>
</aside>