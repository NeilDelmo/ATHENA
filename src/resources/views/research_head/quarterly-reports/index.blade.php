<x-app-layout>
    <x-slot name="header">
        <x-page-header title="College accomplishment reports" subtitle="Read submitted coordinator reports and return a report when corrections are needed.">
            <x-slot name="actions"><a href="{{ route('research_head.rms-quarterly-reports.index', ['year' => $year]) }}" class="dashboard-action">Quarterly monitoring</a></x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <form method="GET" action="{{ route('research_head.quarterly-reports.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="college-report-year" value="Reporting year" />
                <input id="college-report-year" name="year" type="number" min="2000" max="2100" value="{{ $year }}" required class="mt-1 w-32 rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            </div>
            <button type="submit" class="dashboard-action">View year</button>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $reports->total() }} submitted college reports</p>
        </form>

        <div class="space-y-4">
            @forelse ($reports as $report)
                @php($submission = $report->latestSubmission)
                <article class="dashboard-panel overflow-hidden" aria-labelledby="college-report-{{ $report->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-4 p-5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-brand dark:text-rose-300">Q{{ $report->reporting_quarter }} · {{ $report->reporting_year }}</p>
                            <h2 id="college-report-{{ $report->id }}" class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $report->college }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $submission->submittedBy?->name }} · Version {{ $submission->version_number }} · Submitted {{ $submission->submitted_at->format('M j, Y g:i A') }}</p>
                            <p class="mt-2 text-sm font-semibold {{ $submission->returned_at ? 'text-amber-800 dark:text-amber-300' : 'text-emerald-800 dark:text-emerald-300' }}">{{ $submission->returned_at ? 'Returned for correction' : 'Submitted' }}</p>
                            @if ($submission->returned_at)
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $submission->return_reason }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('college-quarterly-submissions.print', $submission) }}" target="_blank" rel="noopener" class="dashboard-action">Print preview</a>
                            <a href="{{ route('college-quarterly-submissions.download', $submission) }}" class="dashboard-action">Download Word</a>
                        </div>
                    </div>

                    @if ($report->submitted_at !== null && $submission->returned_at === null)
                        <details class="border-t border-gray-200 px-5 py-4 dark:border-slate-800">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white">Return for correction</summary>
                            <form method="POST" action="{{ route('research_head.quarterly-reports.return', $report) }}" class="mt-3 space-y-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="submission_id" value="{{ $submission->id }}">
                                <label for="return-reason-{{ $report->id }}" class="block text-sm text-gray-700 dark:text-slate-300">What should the coordinator correct?</label>
                                <textarea id="return-reason-{{ $report->id }}" name="reason" rows="3" maxlength="5000" required class="block w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ (int) old('submission_id') === $submission->id ? old('reason') : '' }}</textarea>
                                <p class="text-sm text-slate-500 dark:text-slate-400">The submitted version remains available in history. The report is excluded from quarterly monitoring until the coordinator resubmits it.</p>
                                <x-primary-button>Return report</x-primary-button>
                            </form>
                        </details>
                    @endif

                    <details class="border-t border-gray-200 px-5 py-4 dark:border-slate-800">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white">Submission history ({{ $report->submissions->count() }})</summary>
                        <ul class="mt-3 divide-y divide-gray-100 dark:divide-slate-800">
                            @foreach ($report->submissions as $version)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                    <div class="text-gray-700 dark:text-slate-300">
                                        <p class="font-semibold">Version {{ $version->version_number }}{{ $version->id === $submission->id ? ' · Latest' : '' }}</p>
                                        <p class="mt-1 text-slate-500 dark:text-slate-400">{{ $version->submitted_at->format('M j, Y g:i A') }}{{ $version->returned_at ? ' · Returned for correction' : '' }}</p>
                                    </div>
                                    <div class="flex flex-wrap gap-3">
                                        <a href="{{ route('college-quarterly-submissions.print', $version) }}" target="_blank" rel="noopener" class="font-semibold text-brand underline dark:text-rose-300">Print preview<span class="sr-only"> version {{ $version->version_number }}</span></a>
                                        <a href="{{ route('college-quarterly-submissions.download', $version) }}" class="font-semibold text-brand underline dark:text-rose-300">Download Word<span class="sr-only"> version {{ $version->version_number }}</span></a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                </article>
            @empty
                <div class="dashboard-panel px-5 py-10 text-center">
                    <h2 class="font-semibold text-gray-900 dark:text-white">No submitted college reports for {{ $year }}</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Coordinator reports appear here after submission.</p>
                </div>
            @endforelse
        </div>
        {{ $reports->links() }}
    </div>
</x-app-layout>
