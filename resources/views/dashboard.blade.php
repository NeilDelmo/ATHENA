<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-black text-2xl text-gray-900 dark:text-white tracking-tight">
                    Research Workspace
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Welcome back, <span class="font-semibold text-red-600 dark:text-red-400">{{ Auth::user()->name }}</span>. Manage and track institutional submittals.
                </p>
            </div>
            
            <div>
                <button class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition duration-150 shadow-sm shadow-red-600/10 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Submit Proposal
                </button>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        <div x-data="{ 
        activeSlide: 0, 
        slides: [
            { img: 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1200&auto=format&fit=crop', title: 'Call for Proposals Open', sub: 'Submit your institutional research projects for Fiscal Year 2026.' },
            { img: 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=1200&auto=format&fit=crop', title: 'Research Ethics Review', sub: 'Ensure compliance with the newly updated BatStateU IRB guidelines.' },
            { img: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1200&auto=format&fit=crop', title: 'Faculty Writing Workshop', sub: 'Join our technical writing mentorship camp this coming month.' }
        ],
        init() {
            setInterval(() => {
                this.activeSlide = (this.activeSlide + 1) % this.slides.length;
            }, 6000); // Switches images automatically every 6 seconds
        }
     }" 
     class="relative w-full h-56 sm:h-64 rounded-2xl overflow-hidden shadow-sm border border-gray-200/60 bg-gray-900 group">
    
    <div class="relative w-full h-full">
        <template x-for="(slide, index) in slides" :key="index">
            <div x-show="activeSlide === index" 
                 x-transition:enter="transition ease-out duration-700"
                 x-transition:enter-start="opacity-0 scale-105"
                 x-transition:enter-end="opacity-100 scale-100"
                 class="absolute inset-0 w-full h-full">
                
                <img :src="slide.img" class="w-full h-full object-cover object-center opacity-40 group-hover:scale-102 transition duration-700" alt="Banner Image">
                
                <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-900/40 to-transparent"></div>
                
                <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 text-white z-10">
                    <span class="text-[10px] bg-red-600 text-white px-2 py-0.5 rounded font-black tracking-widest uppercase mb-2 inline-block">Institutional Notice</span>
                    <h3 class="text-lg sm:text-xl font-black tracking-tight text-white" x-text="slide.title"></h3>
                    <p class="text-xs text-gray-300 mt-1 max-w-xl" x-text="slide.sub"></p>
                </div>
            </div>
        </template>
    </div>

    <div class="absolute bottom-4 right-6 flex items-center gap-1.5 z-20">
        <template x-for="(slide, index) in slides" :key="index">
            <button @click="activeSlide = index" 
                    :class="activeSlide === index ? 'w-5 bg-red-600' : 'w-1.5 bg-white/40 hover:bg-white/70'" 
                    class="h-1.5 rounded-full transition-all duration-300 cursor-pointer focus:outline-none"></button>
        </template>
    </div>
</div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Active Proposals</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white block mt-1">0</span>
                </div>
                <div class="p-3 bg-red-50 dark:bg-red-950/30 rounded-xl text-red-600 dark:text-red-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Approved Studies</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white block mt-1">0</span>
                </div>
                <div class="p-3 bg-green-50 dark:bg-green-950/30 rounded-xl text-green-600 dark:text-green-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 shadow-sm flex items-center justify-between sm:col-span-2 lg:col-span-1">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Under Evaluation</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white block mt-1">0</span>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-950/30 rounded-xl text-amber-600 dark:text-amber-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>

        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700/50 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-base text-gray-900 dark:text-white">My Submitted Manuscripts</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Real-time status loops for your active submissions.</p>
                </div>
            </div>

            <div class="p-12 text-center max-w-sm mx-auto flex flex-col items-center">
                <div class="h-12 w-12 rounded-2xl bg-gray-50 dark:bg-gray-700/50 flex items-center justify-center text-gray-400 mb-4 border border-gray-200/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a6 6 0 00-12 0m12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17.25"/>
                    </svg>
                </div>
                <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200">No projects recorded</h4>
                <p class="text-xs text-gray-400 mt-1 mb-4 leading-relaxed">You haven't uploaded any research proposals to the portal yet.</p>
            </div>
        </div>

    </div>
</x-app-layout>