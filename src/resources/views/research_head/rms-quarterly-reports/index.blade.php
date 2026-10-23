@php
    $sections = ['presentations' => 'Paper presentations', 'publications' => 'Publications', 'ip_assets' => 'IP assets'];
    $rows = collect($sections)->keys()->flatMap(fn (string $section) => $reportData[$section]);
    $missingCount = $rows->sum(fn (array $row): int => count($row['missing']));
    $restoreRows = old('source_token') === $reportData['source_token'];
    $rowsJson = old('row_details_json');
    $restoredRows = is_string($rowsJson) && strlen($rowsJson) <= 5 * 1024 * 1024 ? json_decode($rowsJson, true, 32) : old('row_details', []);
    $restoredRows = is_array($restoredRows) ? $restoredRows : [];
    $inputClass = 'block min-h-11 w-full rounded-lg border-gray-300 text-base focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
    $iconAction = 'workspace-icon focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
    $actionClass = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
    $errorSection = collect(['presentation' => 'presentations', 'publication' => 'publications', 'ip' => 'ip_assets'])
        ->first(fn (string $section, string $prefix): bool => $errors->has('row_details.'.$prefix.'-*.*'));
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'RMS quarterly report · Q'.$quarter.' '.$year" subtitle="Review submitted college outputs, complete the report details, and generate the campus Excel workbook.">
            <x-slot name="actions">
                <a href="{{ route('research_head.rms-quarterly-reports.index') }}" class="{{ $actionClass }} text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"><x-report-library-icon name="back" class="h-5 w-5" />All RMS reports</a>
                <a href="{{ route('research_head.quarterly-reports.index', ['year' => $year]) }}" class="{{ $actionClass }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"><x-report-library-icon name="college" class="h-5 w-5" />College reports</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5" data-rms-quarterly-reports>
        @if (session('status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-base text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-base text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p class="font-semibold">Check the report before saving.</p>
                <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section aria-labelledby="rms-workbook-heading" class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6 dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div class="min-w-0">
                    <h2 id="rms-workbook-heading" class="text-xl font-bold text-slate-950 dark:text-white">{{ $period['start']->format('F') }}–{{ $period['end']->format('F Y') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $report ? 'Draft saved '.$report->updated_at->format('M j, Y g:i A') : 'Draft not yet saved' }}</p>
                </div>
                <div class="flex w-full flex-wrap gap-2 sm:w-auto" role="group" aria-label="Report actions">
                    <button type="submit" form="rms-report-form" name="intent" value="save" class="{{ $actionClass }} border border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"><x-report-library-icon name="save" class="h-5 w-5" />Save draft</button>
                    <button type="submit" form="rms-report-form" name="intent" value="download" @disabled(! $reportStatus['ready']) aria-describedby="rms-export-readiness" class="{{ $actionClass }} bg-brand text-white hover:bg-brand-soft disabled:cursor-not-allowed disabled:opacity-50"><x-report-library-icon name="export" class="h-5 w-5" />Save &amp; download Excel</button>
                </div>
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-slate-100 pt-5 sm:grid-cols-4 dark:border-slate-800">
                @foreach (['College reports included' => $reportStatus['submitted_count'], 'Output records' => $rows->count(), 'Fields to complete' => $missingCount, 'Colleges pending' => count($reportStatus['missing_colleges'])] as $label => $count)
                    <div><dt class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950 dark:text-white">{{ $count }}</dd></div>
                @endforeach
            </dl>
            <div class="mt-5 space-y-3 border-t border-slate-100 pt-5 dark:border-slate-800" data-rms-readiness="{{ $reportStatus['ready'] ? 'ready' : 'waiting' }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-slate-900 dark:text-white">{{ $reportStatus['submitted_count'] }} of {{ $reportStatus['total_count'] }} college reports received</p>
                    @if ($reportStatus['outdated'])
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Updated since last download</p>
                    @endif
                </div>
                <p id="rms-export-readiness" class="text-sm font-medium {{ $reportStatus['ready'] ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-800 dark:text-amber-300' }}">{{ $reportStatus['ready'] ? 'All college reports received. Excel download is available.' : 'Waiting for all colleges to submit. Returned reports must be resubmitted before downloading. You can still save a draft.' }}</p>
                @if ($reportStatus['downloaded_at'])
                    <p class="text-sm text-slate-500 dark:text-slate-400">Last downloaded <time datetime="{{ $reportStatus['downloaded_at']->toISOString() }}">{{ $reportStatus['downloaded_at']->format('M j, Y · g:i A') }}</time></p>
                @endif
                <ul class="grid gap-x-6 gap-y-2 sm:grid-cols-2 xl:grid-cols-3" aria-label="College submission status">
                    @foreach ($reportStatus['colleges'] as $college => $collegeStatus)
                        <li class="flex items-start justify-between gap-3 text-sm">
                            <span class="text-slate-600 dark:text-slate-300">{{ $college }}</span>
                            <span class="shrink-0 rounded-md px-2 py-1 text-xs font-medium {{ match ($collegeStatus) { 'submitted' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300', 'returned' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300', default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' } }}">{{ match ($collegeStatus) { 'submitted' => 'Received', 'returned' => 'Returned', default => 'Pending' } }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="mt-5 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">One workbook for Q{{ $quarter }} {{ $year }}, with separate sheets for paper presentations, publications, and IP assets. Receiving all reports unlocks Excel; review report fields separately. Missing details stay blank.</p>
        </section>

        <details class="dashboard-panel p-5" aria-labelledby="rms-source-heading">
            <summary class="disclosure-summary rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                <span id="rms-source-heading" class="font-semibold text-slate-950 dark:text-white">Source college reports <span class="font-normal text-slate-500 dark:text-slate-400">({{ count($reportData['sources']) }} included · {{ count($reportData['missing_colleges']) }} pending)</span></span>
            </summary>
            <p class="mt-3 max-w-4xl text-base leading-7 text-slate-600 dark:text-slate-400">Records come from submitted coordinator reports. Attached activity proof carries into RMS. Review authors, funding and SDGs before using the report. Blank details stay blank in Excel; you can save an incomplete draft.</p>
            @if ($reportData['sources'] !== [])
                <details class="mt-4 border-t border-gray-200 pt-3 dark:border-slate-800">
                    <summary class="disclosure-summary text-base font-semibold text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white">Included college reports ({{ count($reportData['sources']) }})</summary>
                    <ul class="mt-3 divide-y divide-gray-100 dark:divide-slate-800">
                        @foreach ($reportData['sources'] as $source)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-base">
                                <span class="text-gray-700 dark:text-slate-300">{{ $source['college'] }} · Version {{ $source['version'] }}</span>
                                @if ($source['submission_id'])<a href="{{ route('research_head.quarterly-reports.index', ['year' => $year, 'college' => $source['college'], 'quarter' => $quarter]) }}" class="{{ $iconAction }}" aria-label="Open submitted report for {{ $source['college'] }}" title="Open submitted college report"><x-report-library-icon name="open" /></a>@endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
            @if ($reportData['missing_colleges'] !== [])
                <details class="mt-4 border-t border-gray-200 pt-3 dark:border-slate-800" @if ($reportData['sources'] === []) open @endif>
                    <summary class="disclosure-summary text-base font-semibold text-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-amber-300">Awaiting college reports ({{ count($reportData['missing_colleges']) }})</summary>
                    <p class="mt-2 text-base text-slate-600 dark:text-slate-400">Drafts and reports returned for correction are excluded until submitted.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-base text-slate-600 dark:text-slate-400">@foreach ($reportData['missing_colleges'] as $college)<li>{{ $college }}</li>@endforeach</ul>
                </details>
            @endif
        </details>

        {{-- Server validation reopens the section containing an invalid field. --}}
        <form method="POST" action="{{ route('research_head.rms-quarterly-reports.store') }}" novalidate class="space-y-5" id="rms-report-form" x-data="rmsQuarterlyReportForm" x-init="openSection(@js($errorSection ?? 'presentations'))" @formdata="serialize($event)"
            data-assistant-report-type="rms-quarterly" data-assistant-report-key="{{ $report ? 'rms-quarterly:'.$report->id : '' }}" data-assistant-report-save-mode="manual">
            @csrf
            <input type="hidden" name="reporting_year" value="{{ $year }}">
            <input type="hidden" name="reporting_quarter" value="{{ $quarter }}">
            <input type="hidden" name="source_token" value="{{ $reportData['source_token'] }}">

            <nav data-rms-section-navigation aria-label="Report sections" class="flex flex-wrap gap-2">
                @foreach ($sections as $section => $label)
                    <button type="button" @click="openSection('{{ $section }}')" aria-controls="rms-{{ $section }}" :aria-pressed="section === '{{ $section }}'" :class="section === '{{ $section }}' ? 'border-brand bg-brand-wash text-brand dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'" class="{{ $actionClass }} border">
                        <x-report-library-icon :name="match ($section) { 'presentations' => 'presentation', 'publications' => 'publication', default => 'ip' }" class="h-5 w-5" />
                        {{ $label }}<span class="tabular-nums">({{ count($reportData[$section]) }})</span>
                    </button>
                @endforeach
            </nav>

            <x-assistant-report-editor :context="['report_type' => 'rms-quarterly', 'report_id' => $report?->id]" />

            <details class="dashboard-panel p-5" @if ($errors->has('campus') || $errors->has('signatories.*')) open @endif>
                <summary class="disclosure-summary font-bold text-gray-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white">Report details and signatories</summary>
                <div class="mt-4 space-y-5">
                    <div class="max-w-lg">
                        <x-input-label class="!text-base" for="rms-campus" value="Campus" />
                        <input id="rms-campus" name="campus" value="{{ old('campus', $report?->campus ?? 'ARASOF-Nasugbu') }}" required maxlength="255" class="{{ $inputClass }} mt-1">
                        <x-input-error :messages="$errors->get('campus')" class="mt-1 !text-base" />
                    </div>
                    @foreach (['prepared' => 'Prepared by', 'reviewed' => 'Reviewed by', 'approved' => 'Approved by'] as $role => $label)
                        <fieldset class="grid gap-3 sm:grid-cols-3">
                            <legend class="mb-2 text-base font-semibold text-gray-800 dark:text-slate-200">{{ $label }}</legend>
                            @foreach (['name' => 'Name', 'position' => 'Position', 'office' => 'Office'] as $field => $fieldLabel)
                                <div>
                                    <label for="rms-{{ $role }}-{{ $field }}" class="block text-base text-slate-600 dark:text-slate-400">{{ $fieldLabel }}</label>
                                    <input id="rms-{{ $role }}-{{ $field }}" name="signatories[{{ $role }}][{{ $field }}]" value="{{ old('signatories.'.$role.'.'.$field, $report?->signatories[$role][$field] ?? '') }}" maxlength="255" class="{{ $inputClass }} mt-1">
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
            </details>



            @foreach ($sections as $section => $label)
                <section id="rms-{{ $section }}" x-cloak x-show="section === '{{ $section }}'" class="dashboard-panel scroll-mt-6 overflow-hidden" aria-labelledby="rms-{{ $section }}-heading">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-slate-800"><h2 id="rms-{{ $section }}-heading" class="text-lg font-bold text-gray-950 dark:text-white">{{ $label }} <span class="font-normal text-slate-500 dark:text-slate-400">({{ count($reportData[$section]) }})</span></h2></div>
                    <div class="divide-y divide-slate-200 px-5 dark:divide-slate-800">
                        @forelse ($reportData[$section] as $row)
                            @php
                                $key = $row['key'];
                                $title = $row['values'][match ($section) {'presentations' => 'presentation_title', 'publications' => 'article_title', default => 'title'}] ?: 'Untitled record';
                            @endphp
                            <details class="py-4" @if ($errors->has('row_details.'.$key.'.*') || $errors->has('row_details.'.$key)) open @endif>
                                <summary class="disclosure-summary rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                    <span class="min-w-0 align-top">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $loop->iteration }}. {{ $title }}</span>
                                        <span class="mt-1 block text-base text-slate-500 dark:text-slate-400">{{ $row['college'] }} · {{ count($row['missing']) }} fields to complete</span>
                                    </span>
                                </summary>
                                <div class="mt-5 space-y-4">
                                    @if ($row['missing'] !== [])<p class="text-base leading-6 text-amber-800 dark:text-amber-300">To complete: {{ implode(', ', $row['missing']) }}.</p>@endif
                                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach (\App\Services\RmsQuarterlyReportData::FIELDS[$section] as $field => $definition)
                                            @php
                                                $path = 'row_details.'.$key.'.'.$field;
                                                $value = $restoreRows ? data_get($restoredRows, $key.'.'.$field, $row['values'][$field]) : $row['values'][$field];
                                                $id = $key.'-'.$field;
                                            @endphp
                                            <div class="min-w-0">
                                                <label for="{{ $id }}" class="mb-1 block text-base font-medium text-gray-700 dark:text-slate-300">{{ $definition['label'] }}</label>
                                                @if ($field === 'college')
                                                    <input id="{{ $id }}" value="{{ $row['college'] }}" readonly class="{{ $inputClass }} bg-gray-50 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                @elseif ($definition['type'] === 'select')
                                                    <select id="{{ $id }}" name="row_details[{{ $key }}][{{ $field }}]" class="{{ $inputClass }}" @if ($errors->has($path)) aria-invalid="true" @endif>
                                                        <option value="">Select when known</option>
                                                        @foreach ($definition['options'] as $option)<option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>@endforeach
                                                    </select>
                                                @elseif (in_array($field, ['presentation_title', 'article_title', 'project_title', 'authors', 'presenter', 'inventors', 'remarks'], true))
                                                    <textarea id="{{ $id }}" name="row_details[{{ $key }}][{{ $field }}]" rows="2" maxlength="2000" class="{{ $inputClass }}" @if ($errors->has($path)) aria-invalid="true" @endif>{{ $value }}</textarea>
                                                @else
                                                    <input id="{{ $id }}" type="{{ in_array($definition['type'], ['date', 'url'], true) ? $definition['type'] : 'text' }}" name="row_details[{{ $key }}][{{ $field }}]" value="{{ $value }}" maxlength="{{ $definition['type'] === 'url' ? 2048 : ($field === 'sdgs' ? 255 : 2000) }}" class="{{ $inputClass }}" @if ($definition['type'] === 'date') min="{{ $period['start']->toDateString() }}" max="{{ $period['end']->toDateString() }}" @endif @if ($field === 'sdgs') placeholder="e.g. 3, 4, 9" aria-describedby="{{ $id }}-help" @endif @if ($errors->has($path)) aria-invalid="true" @endif>
                                                @endif
                                                @if ($field === 'sdgs')<p id="{{ $id }}-help" class="mt-1 text-base text-slate-500 dark:text-slate-400">SDG numbers 1–17, separated by commas.</p>
                                                @elseif ($field === 'project_status')<p class="mt-1 text-base text-slate-500 dark:text-slate-400">Leave blank for non-funded research.</p>@endif
                                                <x-input-error :messages="$errors->get($path)" class="mt-1 !text-base" />
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </details>
                        @empty
                            <div class="flex flex-col items-start gap-3 py-8">
                                <p class="text-base text-slate-500 dark:text-slate-400">No {{ strtolower($label) }} in the submitted college reports for this quarter.</p>
                                <a href="{{ route('research_head.quarterly-reports.index', ['year' => $year]) }}" class="{{ $actionClass }} border border-slate-200 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Review college submissions<x-report-library-icon name="open" class="h-4 w-4" /></a>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach

            <input type="hidden" name="form_complete" value="1">
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 py-4 dark:border-slate-800">
                <p class="text-base text-slate-500 dark:text-slate-400">{{ $missingCount }} fields to complete. You can save a draft at any time. Excel download requires all college reports.</p>
                <p class="text-base text-slate-500 dark:text-slate-400">Review each section before sending the report.</p>
            </div>
        </form>
    </div>
</x-app-layout>
