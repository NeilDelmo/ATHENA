@php
    $receivedOnly = $receivedOnly ?? false;
@endphp
@php
    $submissionRoute = $receivedOnly ? 'research_head.received-submissions.index' : 'research_head.proposal-submissions.index';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :class="($receivedOnly ? '' : 'proposal-reviews-header ').'[&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6'" :title="$receivedOnly ? 'Received submissions' : 'Proposal reviews'" :subtitle="$receivedOnly ? 'Find incoming projects and revisions, newest first.' : 'Review current projects and track the next decision.'">
        </x-page-header>
    </x-slot>

    <div class="space-y-5" data-proposal-submissions @if (! $receivedOnly) data-proposal-reviews @endif>
        <x-kpi-strip class="[&_dt]:text-sm [&_dt]:leading-5 [&_dd]:!text-[28px] [&_svg]:h-4 [&_svg]:w-4" data-submission-summary :items="[
            ['label' => 'Proposal records', 'value' => \Illuminate\Support\Number::format($summary['proposals']), 'icon' => 'folder'],
            ['label' => 'Active queue', 'value' => \Illuminate\Support\Number::format($summary['active']), 'icon' => 'clock'],
            ['label' => 'All submissions', 'value' => \Illuminate\Support\Number::format($summary['total']), 'icon' => 'layers'],
            ['label' => 'Initial packages', 'value' => \Illuminate\Support\Number::format($summary['initial']), 'icon' => 'file-plus'],
            ['label' => 'Revisions received', 'value' => \Illuminate\Support\Number::format($summary['revision']), 'icon' => 'refresh'],
        ]" />

        <form method="GET" action="{{ route($submissionRoute) }}" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_220px_260px_auto]">
            <label class="sr-only" for="proposal-submission-search">Search proposal submissions</label>
            <input id="proposal-submission-search" name="search" type="search" value="{{ $search }}" placeholder="Search proposal, faculty, or research call..." class="block w-full rounded-xl border-gray-200 text-base focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
            <label class="sr-only" for="proposal-submission-type">Submission type</label>
            <select id="proposal-submission-type" name="type" class="block w-full rounded-xl border-gray-200 text-base focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <option value="">All submission types</option>
                <option value="initial" @selected($submissionType === 'initial')>Initial packages</option>
                <option value="revision" @selected($submissionType === 'revision')>Revisions</option>
            </select>
            <label class="sr-only" for="proposal-submission-status">Active review stage</label>
            <select id="proposal-submission-status" name="status" class="block w-full rounded-xl border-gray-200 text-base focus:border-gray-500 focus:ring-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
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
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 dark:bg-red-800 dark:hover:bg-red-700 dark:focus:ring-red-400 dark:focus:ring-offset-slate-900">Filter</button>
                @if ($search !== '' || $submissionType !== '' || $status !== '')
                    <a href="{{ route($submissionRoute) }}" class="inline-flex min-h-11 items-center rounded-lg border border-gray-200 px-3 text-sm font-semibold text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                @endif
            </div>
        </form>

        @if (! $receivedOnly)
        <div x-data="{ workflowOpen: false }" data-submission-workflow-reference>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-slate-400">Use the workflow as a reference for queue labels and what the faculty needs to do next.</p>
                <button type="button" @click="workflowOpen = ! workflowOpen" :aria-expanded="workflowOpen.toString()" aria-expanded="false" aria-controls="submission-workflow-guide" data-submission-workflow-toggle class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3 py-2 text-base font-bold text-red-700 shadow-sm transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand dark:border-red-900 dark:bg-slate-900 dark:text-red-200 dark:hover:bg-red-950/40 dark:focus-visible:ring-red-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" d="M12 10.5v5m0-8.25h.01" /></svg>
                    <span x-text="workflowOpen ? 'Hide workflow' : 'Show workflow'">Show workflow</span>
                </button>
            </div>
            <div x-cloak x-show="workflowOpen" class="[&_.text-xs]:text-sm [&_.text-sm]:text-base [&_.text-sm]:leading-6">
                <x-proposal-workflow id="submission-workflow-guide" />
            </div>
        </div>

        <section aria-labelledby="active-proposal-queue-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 id="active-proposal-queue-heading" class="text-xl font-black text-gray-900 dark:text-white">Active proposal queue</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">One current package per proposal. New packages are marked in red.</p>
                </div>
                <span class="shrink-0 text-sm tabular-nums text-gray-500 dark:text-slate-400">{{ $activeProposals->total() }} active</span>
            </div>
            <div data-proposal-queue-layout="rows" class="overflow-hidden rounded-b-xl border-x border-b border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1fr)_220px_180px_196px] items-center gap-4 border-b border-brand bg-brand px-4 py-3 text-sm font-semibold text-white dark:border-red-900 dark:bg-brand dark:text-white xl:grid">
                    <span>Proposal and faculty</span><span>Review stage</span><span>Latest package</span><span class="text-right">Action</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($activeProposals as $proposal)
                        @php
                            $latestSubmission = $proposal->latestVersion;
                            $isNewlyReceivedVersion = in_array($proposal->status, ['pending', 'resubmitted'], true) && ! $proposal->latestVersionHasBeenViewedByResearchHead();
                            $statusLabel = $proposal->researchHeadQueueStatusLabel($latestSubmission);
                            $receivedAt = $latestSubmission?->created_at ?? $proposal->created_at;
                            $isRevisedSubmission = $latestSubmission?->submission_type === 'revision';
                        @endphp
                        <article data-proposal-id="{{ $proposal->id }}" data-proposal-state="{{ $isNewlyReceivedVersion ? 'new' : 'opened' }}"
                            @class([
                                'grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-l-2 px-4 py-3 bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/50 xl:grid-cols-[minmax(0,1fr)_220px_180px_196px] xl:items-center xl:gap-4',
                                'border-l-red-600 dark:border-l-red-400' => $isNewlyReceivedVersion,
                                'border-l-transparent' => ! $isNewlyReceivedVersion,
                            ])>
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <h4 class="break-words text-base font-semibold leading-6 text-gray-900 dark:text-white">{{ $proposal->title }}</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $proposal->user->name }}</p>
                            </div>
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <span class="sr-only">Review stage:</span>
                                <span data-proposal-status-label="{{ $statusLabel }}" @class([
                                    'inline-flex max-w-full rounded-md px-2 py-1 text-sm font-medium leading-5',
                                    'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $isNewlyReceivedVersion,
                                    'border border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200' => ! $isNewlyReceivedVersion,
                                ])>{{ $statusLabel }}</span>
                            </div>
                            <div class="col-span-2 min-w-0 text-sm text-gray-500 dark:text-slate-400 xl:col-span-1">
                                @if ($latestSubmission)
                                    <p class="font-medium text-gray-700 dark:text-slate-200">{{ $isRevisedSubmission ? 'Revised package' : 'Initial package' }} · Version {{ $latestSubmission->version_number }}</p>
                                @else
                                    <p>Submitted proposal record</p>
                                @endif
                                <time datetime="{{ $receivedAt?->toIso8601String() }}" title="{{ $receivedAt?->format('M j, Y g:i A') }}" class="mt-1 block">{{ $receivedAt?->diffForHumans() }}</time>
                            </div>
                            <div class="col-span-2 text-right xl:col-span-1">
                                <a href="{{ route('topics.show', $proposal) }}" aria-label="{{ $proposal->status === 'revision_requested' ? 'Revision record for ' : 'Review: ' }}{{ $proposal->title }}" class="inline-flex min-h-11 w-48 whitespace-nowrap items-center justify-center gap-2 rounded-lg bg-brand px-4 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400">
                                    {{ $proposal->status === 'revision_requested' ? 'Revision record' : 'Review' }}<span aria-hidden="true">→</span>
                                </a>
                            </div>

                        </article>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white">No active proposals in the queue</h4>
                            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">New and revised packages will appear here. Approved projects remain in Project Monitoring.</p>
                        </div>
                    @endforelse
                </div>
            </div>
            @if ($activeProposals->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800">{{ $activeProposals->links() }}</div>
            @endif
        </section>

        @endif
        <{{ $receivedOnly ? 'section' : 'details' }} id="submission-history" data-submission-history @if ($search !== '' || $submissionType !== '' || $status !== '' || request()->has('page')) open @endif class="group/history">
            <{{ $receivedOnly ? 'div' : 'summary' }} class="mb-3 flex min-h-14 cursor-pointer list-none flex-wrap items-center justify-between gap-3 rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400 sm:flex-nowrap [&::-webkit-details-marker]:hidden">
                <div class="border-l-4 border-brand pl-3 dark:border-red-400">
                    <h3 id="proposal-submission-records-heading" class="text-xl font-bold text-gray-900 dark:text-white">{{ $receivedOnly ? 'Received packages' : 'Submission history' }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">All submitted versions, newest first.</p>
                </div>
                <span class="flex shrink-0 items-center gap-3 text-sm tabular-nums text-gray-500 dark:text-slate-400">
                    {{ $submissions->total() }} submissions
                    @if (! $receivedOnly)<span class="rounded-lg border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"><span class="group-open/history:hidden">Show history</span><span class="hidden group-open/history:inline">Hide history</span></span>@endif
                </span>
            </{{ $receivedOnly ? 'div' : 'summary' }}>
            <div data-submission-history-layout="rows" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1fr)_200px_170px_130px_196px] items-center gap-4 border-b border-brand bg-brand px-4 py-3 text-sm font-semibold text-white dark:border-red-900 xl:grid">
                    <span>Proposal and faculty</span><span>Current review stage</span><span>Package</span><span>Received</span><span class="text-right">Action</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($submissions as $submission)
                        @php
                            $isRevision = $submission->submission_type === 'revision';
                            $fileCount = $submission->package_files_count ?: ($submission->file_path ? 1 : 0);
                            $historyStatusLabel = $submission->topic->researchHeadQueueStatusLabel($submission->topic->latestVersion);
                        @endphp
                        <article data-submission-id="{{ $submission->id }}" class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-3 border-l-2 border-l-transparent bg-white px-4 py-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/50 xl:grid-cols-[minmax(0,1fr)_200px_170px_130px_196px] xl:items-center xl:gap-4">
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <h4 class="break-words text-base font-semibold leading-6 text-gray-900 dark:text-white">{{ $submission->title }}</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $submission->topic->user->name }}</p>
                                <details class="group/package mt-1 text-sm text-gray-500 dark:text-slate-400">
                                    <summary class="inline-flex min-h-8 cursor-pointer list-none items-center gap-2 rounded font-medium hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-gray-500 dark:hover:text-white [&::-webkit-details-marker]:hidden"><span class="group-open/package:hidden" aria-hidden="true">+</span><span class="hidden group-open/package:inline" aria-hidden="true">&minus;</span>Package details</summary>
                                    <div class="space-y-1 py-2 leading-6">
                                        <p class="break-all">{{ $submission->topic->user->email }}</p>
                                        <p>{{ $submission->topic->researchCall?->title ?? 'Research call unavailable' }}@if ($submission->topic->researchCall?->academic_year) · AY {{ $submission->topic->researchCall->academic_year }}@endif</p>
                                        <p>Submitted by {{ $submission->submitter?->name ?? 'Former user' }}</p>
                                        @if ($submission->change_summary)
                                            <p><span class="font-semibold">Changes:</span> {{ $submission->change_summary }}</p>
                                        @endif
                                    </div>
                                </details>
                            </div>
                            <div class="col-span-2 min-w-0 xl:col-span-1">
                                <span class="sr-only">Current review stage:</span>
                                <span data-proposal-history-status-label="{{ $historyStatusLabel }}" class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-sm font-medium leading-5 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $historyStatusLabel }}</span>
                            </div>
                            <div class="col-span-2 min-w-0 text-sm text-gray-500 dark:text-slate-400 xl:col-span-1">
                                <span class="sr-only">Package:</span>
                                <p class="font-medium text-gray-700 dark:text-slate-200">{{ $isRevision ? 'Revision' : 'Initial submission' }} · Version {{ $submission->version_number }}</p>
                                <p class="mt-1">{{ $fileCount }} {{ Str::plural('package file', $fileCount) }}</p>
                            </div>
                            <div class="col-span-2 text-sm text-gray-500 dark:text-slate-400 xl:col-span-1">
                                <span class="sr-only">Received:</span>
                                <time datetime="{{ $submission->created_at->toIso8601String() }}" class="whitespace-nowrap">{{ $submission->created_at->format('M j, Y') }}</time>
                                <p class="mt-1">{{ $submission->created_at->format('g:i A') }}</p>
                            </div>
                            <div class="col-span-2 text-right xl:col-span-1">
                                <a href="{{ route('topics.show', $submission->topic) }}#version-history" aria-label="View history for {{ $submission->title }}, version {{ $submission->version_number }}" class="inline-flex min-h-11 w-48 whitespace-nowrap items-center justify-center gap-2 rounded-lg bg-brand px-4 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400">View history <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @empty
                        <div class="px-4 py-8 text-center"><h4 class="text-base font-semibold text-gray-900 dark:text-white">No proposal submissions found</h4><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Try changing the search or filters.</p></div>
                    @endforelse
                </div>
            </div>
            @if ($submissions->hasPages())
                <div class="mt-3">{{ $submissions->links() }}</div>
            @endif
        </{{ $receivedOnly ? 'section' : 'details' }}>
    </div>
</x-app-layout>
