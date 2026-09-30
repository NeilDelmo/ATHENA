<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proposal Submissions" subtitle="Review current proposal packages and find earlier submitted versions.">
        </x-page-header>
    </x-slot>

    <div class="space-y-5" data-proposal-submissions>
        <x-kpi-strip data-submission-summary :items="[
            ['label' => 'Proposal records', 'value' => \Illuminate\Support\Number::format($summary['proposals']), 'icon' => 'folder'],
            ['label' => 'Active queue', 'value' => \Illuminate\Support\Number::format($summary['active']), 'icon' => 'clock'],
            ['label' => 'All submissions', 'value' => \Illuminate\Support\Number::format($summary['total']), 'icon' => 'layers'],
            ['label' => 'Initial packages', 'value' => \Illuminate\Support\Number::format($summary['initial']), 'icon' => 'file-plus'],
            ['label' => 'Revisions received', 'value' => \Illuminate\Support\Number::format($summary['revision']), 'icon' => 'refresh'],
        ]" />

        <form method="GET" action="{{ route('research_head.proposal-submissions.index') }}" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_180px_210px_auto]">
            <label class="sr-only" for="proposal-submission-search">Search proposal submissions</label>
            <input id="proposal-submission-search" name="search" type="search" value="{{ $search }}" placeholder="Search proposal, faculty, or research call..." class="block w-full rounded-xl border-gray-200 text-sm focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
            <label class="sr-only" for="proposal-submission-type">Submission type</label>
            <select id="proposal-submission-type" name="type" class="block w-full rounded-xl border-gray-200 text-sm focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">All submission types</option>
                <option value="initial" @selected($submissionType === 'initial')>Initial packages</option>
                <option value="revision" @selected($submissionType === 'revision')>Revisions</option>
            </select>
            <label class="sr-only" for="proposal-submission-status">Active review stage</label>
            <select id="proposal-submission-status" name="status" class="block w-full rounded-xl border-gray-200 text-sm focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">All active review stages</option>
                @foreach ([
                    'pending' => 'New submission / Needs review',
                    'gad_assessment' => 'GAD assessment',
                    'co_evaluator_review' => 'Co-evaluator review',
                    'lrec_queued' => 'Awaiting LREC presentation',
                    'lrec_review' => 'LREC review',
                    'revision_requested' => 'Revision requested',
                    'resubmitted' => 'New revision / Needs review',
                    'ready_for_signature' => 'Final signing',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 dark:bg-red-800 dark:hover:bg-red-700 dark:focus:ring-red-400 dark:focus:ring-offset-slate-900">Filter</button>
                @if ($search !== '' || $submissionType !== '' || $status !== '')
                    <a href="{{ route('research_head.proposal-submissions.index') }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                @endif
            </div>
        </form>

        <div x-data="{ workflowOpen: false }" data-submission-workflow-reference>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-gray-500 dark:text-slate-400">Use the workflow as a reference for queue labels and what the faculty needs to do next.</p>
                <button type="button" @click="workflowOpen = ! workflowOpen" :aria-expanded="workflowOpen.toString()" aria-expanded="false" aria-controls="submission-workflow-guide" data-submission-workflow-toggle class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand dark:border-red-900 dark:bg-slate-900 dark:text-red-200 dark:hover:bg-red-950/40 dark:focus-visible:ring-red-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" d="M12 10.5v5m0-8.25h.01" /></svg>
                    <span x-text="workflowOpen ? 'Hide workflow' : 'Show workflow'">Show workflow</span>
                </button>
            </div>
            <div x-cloak x-show="workflowOpen">
                <x-proposal-workflow id="submission-workflow-guide" />
            </div>
        </div>

        <section aria-labelledby="active-proposal-queue-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 id="active-proposal-queue-heading" class="text-base font-black text-gray-900 dark:text-white">Active proposal queue</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">One current package per proposal. New packages are marked in red.</p>
                </div>
                <span class="shrink-0 text-xs tabular-nums text-gray-500 dark:text-slate-400">{{ $activeProposals->total() }} active</span>
            </div>
            <div class="overflow-x-auto">
                <table data-proposal-queue-layout="table" class="min-w-full divide-y divide-gray-100 dark:divide-slate-800">
                    <caption class="sr-only">Active proposal queue, showing the current package for each proposal.</caption>
                    <thead class="bg-gray-50 dark:bg-slate-800/60">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400 dark:text-slate-400">Proposal and faculty</th>
                            <th scope="col" class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400 dark:text-slate-400">Review stage</th>
                            <th scope="col" class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400 dark:text-slate-400">Latest package</th>
                            <th scope="col" class="px-5 py-3"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse ($activeProposals as $proposal)
                            @php
                                $latestSubmission = $proposal->latestVersion;
                                $isNewlyReceivedVersion = in_array($proposal->status, ['pending', 'resubmitted'], true) && ! $proposal->latestVersionHasBeenViewedByResearchHead();
                                $statusLabel = $proposal->researchHeadQueueStatusLabel($latestSubmission);
                                $receivedAt = $latestSubmission?->created_at ?? $proposal->created_at;
                                $isRevisedSubmission = $latestSubmission?->submission_type === 'revision';
                            @endphp
                            <tr data-proposal-id="{{ $proposal->id }}" data-proposal-state="{{ $isNewlyReceivedVersion ? 'new' : 'opened' }}"
                                @class([
                                    'border-l-2',
                                    'border-l-red-600 dark:border-l-red-400' => $isNewlyReceivedVersion,
                                    'border-l-transparent' => ! $isNewlyReceivedVersion,
                                ])>
                                <td class="min-w-[16rem] px-5 py-4">
                                    <h4 class="break-words text-sm font-black text-gray-900 dark:text-white">{{ $proposal->title }}</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $proposal->user->name }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="sr-only">Review stage:</span>
                                    <span data-proposal-status-label="{{ $statusLabel }}" @class([
                                        'inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase',
                                        'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $isNewlyReceivedVersion,
                                        'bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-200' => ! $isNewlyReceivedVersion,
                                    ])>{{ $statusLabel }}</span>
                                </td>
                                <td class="px-5 py-4 text-xs text-gray-400 dark:text-slate-400">
                                    @if ($latestSubmission)
                                        <p class="whitespace-nowrap font-black text-gray-800 dark:text-slate-200">{{ $isRevisedSubmission ? 'Revised package' : 'Initial package' }} · Version {{ $latestSubmission->version_number }}</p>
                                    @else
                                        <p>Submitted proposal record</p>
                                    @endif
                                    <time datetime="{{ $receivedAt?->toIso8601String() }}" title="{{ $receivedAt?->format('M j, Y g:i A') }}" class="mt-1 block whitespace-nowrap text-[11px]">{{ $receivedAt?->diffForHumans() }}</time>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('topics.show', $proposal) }}" aria-label="{{ $proposal->status === 'revision_requested' ? 'Revision record for ' : 'Review ' }}{{ $proposal->title }}" class="inline-flex items-center gap-1 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">
                                        {{ $proposal->status === 'revision_requested' ? 'Revision record' : 'Review' }} <span class="text-[10px] leading-none" aria-hidden="true">&gt;</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-14 text-center">
                                <h4 class="text-sm font-bold text-gray-700 dark:text-slate-200">No active proposals in the queue</h4>
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">New and revised packages will appear here. Approved projects remain in Project Monitoring.</p>
                            </td></tr>
                        @endforelse
                        </tbody>
                </table>
            </div>
            @if ($activeProposals->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800">{{ $activeProposals->links() }}</div>
            @endif
        </section>

        <details id="submission-history" data-submission-history @if ($search !== '' || $submissionType !== '' || $status !== '' || request()->has('page')) open @endif class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-gray-500 dark:hover:bg-slate-800 dark:focus-visible:ring-slate-400 [&::-webkit-details-marker]:hidden">
                <div>
                    <h3 id="proposal-submission-records-heading" class="text-base font-black text-gray-900 dark:text-white">Submission history <span class="ml-2 text-xs font-normal tabular-nums text-gray-500 dark:text-slate-400">{{ $submissions->total() }}</span></h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">All submitted versions, newest first.</p>
                </div>
                <svg class="h-4 w-4 shrink-0 text-gray-500 group-open:rotate-180 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
            </summary>
            <div class="overflow-x-auto border-t border-gray-200 dark:border-slate-800">
                <table data-submission-history-layout="compact" class="min-w-full divide-y divide-gray-100 text-left text-xs dark:divide-slate-800">
                    <caption class="sr-only">Submission history, ordered by most recently received package. Review stage shows the proposal's current stage.</caption>
                    <thead class="bg-gray-50 text-[11px] font-black uppercase text-gray-400 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-3">Proposal and faculty</th><th scope="col" class="px-5 py-3">Package</th><th scope="col" class="px-5 py-3">Current review stage</th><th scope="col" class="px-5 py-3">Received</th><th scope="col" class="px-5 py-3 text-right"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse ($submissions as $submission)
                            @php
                                $isRevision = $submission->submission_type === 'revision';
                                $fileCount = $submission->package_files_count ?: ($submission->file_path ? 1 : 0);
                                $historyStatusLabel = $submission->topic->researchHeadQueueStatusLabel($submission->topic->latestVersion);
                            @endphp
                            <tr>
                                <td class="min-w-[16rem] px-5 py-4">
                                    <p class="break-words text-sm font-black text-gray-900 dark:text-white">{{ $submission->title }}</p>
                                    <p class="mt-0.5 text-gray-500 dark:text-slate-400">{{ $submission->topic->user->name }}</p>
                                    <details class="mt-1 text-[11px] text-gray-500 dark:text-slate-400">
                                        <summary class="w-fit cursor-pointer rounded-sm font-medium hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-500 dark:hover:text-white">Package details</summary>
                                        <div class="space-y-1 py-2 leading-5">
                                            <p>{{ $submission->topic->user->email }}</p>
                                            <p>{{ $submission->topic->researchCall?->title ?? 'Research call unavailable' }}@if ($submission->topic->researchCall?->academic_year) · AY {{ $submission->topic->researchCall->academic_year }}@endif</p>
                                            <p>Submitted by {{ $submission->submitter?->name ?? 'Former user' }}</p>
                                            @if ($submission->change_summary)
                                                <p><span class="font-semibold">Changes:</span> {{ $submission->change_summary }}</p>
                                            @endif
                                        </div>
                                    </details>
                                </td>
                                <td class="px-5 py-4 text-gray-800 dark:text-slate-200">
                                    <p class="whitespace-nowrap font-black">{{ $isRevision ? 'Revision' : 'Initial submission' }} · Version {{ $submission->version_number }}</p>
                                    <p class="mt-1 whitespace-nowrap text-[11px] text-gray-500 dark:text-slate-400">{{ $fileCount }} {{ Str::plural('package file', $fileCount) }}</p>
                                </td>
                                <td class="px-5 py-4"><span data-proposal-history-status-label="{{ $historyStatusLabel }}" class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ $historyStatusLabel }}</span></td>
                                <td class="px-5 py-4 text-gray-500 dark:text-slate-400">
                                    <time datetime="{{ $submission->created_at->toIso8601String() }}" class="whitespace-nowrap">{{ $submission->created_at->format('M j, Y') }}</time>
                                    <p class="mt-1 whitespace-nowrap text-[11px]">{{ $submission->created_at->format('g:i A') }}</p>
                                </td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('topics.show', $submission->topic) }}#version-history" aria-label="View history for {{ $submission->title }}, version {{ $submission->version_number }}" class="inline-flex items-center gap-1 whitespace-nowrap rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">View history <span class="text-[10px] leading-none" aria-hidden="true">&gt;</span></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-14 text-center"><p class="text-sm font-bold text-gray-700 dark:text-slate-200">No proposal submissions found</p><p class="mt-1 text-gray-500 dark:text-slate-400">Try changing the search or filters.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($submissions->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800">{{ $submissions->links() }}</div>
            @endif
        </details>
    </div>
</x-app-layout>
