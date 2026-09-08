<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Proposal Submissions</h2>
            <a href="{{ route('signatories.index') }}" class="mt-2 mr-4 inline-flex text-sm font-semibold text-red-700 dark:text-red-300">Manage signatory names</a>
            <a href="{{ route('similarity-checks.index') }}" class="mt-2 inline-flex text-sm font-semibold text-red-700 dark:text-red-300">Similarity-check requests</a>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Receive active proposal packages, review their current status, and retain every submitted version.</p>
        </div>
    </x-slot>

    <div class="space-y-6" data-proposal-submissions>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['Proposal records', $summary['proposals'], 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300'],
                ['Active queue', $summary['active'], 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'],
                ['All submissions', $summary['total'], 'bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-200'],
                ['Initial packages', $summary['initial'], 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300'],
                ['Revisions received', $summary['revision'], 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'],
            ] as [$label, $count, $style])
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-slate-500">{{ $label }}</p>
                    <p class="mt-2 inline-flex rounded-xl px-3 py-1 text-2xl font-black {{ $style }}">{{ \Illuminate\Support\Number::format($count) }}</p>
                </div>
            @endforeach
        </div>

        <section aria-labelledby="active-proposal-queue-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h3 id="active-proposal-queue-heading" class="text-lg font-black text-gray-900 dark:text-white">Active proposal queue</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">One current record per proposal. A red dot marks a submission that still needs your review.</p>
                </div>
                <div class="flex flex-wrap justify-end gap-2 text-xs font-bold">
                    @if (count($unreadProposalTopicIds) > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-900">
                            <span class="h-2 w-2 rounded-full bg-red-600 dark:bg-red-400" aria-hidden="true"></span>
                            {{ count($unreadProposalTopicIds) }} {{ count($unreadProposalTopicIds) === 1 ? 'needs' : 'need' }} review
                        </span>
                    @endif
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600 dark:bg-slate-800 dark:text-slate-300">{{ $activeProposals->total() }} active {{ Str::plural('proposal', $activeProposals->total()) }}</span>
                </div>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($activeProposals as $proposal)
                    @php
                        [$statusLabel, $statusDescription, $statusStyle] = match ($proposal->status) {
                            'lrec_queued' => ['Awaiting LREC presentation', 'Initial review is cleared. Record the outcome after the presentation.', 'bg-gray-100 text-gray-800 dark:bg-slate-800 dark:text-slate-200'],
                            'lrec_review' => ['LREC review', 'Record committee feedback or clear the proposal for signing.', 'bg-gray-100 text-gray-800 dark:bg-slate-800 dark:text-slate-200'],
                            'expert_review' => ['Under expert review', 'The assigned expert is evaluating this package.', 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-200'],
                            'for_final_decision' => ['Awaiting decision', 'The review stage is complete and the proposal needs a Research Head decision.', 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-200'],
                            'revision_requested' => ['Revision requested', 'The faculty member is preparing the requested corrections.', 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200'],
                            'resubmitted' => ['Resubmitted', 'A revised package was received and needs another review.', 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-200'],
                            'ready_for_signature' => ['Final signing', 'Selected final papers are awaiting signed copies.', 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200'],
                            default => ['New submission', 'A proposal package was received and is ready to enter review.', 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'],
                        };
                        $latestSubmission = $proposal->latestVersion;
                        $receivedAt = $latestSubmission?->created_at ?? $proposal->created_at;
                        $requiresAttention = in_array($proposal->id, $unreadProposalTopicIds, true);
                        $isRevisedSubmission = $latestSubmission?->submission_type === 'revision';
                    @endphp

                    <article
                        data-proposal-id="{{ $proposal->id }}"
                        data-proposal-attention="{{ $requiresAttention ? 'unread' : 'seen' }}"
                        @class([
                            'relative flex min-h-64 flex-col overflow-hidden rounded-2xl border bg-white p-5 shadow-sm transition dark:bg-slate-900',
                            'border-red-300 ring-2 ring-red-100 shadow-red-100/60 dark:border-red-800 dark:ring-red-950/70 dark:shadow-none' => $requiresAttention,
                            'border-gray-200 dark:border-slate-800' => ! $requiresAttention,
                        ])
                    >
                        @if ($requiresAttention)
                            <div class="absolute inset-x-0 top-0 h-1 bg-red-600 dark:bg-red-500" aria-hidden="true"></div>
                        @endif

                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($requiresAttention)
                                    <span data-proposal-unread-dot class="inline-flex items-center gap-1.5 rounded-full bg-red-600 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white">
                                        <span class="h-2 w-2 rounded-full bg-white" aria-hidden="true"></span>
                                        Needs review
                                    </span>
                                @endif
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusStyle }}">{{ $statusLabel }}</span>
                            </div>
                            <time datetime="{{ $receivedAt?->toIso8601String() }}" class="shrink-0 text-[11px] text-gray-500 dark:text-slate-400">{{ $receivedAt?->diffForHumans() }}</time>
                        </div>

                        <h4 class="mt-4 line-clamp-2 text-base font-black leading-6 text-gray-900 dark:text-white">{{ $proposal->title }}</h4>
                        <p class="mt-2 text-xs font-semibold text-gray-600 dark:text-slate-300">{{ $proposal->user->name }}</p>
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-slate-400">{{ $proposal->researchCall?->title ?? 'Research call unavailable' }}</p>
                        @if ($proposal->researchCall?->academic_year)
                            <p class="mt-1 text-[11px] font-semibold text-gray-500 dark:text-slate-400">AY {{ $proposal->researchCall->academic_year }}</p>
                        @endif

                        <p class="mt-4 text-xs leading-5 text-gray-600 dark:text-slate-300">{{ $statusDescription }}</p>

                        <div @class([
                            'mt-4 rounded-xl border px-3 py-3',
                            'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/40' => $isRevisedSubmission,
                            'border-gray-100 bg-gray-50 dark:border-slate-800 dark:bg-slate-950' => ! $isRevisedSubmission,
                        ])>
                            @if ($latestSubmission)
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-wider {{ $isRevisedSubmission ? 'text-blue-700 dark:text-blue-300' : 'text-gray-500 dark:text-slate-400' }}">
                                            {{ $isRevisedSubmission ? 'Revised package received' : 'Initial package received' }}
                                        </p>
                                        <p class="mt-1 text-xs font-bold {{ $isRevisedSubmission ? 'text-blue-950 dark:text-blue-100' : 'text-gray-700 dark:text-slate-200' }}">
                                            Version {{ $latestSubmission->version_number }} · {{ $isRevisedSubmission ? 'Faculty revision' : 'First submission' }}
                                        </p>
                                    </div>
                                    @if ($isRevisedSubmission)
                                        <svg class="h-5 w-5 shrink-0 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.5 15a7 7 0 0 0 11.8 2.2L20 14M4 10l2.7-3.2A7 7 0 0 1 18.5 9" />
                                        </svg>
                                    @endif
                                </div>
                            @else
                                <p class="text-xs font-semibold text-gray-600 dark:text-slate-300">Submitted proposal record</p>
                            @endif
                        </div>

                        <div class="mt-auto pt-6">
                            <a href="{{ route('topics.show', $proposal) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-500 dark:focus:ring-offset-slate-900">
                                {{ $proposal->status === 'revision_requested' ? 'Open revision record' : 'Open for review' }}
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900 md:col-span-2 xl:col-span-3">
                        <h4 class="text-base font-black text-gray-900 dark:text-white">No active proposals in the queue</h4>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-500 dark:text-slate-400">New and revised packages will appear here when they are received. Approved projects remain in Project Monitoring.</p>
                    </div>
                @endforelse
            </div>

            @if ($activeProposals->hasPages())
                <div class="mt-6">{{ $activeProposals->links() }}</div>
            @endif
        </section>

        <form method="GET" action="{{ route('research_head.proposal-submissions.index') }}" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:grid-cols-[minmax(0,1fr)_190px_210px_auto]">
            <label class="sr-only" for="proposal-submission-search">Search proposal submissions</label>
            <input id="proposal-submission-search" name="search" type="search" value="{{ $search }}" placeholder="Search proposal, faculty, or research call..." class="block w-full rounded-xl border-gray-200 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">

            <label class="sr-only" for="proposal-submission-type">Submission type</label>
            <select id="proposal-submission-type" name="type" class="block w-full rounded-xl border-gray-200 text-sm font-semibold dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <option value="">All submission types</option>
                <option value="initial" @selected($submissionType === 'initial')>Initial packages</option>
                <option value="revision" @selected($submissionType === 'revision')>Revisions</option>
            </select>

            <label class="sr-only" for="proposal-submission-status">Proposal status</label>
            <select id="proposal-submission-status" name="status" class="block w-full rounded-xl border-gray-200 text-sm font-semibold dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <option value="">All proposal statuses</option>
                @foreach ([
                    'pending' => 'Pending',
                    'expert_review' => 'Awaiting Research Head',
                    'for_final_decision' => 'Awaiting Research Head',
                    'lrec_queued' => 'Awaiting LREC presentation',
                    'lrec_review' => 'LREC review',
                    'revision_requested' => 'Revision requested',
                    'resubmitted' => 'Resubmitted',
                    'ready_for_signature' => 'Ready for signature',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <div class="flex gap-2">
                <button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Filter</button>
                @if ($search !== '' || $submissionType !== '' || $status !== '')
                    <a href="{{ route('research_head.proposal-submissions.index') }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 transition hover:bg-gray-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                @endif
            </div>
        </form>

        <section aria-labelledby="proposal-submission-records-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-slate-800">
                <h3 id="proposal-submission-records-heading" class="text-base font-black text-gray-900 dark:text-white">Submission history</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Records are ordered by the most recently received package.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-left">
                    <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:bg-slate-950/60 dark:text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Submission</th>
                            <th class="px-5 py-3">Proposal and faculty</th>
                            <th class="px-5 py-3">Research call</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Files</th>
                            <th class="px-5 py-3">Received</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse ($submissions as $submission)
                            @php
                                $isRevision = $submission->submission_type === 'revision';
                                $fileCount = $submission->package_files_count ?: ($submission->file_path ? 1 : 0);
                                $statusStyle = match ($submission->topic->status) {
                                    'approved' => 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300',
                                    'ready_for_signature' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
                                    'rejected' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
                                    'revision_requested' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                                    'expert_review', 'resubmitted' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300',
                                    'for_final_decision' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300',
                                    default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                                };
                            @endphp
                            <tr class="align-top transition hover:bg-gray-50/70 dark:hover:bg-slate-800/50">
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $isRevision ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' }}">
                                        {{ $isRevision ? 'Revision' : 'Initial submission' }}
                                    </span>
                                    <p class="mt-2 text-xs font-black text-gray-700 dark:text-slate-200">Version {{ $submission->version_number }}</p>
                                </td>
                                <td class="max-w-sm px-5 py-4">
                                    <p class="break-words text-sm font-black text-gray-900 dark:text-white">{{ $submission->title }}</p>
                                    <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-slate-400">{{ $submission->topic->user->name }}</p>
                                    <p class="mt-0.5 text-[11px] text-gray-400 dark:text-slate-500">{{ $submission->topic->user->email }}</p>
                                    @if ($submission->change_summary)
                                        <p class="mt-2 line-clamp-2 text-[11px] leading-5 text-blue-700 dark:text-blue-300"><span class="font-black">Changes:</span> {{ $submission->change_summary }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <p class="max-w-52 text-xs font-bold text-gray-700 dark:text-slate-200">{{ $submission->topic->researchCall?->title ?? 'Research call unavailable' }}</p>
                                    @if ($submission->topic->researchCall?->academic_year)
                                        <p class="mt-1 text-[11px] text-gray-400 dark:text-slate-500">AY {{ $submission->topic->researchCall->academic_year }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusStyle }}">{{ str($submission->topic->status)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="whitespace-nowrap text-xs font-black text-gray-700 dark:text-slate-200">{{ $fileCount }} {{ Str::plural('package file', $fileCount) }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <time datetime="{{ $submission->created_at->toIso8601String() }}" class="whitespace-nowrap text-xs font-bold text-gray-600 dark:text-slate-300">{{ $submission->created_at->format('M j, Y') }}</time>
                                    <p class="mt-1 whitespace-nowrap text-[11px] text-gray-400 dark:text-slate-500">{{ $submission->created_at->format('g:i A') }}</p>
                                    <p class="mt-1 max-w-40 text-[10px] text-gray-400 dark:text-slate-500">By {{ $submission->submitter?->name ?? 'Former user' }}</p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('topics.show', $submission->topic) }}#version-history" class="inline-flex whitespace-nowrap items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-500 dark:focus:ring-offset-slate-900">View history</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-14 text-center">
                                    <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No proposal submissions found</p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">Try changing the search or filters.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($submissions->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800">{{ $submissions->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
