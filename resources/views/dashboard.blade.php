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
                <button id="openSubmitModalBtn" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition duration-150 shadow-sm shadow-red-600/10 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Submit Proposal
                </button>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        
        <div class="relative w-full h-56 sm:h-64 rounded-2xl overflow-hidden shadow-sm border border-gray-200/60 bg-gray-900 group">
            
            <div class="relative w-full h-full">
                <div class="slide-item absolute inset-0 w-full h-full transition-all duration-700 opacity-100 scale-100">
                    <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1200&auto=format&fit=crop" class="w-full h-full object-cover object-center opacity-40 group-hover:scale-102 transition duration-700" alt="Banner Image">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-900/40 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 text-white z-10">
                        <span class="text-[10px] bg-red-600 text-white px-2 py-0.5 rounded font-black tracking-widest uppercase mb-2 inline-block">Institutional Notice</span>
                        <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">Call for Proposals Open</h3>
                        <p class="text-xs text-gray-300 mt-1 max-w-xl">Submit your institutional research projects for Fiscal Year 2026.</p>
                    </div>
                </div>

                <div class="slide-item absolute inset-0 w-full h-full transition-all duration-700 opacity-0 scale-105 pointer-events-none">
                    <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=1200&auto=format&fit=crop" class="w-full h-full object-cover object-center opacity-40 group-hover:scale-102 transition duration-700" alt="Banner Image">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-900/40 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 text-white z-10">
                        <span class="text-[10px] bg-red-600 text-white px-2 py-0.5 rounded font-black tracking-widest uppercase mb-2 inline-block">Institutional Notice</span>
                        <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">Research Ethics Review</h3>
                        <p class="text-xs text-gray-300 mt-1 max-w-xl">Ensure compliance with the newly updated BatStateU IRB guidelines.</p>
                    </div>
                </div>

                <div class="slide-item absolute inset-0 w-full h-full transition-all duration-700 opacity-0 scale-105 pointer-events-none">
                    <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1200&auto=format&fit=crop" class="w-full h-full object-cover object-center opacity-40 group-hover:scale-102 transition duration-700" alt="Banner Image">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-900/40 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 text-white z-10">
                        <span class="text-[10px] bg-red-600 text-white px-2 py-0.5 rounded font-black tracking-widest uppercase mb-2 inline-block">Institutional Notice</span>
                        <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">Faculty Writing Workshop</h3>
                        <p class="text-xs text-gray-300 mt-1 max-w-xl">Join our technical writing mentorship camp this coming month.</p>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-4 right-6 flex items-center gap-1.5 z-20">
                <button class="slide-dot h-1.5 w-5 bg-red-600 rounded-full transition-all duration-300 cursor-pointer focus:outline-none"></button>
                <button class="slide-dot h-1.5 w-1.5 bg-white/40 hover:bg-white/70 rounded-full transition-all duration-300 cursor-pointer focus:outline-none"></button>
                <button class="slide-dot h-1.5 w-1.5 bg-white/40 hover:bg-white/70 rounded-full transition-all duration-300 cursor-pointer focus:outline-none"></button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Active Proposals</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white block mt-1">0</span>
                </div>
                <div class="p-3 bg-red-50 dark:bg-red-950/30 rounded-xl text-red-600 dark:text-red-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Approved Studies</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white block mt-1">0</span>
                </div>
                <div class="p-3 bg-green-50 dark:bg-green-950/30 rounded-xl text-green-600 dark:text-green-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/60 dark:border-gray-700/50 shadow-sm flex items-center justify-between sm:col-span-2 lg:col-span-1">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Under Evaluation</span>
                    <span class="text-3xl font-black text-gray-900 dark:text-white block mt-1">0</span>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-950/30 rounded-xl text-amber-600 dark:text-amber-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
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
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a6 6 0 00-12 0m12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17.25" /></svg>
                </div>
                <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200">No projects recorded</h4>
                <p class="text-xs text-gray-400 mt-1 mb-4 leading-relaxed">You haven't uploaded any research proposals to the portal yet.</p>
            </div>
        </div>

        <div id="submitProposalModal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6">
            <div id="closeModalBackdrop" class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity cursor-pointer"></div>

            <div class="relative bg-white dark:bg-gray-900 rounded-3xl border border-gray-200/80 dark:border-gray-800/80 shadow-2xl w-full max-w-lg overflow-hidden transform transition-all z-10">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 dark:border-gray-800/60 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white tracking-tight">Submit Research Manuscript</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Download template guidelines and upload your copy.</p>
                    </div>
                    <button id="closeModalCrossBtn" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="#" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                    <div class="space-y-2">
                        <label class="text-xs font-black text-gray-400 uppercase tracking-wider block">Step 1: Download Target Template</label>
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200/60 dark:border-gray-700/40 rounded-2xl flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 rounded-xl">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200">ATHENA_Proposal_Form.docx</p>
                                    <p class="text-[11px] text-gray-400 font-medium">Standardized Layout</p>
                                </div>
                            </div>
                            <a href="#download-dummy-file" class="inline-flex items-center gap-1.5 bg-gray-900 hover:bg-gray-800 dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-900 text-xs font-bold px-3 py-2 rounded-xl transition duration-150 shadow-sm cursor-pointer">Download</a>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-black text-gray-400 uppercase tracking-wider block">Step 2: Upload Document File Copy</label>
                        <div class="border-2 border-dashed border-gray-200 dark:border-gray-700/80 hover:border-red-500/50 rounded-2xl p-8 text-center bg-white dark:bg-gray-900 transition relative group">
                            <input type="file" name="proposal_file" id="proposal_file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <div class="flex flex-col items-center pointer-events-none">
                                <div class="h-10 w-10 rounded-xl bg-red-50 dark:bg-red-950/20 flex items-center justify-center text-red-600 dark:text-red-400 mb-3 group-hover:scale-110 transition duration-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                                </div>
                                <p class="text-sm font-bold text-gray-800 dark:text-gray-200">Drag & drop completed copy here</p>
                                <p class="text-xs text-gray-400 mt-1">Accepts document extensions (.docx, .pdf) up to 25MB</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800/60">
                        <button id="closeModalCancelBtn" type="button" class="px-4 py-2.5 text-xs font-bold text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition cursor-pointer">Cancel</button>
                        <button id="submitManuscriptFormBtn" type="button" class="px-5 py-2.5 text-xs font-bold bg-red-600 hover:bg-red-700 text-white rounded-xl shadow-sm transition cursor-pointer">Submit Manuscript</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        function initializeWorkspaceEngine() {
            const modal = document.getElementById('submitProposalModal');
            const openBtn = document.getElementById('openSubmitModalBtn');
            const backdrop = document.getElementById('closeModalBackdrop');
            const crossBtn = document.getElementById('closeModalCrossBtn');
            const cancelBtn = document.getElementById('closeModalCancelBtn');
            const submitFormBtn = document.getElementById('submitManuscriptFormBtn');

            // Toggle function locked inside scope
            function setModalVisibility(visible) {
                if (!modal) return;
                if (visible) {
                    modal.classList.remove('hidden');
                } else {
                    modal.classList.add('hidden');
                }
            }

            // Bind click nodes cleanly
            if (openBtn) openBtn.onclick = () => setModalVisibility(true);
            if (backdrop) backdrop.onclick = () => setModalVisibility(false);
            if (crossBtn) crossBtn.onclick = () => setModalVisibility(false);
            if (cancelBtn) cancelBtn.onclick = () => setModalVisibility(false);
            if (submitFormBtn) submitFormBtn.onclick = () => setModalVisibility(false);

            // --- 🎞️ Carousel Logic Implementation ---
            let currentSlide = 0;
            const slides = document.querySelectorAll('.slide-item');
            const dots = document.querySelectorAll('.slide-dot');

            function goToSlide(index) {
                if (!slides.length || !dots.length) return;
                currentSlide = index;
                slides.forEach((slide, i) => {
                    if (i === currentSlide) {
                        slide.classList.remove('opacity-0', 'scale-105', 'pointer-events-none');
                        slide.classList.add('opacity-100', 'scale-100');
                        dots[i].classList.remove('w-1.5', 'bg-white/40');
                        dots[i].classList.add('w-5', 'bg-red-600');
                    } else {
                        slide.classList.remove('opacity-100', 'scale-100');
                        slide.classList.add('opacity-0', 'scale-105', 'pointer-events-none');
                        dots[i].classList.remove('w-5', 'bg-red-600');
                        dots[i].classList.add('w-1.5', 'bg-white/40');
                    }
                });
            }

            // Apply manual click indicators
            dots.forEach((dot, index) => {
                dot.onclick = () => goToSlide(index);
            });

            // Clean background loop clear setup
            if(window.workspaceCarouselInterval) clearInterval(window.workspaceCarouselInterval);
            
            window.workspaceCarouselInterval = setInterval(() => {
                if (slides.length) {
                    let nextSlide = (currentSlide + 1) % slides.length;
                    goToSlide(nextSlide);
                }
            }, 6000);
        }

        // 🚀 CRITICAL: Run initialization instantly AND on every Livewire page switch
        initializeWorkspaceEngine();
        document.addEventListener('livewire:navigated', initializeWorkspaceEngine);
    </script>
</x-app-layout>