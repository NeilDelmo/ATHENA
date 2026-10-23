<x-app-layout>
    <x-slot name="header">
        <x-page-header title="RMS quarterly reports" subtitle="Combine submitted college reports and generate the campus RMS Excel workbook.">
            <x-slot name="actions">
                <a wire:navigate href="{{ route('research_head.quarterly-reports.index') }}" class="dashboard-action justify-center">
                    <x-report-library-icon name="college" class="!h-5 !w-5" />
                    College reports
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6" data-rms-report-overview>
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <div class="max-w-2xl space-y-1 sm:basis-64 sm:flex-1">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Reports by quarter</h2>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Each quarter has its own report and Excel workbook. All colleges must submit before downloading. Downloads use saved details and current submissions; blank fields stay blank.</p>
            </div>
            <form method="GET" action="{{ route('research_head.rms-quarterly-reports.index') }}" class="flex shrink-0 flex-col gap-2">
                <label for="rms-report-year" class="text-sm font-medium text-slate-700 dark:text-slate-300">Reporting year</label>
                <select id="rms-report-year" name="year" class="workspace-input w-36" onchange="this.form.requestSubmit()">
                    <option value="" @selected($selectedYear === null)>All years</option>
                    @foreach ($years as $reportYear)
                        <option value="{{ $reportYear }}" @selected($selectedYear === $reportYear)>{{ $reportYear }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @foreach ($reportYears as $reportYear)
            <section aria-labelledby="rms-year-{{ $reportYear }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900" data-rms-report-year="{{ $reportYear }}">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 id="rms-year-{{ $reportYear }}" class="text-lg font-semibold text-slate-900 dark:text-white">{{ $reportYear }}</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">4 reporting periods</p>
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ([1 => 'January–March', 2 => 'April–June', 3 => 'July–September', 4 => 'October–December'] as $reportQuarter => $months)
                        @php
                            $periodKey = $reportYear.'-'.$reportQuarter;
                            $draft = $drafts->get($periodKey);
                            $status = $statuses[$periodKey];
                        @endphp
                        <article class="flex flex-col gap-4 p-5 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between" data-rms-report-period="{{ $reportYear }}-Q{{ $reportQuarter }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-6">
                                <div class="space-y-1 sm:w-52">
                                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Q{{ $reportQuarter }} <span class="font-normal text-slate-500 dark:text-slate-400">{{ $months }}</span></h3>
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $status['submitted_count'] }} of {{ $status['total_count'] }} college reports received</p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm font-medium {{ $draft ? 'text-brand dark:text-rose-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $draft ? 'Draft saved' : 'Not started' }}</p>
                                    @if ($draft)
                                        <p class="text-sm text-slate-500 dark:text-slate-400">Saved <time datetime="{{ $draft->updated_at->toISOString() }}">{{ $draft->updated_at->format('M j, Y · g:i A') }}</time></p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a wire:navigate href="{{ route('research_head.rms-quarterly-reports.index', ['year' => $reportYear, 'quarter' => $reportQuarter]) }}" class="dashboard-action">
                                    Open report<span class="sr-only"> for {{ $reportYear }} Q{{ $reportQuarter }}</span>
                                    <x-report-library-icon name="open" class="!h-4 !w-4" />
                                </a>
                                @if ($status['ready'])
                                    <a href="{{ route('research_head.rms-quarterly-reports.download', ['year' => $reportYear, 'quarter' => $reportQuarter]) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                                        <x-report-library-icon name="download" class="!h-4 !w-4" />
                                        Download Excel<span class="sr-only"> for {{ $reportYear }} Q{{ $reportQuarter }}</span>
                                    </a>
                                @else
                                    <button type="button" disabled aria-describedby="rms-readiness-{{ $periodKey }}" class="inline-flex min-h-11 cursor-not-allowed items-center justify-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">
                                        <x-report-library-icon name="download" class="!h-4 !w-4" />
                                        Download Excel<span class="sr-only"> for {{ $reportYear }} Q{{ $reportQuarter }}</span>
                                    </button>
                                @endif
                            </div>
                            <div class="w-full space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800" data-rms-readiness="{{ $status['ready'] ? 'ready' : 'waiting' }}">
                                <p id="rms-readiness-{{ $periodKey }}" class="text-sm font-medium {{ $status['ready'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300' }}">{{ $status['ready'] ? 'All college reports received. Excel download is available.' : 'Waiting for all colleges to submit. Returned reports must be resubmitted before downloading.' }}</p>
                                @if ($status['outdated'])
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Updated since last download</p>
                                @endif
                                @if ($status['downloaded_at'])
                                    <p class="text-sm text-slate-500 dark:text-slate-400">Last downloaded <time datetime="{{ $status['downloaded_at']->toISOString() }}">{{ $status['downloaded_at']->format('M j, Y · g:i A') }}</time></p>
                                @endif
                                <ul class="flex flex-wrap gap-2" aria-label="College submission status for {{ $reportYear }} Q{{ $reportQuarter }}">
                                    @foreach ($status['colleges'] as $college => $collegeStatus)
                                        <li class="rounded-md px-2.5 py-1 text-xs font-medium {{ match ($collegeStatus) { 'submitted' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300', 'returned' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300', default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' } }}">
                                            <abbr title="{{ $college }}" class="no-underline">{{ array_search($college, \App\Models\User::COLLEGES, true) ?: $college }}</abbr> · {{ match ($collegeStatus) { 'submitted' => 'Received', 'returned' => 'Returned', default => 'Pending' } }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
