<div wire:key="research-head-dashboard">
    <section aria-labelledby="proposal-pipeline-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
        <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 id="proposal-pipeline-heading" class="text-sm font-black text-gray-950 dark:text-white">Proposal pipeline</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Click a stage to filter the inbox below. Use the status filter for revision, rejected, and other stages.</p>
            </div>
            @if ($pipeline)
                <button wire:click="clearPipeline" type="button" class="shrink-0 text-xs font-black text-[#7A0019] hover:text-red-700 dark:text-red-300 dark:hover:text-red-200">Clear filter</button>
            @endif
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4">
            @php
                $stages = [
                    ['awaiting_review', 'Awaiting your review', $summary['awaiting_review']],
                    ['ready_for_signature', 'Needs signed copies', $summary['ready_for_signature']],
                    ['awaiting_notice', 'Issue notice to proceed', $summary['awaiting_notice']],
                    ['approved', 'Active projects', $summary['approved']],
                ];
            @endphp
            @foreach ($stages as [$key, $label, $count])
                <button
                    type="button"
                    wire:click="setPipeline('{{ $key }}')"
                    aria-pressed="{{ $pipeline === $key ? 'true' : 'false' }}"
                    class="group relative flex min-h-32 flex-col border-b border-r border-gray-200 px-4 py-4 text-left transition-colors last:border-r-0 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#7A0019] dark:border-gray-800 dark:hover:bg-gray-900/70 dark:focus:ring-red-400 sm:min-h-36 {{ $pipeline === $key ? 'bg-red-50/60 dark:bg-red-950/20' : '' }} {{ $loop->last ? 'sm:border-r-0' : '' }}"
                >
                    <p class="max-w-[10rem] text-xs font-bold leading-5 text-gray-600 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-black tracking-tight text-gray-950 dark:text-white">{{ $count }}</p>
                    <div class="mt-auto pt-3">
                        <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider {{ $pipeline === $key ? 'text-[#7A0019] dark:text-red-300' : 'text-gray-500 dark:text-gray-400 group-hover:text-[#7A0019] dark:group-hover:text-red-300' }}">
                            {{ $pipeline === $key ? 'Showing' : 'Show' }}
                            <span aria-hidden="true" class="transition group-hover:translate-x-0.5">&rarr;</span>
                        </span>
                    </div>
                </button>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="inbox-controls-heading" class="mt-5 rounded-2xl border border-gray-200 bg-white px-4 py-4 shadow-sm dark:border-gray-800 dark:bg-gray-950 sm:px-5">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h3 id="inbox-controls-heading" class="text-sm font-black text-gray-950 dark:text-white">Inbox controls</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Find a proposal by title, faculty member, or review status.</p>
            </div>
            @if ($search !== '' || $pipeline)
                <button wire:click="$set('search', ''); clearPipeline()" type="button" class="shrink-0 text-xs font-black text-[#7A0019] hover:text-red-700 dark:text-red-300 dark:hover:text-red-200">Reset filters</button>
            @endif
        </div>

        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
            <label class="sr-only" for="proposal-search">Search proposals</label>
            <input id="proposal-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Search proposal or faculty..." class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 placeholder:text-gray-400 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-red-400 dark:focus:ring-red-400">
        </div>
    </section>

    <section aria-labelledby="received-proposals-heading" wire:key="inbox-{{ $pipeline }}-{{ $search }}" class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-5 dark:border-gray-800 sm:flex-row sm:items-end sm:justify-between">
            <div class="border-l-4 border-[#7A0019] pl-3 dark:border-red-500">
                <h3 id="received-proposals-heading" class="text-base font-black text-gray-950 dark:text-white">Received proposal inbox</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Open a submission to review its package and record your decision.</p>
            </div>
            <p class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }}</p>
        </div>

        <div class="divide-y divide-gray-200 dark:divide-gray-800">
            @forelse ($topics as $topic)
                @php
                    $latestVersion = $topic->versions->sortByDesc('version_number')->first();
                    $latestFiles = $latestVersion?->files ?? collect();
                    $latestReview = $topic->reviews->sortByDesc('created_at')->first();
                    $statusStyle = match (true) {
                        $topic->status === 'approved' && ! $topic->isMonitoringAvailable() => 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900',
                        $topic->status === 'approved' => 'bg-gray-950 text-white ring-gray-950 dark:bg-white dark:text-gray-950 dark:ring-white',
                        $topic->status === 'rejected' => 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-800',
                        in_array($topic->status, ['ready_for_signature', 'revision_requested'], true) => 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900',
                        default => 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-800',
                    };
                    $statusLabel = match (true) {
                        $topic->status === 'approved' && ! $topic->isMonitoringAvailable() => 'Approved - issue notice',
                        $topic->status === 'approved' => 'Notice issued',
                        $topic->status === 'ready_for_signature' => 'Ready for signature',
                        $topic->status === 'rejected' => 'Rejected',
                        $topic->status === 'revision_requested' => 'Revision required',
                        $topic->status === 'resubmitted' => 'Revision awaiting review',
                        default => 'Awaiting review',
                    };
                @endphp

                <article class="grid gap-5 px-5 py-6 transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-900/40 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-stretch lg:gap-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                            <h4 class="break-words text-base font-black text-gray-950 dark:text-white">{{ $topic->title }}</h4>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-black ring-1 ring-inset {{ $statusStyle }}">{{ $statusLabel }}</span>
                        </div>
                        <p class="mt-2 text-sm font-semibold text-gray-600 dark:text-gray-400">{{ $topic->researchCall->title }}@if ($topic->category) <span class="text-gray-300 dark:text-gray-700">/</span> {{ $topic->category->name }}@endif</p>
                        <p class="mt-3 line-clamp-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $topic->description ?: 'No proposal summary provided.' }}</p>

                        @if ($latestReview?->comment)
                            <div class="mt-4 border-l-2 border-[#7A0019] bg-gray-50 px-4 py-3 dark:border-red-500 dark:bg-gray-900/70">
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-[#7A0019] dark:text-red-300">Latest review feedback</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $latestReview->comment }}</p>
                                @if ($topic->status === 'revision_requested')
                                    <p class="mt-2 text-[10px] font-black uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Waiting for faculty revision</p>
                                @endif
                            </div>
                        @endif

                        <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                            <div><dt class="inline font-black uppercase tracking-wider text-gray-400 dark:text-gray-600">Faculty</dt> <dd class="ml-1 inline font-bold text-gray-700 dark:text-gray-300">{{ $topic->user->name }}</dd></div>
                            <div><dt class="inline font-black uppercase tracking-wider text-gray-400 dark:text-gray-600">Cost</dt> <dd class="ml-1 inline font-bold text-gray-700 dark:text-gray-300">PHP {{ number_format((float) $topic->estimated_budget, 2) }}</dd></div>
                            <div><dt class="inline font-black uppercase tracking-wider text-gray-400 dark:text-gray-600">Duration</dt> <dd class="ml-1 inline font-bold text-gray-700 dark:text-gray-300">{{ $topic->estimated_duration_months }} months</dd></div>
                        </dl>
                    </div>

                    <div class="flex flex-col border-t border-gray-200 pt-5 dark:border-gray-800 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500">Latest submission</p>
                                <p class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ $latestFiles->where('document_type', '!=', \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD)->count() }} files received</p>
                            </div>
                            @if ($latestVersion)
                                <span class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-black text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">v{{ $latestVersion->version_number }}</span>
                            @endif
                        </div>
                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $latestVersion ? $latestVersion->created_at->format('M j, Y g:i A') : 'No submitted version available' }}</p>

                        <a href="{{ route('topics.show', $topic) }}#proposal-review" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gray-950 px-4 py-3 text-sm font-black text-white transition hover:bg-[#7A0019] focus:outline-none focus:ring-2 focus:ring-[#7A0019] focus:ring-offset-2 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200 dark:focus:ring-red-400 dark:focus:ring-offset-gray-950 lg:mt-auto">
                            {{ match (true) {
                                $topic->status === 'approved' && ! $topic->isMonitoringAvailable() => 'Issue Notice to Proceed',
                                in_array($topic->status, ['approved', 'rejected'], true) => 'Open proposal record',
                                $topic->status === 'ready_for_signature' => 'Complete signatures',
                                default => 'Review proposal',
                            } }}
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </article>
            @empty
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 text-[#7A0019] dark:border-gray-800 dark:text-red-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z" />
                        </svg>
                    </div>
                    <p class="mt-4 text-sm font-black text-gray-800 dark:text-gray-200">No proposals found</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try changing the search or pipeline filter.</p>
                </div>
            @endforelse
        </div>

        @if ($topics->hasPages())
            <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">{{ $topics->links() }}</div>
        @endif
    </section>
</div>
