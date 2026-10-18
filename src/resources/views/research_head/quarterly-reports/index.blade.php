@php
    $root = route('research_head.quarterly-reports.index');
    $yearUrl = route('research_head.quarterly-reports.index', ['year' => $year]);
    $collegeUrl = route('research_head.quarterly-reports.index', ['year' => $year, 'college' => $college]);
    $crumbs = [['label' => 'College reports', 'url' => $browsingYears ? null : $root]];
    if (! $browsingYears) { $crumbs[] = ['label' => $year, 'url' => $college !== '' ? $yearUrl : null]; }
    if ($college !== '') { $crumbs[] = ['label' => array_search($college, \App\Models\User::COLLEGES, true) ?: $college, 'url' => $quarter ? $collegeUrl : null]; }
    if ($quarter) { $crumbs[] = ['label' => 'Q'.$quarter]; }
    $backUrl = $browsingYears ? null : ($quarter ? $collegeUrl : ($college !== '' ? $yearUrl : $root));
    $iconAction = 'workspace-icon focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
    $collegeFolders = collect(\App\Models\User::COLLEGES)->merge($collegeReports->pluck('college', 'college')
        ->reject(fn (string $name): bool => in_array($name, \App\Models\User::COLLEGES, true)));
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="College accomplishment reports" subtitle="Browse college folders to read submissions, check evidence, and request corrections.">
            <x-slot name="actions"><a href="{{ route('research_head.rms-quarterly-reports.index', ['year' => $year]) }}" class="{{ $iconAction }}" aria-label="Open quarterly monitoring" title="Quarterly monitoring"><x-report-library-icon name="calendar" /></a></x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-base text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-base text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section data-report-library x-data="reportLibrary(@js('athena-report-library:'.auth()->id()))" data-view="grid" :data-view="view" data-size="medium" :data-size="size" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <x-report-library-path :crumbs="$crumbs" :back-url="$backUrl">
                <form method="GET" action="{{ $root }}" class="flex items-center gap-2">
                    <input name="year" type="number" min="2000" max="2100" value="{{ $year }}" required aria-label="Reporting year" class="workspace-input w-28">
                    <button type="submit" class="{{ $iconAction }}" aria-label="Open reporting year" title="Open reporting year"><x-report-library-icon name="calendar" /></button>
                </form>
            </x-report-library-path>
            <x-report-library-view-controls class="justify-end border-b border-slate-200 px-4 py-3 dark:border-slate-700" />

            @if ($browsingYears)
                <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="report-library-items min-h-[320px] p-5">
                    @foreach ($years as $folderYear)
                        <x-report-library-folder :href="route('research_head.quarterly-reports.index', ['year' => $folderYear])" :name="(string) $folderYear" description="College submissions" />
                    @endforeach
                </div>
            @elseif ($college === '')
                <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="report-library-items p-5">
                    @foreach ($collegeFolders as $code => $collegeName)
                        @php
                            $count = $collegeReports->where('college', $collegeName)->count();
                        @endphp
                        <x-report-library-folder :href="route('research_head.quarterly-reports.index', ['year' => $year, 'college' => $collegeName])" :name="$code" :description="$collegeName.' · '.$count.' '.Illuminate\Support\Str::plural('report', $count)" />
                    @endforeach
                </div>
                <p class="border-t border-slate-100 px-5 py-4 text-base text-slate-500 dark:border-slate-800 dark:text-slate-400">{{ $collegeReports->count() }} submitted college reports</p>
            @elseif (! $quarter)
                <h2 class="px-5 pt-5 text-xl font-semibold text-slate-950 dark:text-white">{{ $college }}</h2>
                <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="report-library-items p-5">
                    @foreach ([1 => 'January–March', 2 => 'April–June', 3 => 'July–September', 4 => 'October–December'] as $folderQuarter => $months)
                        @php
                            $quarterReport = $collegeReports->where('college', $college)->firstWhere('reporting_quarter', $folderQuarter);
                            $state = $quarterReport ? ($quarterReport->latestSubmission?->returned_at ? 'Returned for correction' : 'Submitted') : 'Not submitted';
                        @endphp
                        <x-report-library-folder :href="route('research_head.quarterly-reports.index', ['year' => $year, 'college' => $college, 'quarter' => $folderQuarter])" :name="'Q'.$folderQuarter" :description="$months.' · '.$state" />
                    @endforeach
                </div>
            @else
                <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="report-library-items report-library-files p-5">
                @forelse ($reports as $report)
                    @php
                        $submission = $report->latestSubmission;
                    @endphp
                    <article aria-labelledby="college-report-{{ $report->id }}" class="report-library-file">
                        <div class="report-library-file-header">
                            <x-document-file-icon class="report-library-file-graphic" />
                            <div class="min-w-0 flex-1">
                                <h2 id="college-report-{{ $report->id }}" class="text-xl font-bold leading-8 text-slate-950 dark:text-white">Q{{ $report->reporting_quarter }} {{ $report->reporting_year }} accomplishment report</h2>
                                <p class="mt-1 text-base leading-7 text-slate-600 dark:text-slate-300">{{ $report->college }}</p>
                                <p class="mt-2 text-base leading-7 text-slate-500 dark:text-slate-400">{{ $submission->submittedBy?->name }} · Version {{ $submission->version_number }}<br>Submitted {{ $submission->submitted_at->format('M j, Y g:i A') }}</p>
                                <p class="mt-3 text-base font-semibold {{ $submission->returned_at ? 'text-amber-800 dark:text-amber-300' : 'text-emerald-800 dark:text-emerald-300' }}">{{ $submission->returned_at ? 'Returned for correction' : 'Submitted' }}</p>
                                @if ($submission->returned_at)<p class="mt-2 whitespace-pre-line text-base leading-7 text-slate-600 dark:text-slate-300">{{ $submission->return_reason }}</p>@endif
                            </div>
                            <div class="report-library-file-actions flex w-full justify-end gap-2 sm:w-auto" role="group" aria-label="Report actions">
                                <a href="{{ route('college-quarterly-submissions.print', $submission) }}" target="_blank" rel="noopener" class="{{ $iconAction }}" aria-label="Preview report" title="Preview report"><x-report-library-icon name="preview" /></a>
                                <a href="{{ route('college-quarterly-submissions.download', $submission) }}" class="{{ $iconAction }}" aria-label="Download report" title="Download report"><x-report-library-icon name="download" /></a>
                            </div>
                        </div>
                        @if ($report->submitted_at !== null && $submission->returned_at === null)
                            <details class="mt-6 border-t border-slate-200 pt-4 dark:border-slate-700">
                                <summary class="disclosure-summary rounded text-base font-semibold text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white"><span class="inline-flex items-center gap-2 align-middle"><x-report-library-icon name="return" />Return for correction</span></summary>
                                <form method="POST" action="{{ route('research_head.quarterly-reports.return', $report) }}" class="mt-3 space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="submission_id" value="{{ $submission->id }}">
                                    <label for="return-reason-{{ $report->id }}" class="block text-base text-slate-700 dark:text-slate-300">What should the coordinator correct?</label>
                                    <textarea id="return-reason-{{ $report->id }}" name="reason" rows="3" maxlength="5000" required class="workspace-input block w-full">{{ (int) old('submission_id') === $submission->id ? old('reason') : '' }}</textarea>
                                    <div class="flex items-start gap-4"><p class="max-w-3xl flex-1 text-base leading-7 text-slate-500 dark:text-slate-400">The submitted version remains in history. Quarterly monitoring excludes this report until the coordinator resubmits it.</p><button type="submit" class="{{ $iconAction }} !bg-brand !text-white" aria-label="Return report for correction" title="Return report for correction"><x-report-library-icon name="return" /></button></div>
                                </form>
                            </details>
                        @endif
                        <details class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
                            <summary class="disclosure-summary rounded text-base font-semibold text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white"><span class="inline-flex items-center gap-2 align-middle"><x-report-library-icon name="history" />Submission history ({{ $report->submissions->count() }})</span></summary>
                            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($report->submissions as $version)
                                    <li class="flex flex-wrap items-center justify-between gap-3 py-4 text-base">
                                        <div><p class="font-semibold text-slate-800 dark:text-slate-200">Version {{ $version->version_number }}{{ $version->id === $submission->id ? ' · Latest' : '' }}</p><p class="mt-1 leading-7 text-slate-500 dark:text-slate-400">{{ $version->submitted_at->format('M j, Y g:i A') }}{{ $version->returned_at ? ' · Returned for correction' : '' }}</p></div>
                                        <div class="flex gap-2">
                                            <a href="{{ route('college-quarterly-submissions.print', $version) }}" target="_blank" rel="noopener" class="{{ $iconAction }}" aria-label="Preview version {{ $version->version_number }}" title="Preview version {{ $version->version_number }}"><x-report-library-icon name="preview" /></a>
                                            <a href="{{ route('college-quarterly-submissions.download', $version) }}" class="{{ $iconAction }}" aria-label="Download version {{ $version->version_number }}" title="Download version {{ $version->version_number }}"><x-report-library-icon name="download" /></a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    </article>
                @empty
                    <div class="col-span-full flex min-h-[300px] flex-col items-center justify-center gap-4 px-6 py-10 text-center"><x-report-library-icon name="folder" /><h2 class="text-xl font-semibold text-slate-900 dark:text-white">No submitted report for Q{{ $quarter }} {{ $year }}</h2><p class="max-w-xl text-base leading-7 text-slate-500 dark:text-slate-400">The coordinator’s report will appear in this folder after submission.</p></div>
                @endforelse
                </div>
                @if ($reports->hasPages())<div class="border-t border-slate-200 p-5 dark:border-slate-700">{{ $reports->links() }}</div>@endif
            @endif
        </section>
    </div>
</x-app-layout>
