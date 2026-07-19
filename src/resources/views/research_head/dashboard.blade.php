<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900">Research Head Dashboard</h2>
            <p class="mt-1 text-xs text-gray-500">Receive submitted proposal packages, inspect their PDFs, and continue screening from the proposal workspace.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-bold">The request could not be completed.</p>
                <p class="mt-1 text-xs">{{ $errors->first() }}</p>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Awaiting screening', $summary['screening'], 'text-amber-700 bg-amber-50', null],
                ['With co-evaluators', $summary['expert_review'], 'text-purple-700 bg-purple-50', null],
                ['Screening complete', $summary['final_decision'], 'text-blue-700 bg-blue-50', null],
                ['Approved projects', $summary['approved'], 'text-green-700 bg-green-50', route('research_head.projects.index')],
            ] as [$label, $count, $style, $url])
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p>
                    <p class="mt-2 inline-flex rounded-xl px-3 py-1 text-2xl font-black {{ $style }}">{{ $count }}</p>
                    @if ($url)
                        <a href="{{ $url }}" class="mt-3 block text-xs font-bold text-green-700">Open project monitoring &rarr;</a>
                    @endif
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('research_head.dashboard') }}" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px_auto]">
            <input name="search" type="search" value="{{ $search }}" placeholder="Search proposal or faculty..." class="block w-full rounded-xl border-gray-200 text-sm">
            <select name="status" class="block w-full rounded-xl border-gray-200 text-sm font-semibold">
                <option value="">All proposal statuses</option>
                @foreach ([
                    'pending' => 'Pending',
                    'expert_review' => 'Initial screening',
                    'for_final_decision' => 'Screening complete',
                    'revision_requested' => 'Revision requested',
                    'resubmitted' => 'Resubmitted',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Filter</button>
                @if ($search !== '' || $status !== '')
                    <a href="{{ route('research_head.dashboard') }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600">Clear</a>
                @endif
            </div>
        </form>

        <section aria-labelledby="received-proposals-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-5">
                <h3 id="received-proposals-heading" class="text-base font-black text-gray-900">Received proposal inbox</h3>
                <p class="mt-1 text-xs text-gray-500">Open a submission to view and download every PDF before taking a screening action.</p>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($topics as $topic)
                    @php
                        $latestVersion = $topic->versions->sortByDesc('version_number')->first();
                        $latestFiles = $latestVersion?->files ?? collect();
                        $latestReview = $topic->reviews->sortByDesc('created_at')->first();
                        $statusStyle = match ($topic->status) {
                            'approved' => 'bg-green-50 text-green-700',
                            'rejected' => 'bg-red-50 text-red-700',
                            'revision_requested' => 'bg-blue-50 text-blue-700',
                            'expert_review', 'resubmitted' => 'bg-purple-50 text-purple-700',
                            'for_final_decision' => 'bg-cyan-50 text-cyan-700',
                            default => 'bg-amber-50 text-amber-700',
                        };
                    @endphp

                    <article class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="break-words text-base font-black text-gray-900">{{ $topic->title }}</h4>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusStyle }}">{{ str_replace('_', ' ', $topic->status) }}</span>
                            </div>
                            <p class="mt-1 text-xs font-semibold text-gray-500">{{ $topic->researchCall->title }}@if ($topic->category) &middot; {{ $topic->category->name }}@endif</p>
                            <p class="mt-3 line-clamp-2 text-sm leading-6 text-gray-600">{{ $topic->description ?: 'No proposal summary provided.' }}</p>
                            @if ($latestReview?->comment)
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-amber-700">Latest review feedback</p>
                                    <p class="mt-1 whitespace-pre-line text-xs leading-5 text-amber-900">{{ $latestReview->comment }}</p>
                                    @if ($topic->status === 'revision_requested')
                                        <p class="mt-2 text-[10px] font-black uppercase tracking-wider text-blue-700">Waiting for faculty revision</p>
                                    @endif
                                </div>
                            @endif
                            <dl class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600">
                                <div><dt class="inline font-bold text-gray-400">Faculty:</dt> <dd class="inline font-bold text-gray-700">{{ $topic->user->name }}</dd></div>
                                <div><dt class="inline font-bold text-gray-400">Cost:</dt> <dd class="inline font-bold text-gray-700">PHP {{ number_format((float) $topic->estimated_budget, 2) }}</dd></div>
                                <div><dt class="inline font-bold text-gray-400">Duration:</dt> <dd class="inline font-bold text-gray-700">{{ $topic->estimated_duration_months }} months</dd></div>
                            </dl>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Latest receipt</p>
                                    <p class="mt-1 text-sm font-black text-gray-900">{{ $latestFiles->count() }} PDF{{ $latestFiles->count() === 1 ? '' : 's' }} received</p>
                                </div>
                                @if ($latestVersion)
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-gray-600 shadow-sm">v{{ $latestVersion->version_number }}</span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs leading-5 text-gray-500">{{ $latestVersion ? $latestVersion->created_at->format('M j, Y g:i A') : 'No submitted version available' }}</p>

                            <a href="{{ route('topics.show', $topic) }}#submitted-files" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-3 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">
                                {{ $topic->status === 'approved' ? 'Open proposal record' : 'Open submitted package' }}
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="p-12 text-center">
                        <p class="text-sm font-bold text-gray-700">No proposals found</p>
                        <p class="mt-1 text-xs text-gray-400">Try changing the search or status filter.</p>
                    </div>
                @endforelse
            </div>

            @if ($topics->hasPages())
                <div class="border-t border-gray-100 px-5 py-4">{{ $topics->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
