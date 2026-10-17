@php
    $sections = ['presentations' => 'Paper presentations', 'publications' => 'Publications', 'ip_assets' => 'IP assets'];
    $rows = collect($sections)->keys()->flatMap(fn (string $section) => $reportData[$section]);
    $missingCount = $rows->sum(fn (array $row): int => count($row['missing']));
    $restoreRows = old('source_token') === $reportData['source_token'];
    $inputClass = 'block w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Quarterly Monitoring Report" subtitle="Consolidate college accomplishment reports, complete the RMS details, and export the three-sheet Excel report.">
            <x-slot name="actions"><a href="{{ route('research_head.quarterly-reports.index', ['year' => $year]) }}" class="dashboard-action">College reports</a></x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5" data-rms-quarterly-reports>
        @if (session('status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p class="font-semibold">Check the report before saving.</p>
                <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="GET" action="{{ route('research_head.rms-quarterly-reports.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="rms-year" value="Reporting year" />
                <input id="rms-year" name="year" type="number" min="2000" max="2100" value="{{ $year }}" required class="mt-1 w-32 rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            </div>
            <div>
                <x-input-label for="rms-quarter" value="Quarter" />
                <select id="rms-quarter" name="quarter" class="mt-1 rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                    @foreach ([1 => 'January–March', 2 => 'April–June', 3 => 'July–September', 4 => 'October–December'] as $value => $label)
                        <option value="{{ $value }}" @selected($quarter === $value)>Q{{ $value }} · {{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="dashboard-action">Open quarter</button>
        </form>

        <section class="dashboard-panel space-y-4 p-5" aria-labelledby="rms-source-heading">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 id="rms-source-heading" class="text-xl font-bold text-gray-950 dark:text-white">{{ $period['start']->format('F') }}–{{ $period['end']->format('F Y') }} · Q{{ $quarter }}</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ count($reportData['sources']) }} college reports · {{ $rows->count() }} records · {{ $missingCount }} fields to complete</p>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $report ? 'Draft saved '.$report->updated_at->format('M j, Y g:i A') : 'Draft not yet saved' }}</p>
            </div>
            <p class="max-w-4xl text-sm leading-6 text-slate-600 dark:text-slate-400">Records come from submitted coordinator reports. Review authors, funding, SDGs, and supporting links before using the report. Blank details stay blank in Excel; you can save an incomplete draft.</p>
            @if ($reportData['sources'] !== [])
                <details class="border-t border-gray-200 pt-3 dark:border-slate-800">
                    <summary class="cursor-pointer text-sm font-semibold text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white">Included college reports ({{ count($reportData['sources']) }})</summary>
                    <ul class="mt-3 divide-y divide-gray-100 dark:divide-slate-800">
                        @foreach ($reportData['sources'] as $source)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                                <span class="text-gray-700 dark:text-slate-300">{{ $source['college'] }} · Version {{ $source['version'] }}</span>
                                @if ($source['submission_id'])<a href="{{ route('college-quarterly-submissions.print', $source['submission_id']) }}" target="_blank" rel="noopener" class="font-semibold text-brand underline dark:text-rose-300">View submitted report<span class="sr-only"> for {{ $source['college'] }}</span></a>@endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
            @if ($reportData['missing_colleges'] !== [])
                <details class="border-t border-gray-200 pt-3 dark:border-slate-800" @if ($reportData['sources'] === []) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-amber-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-amber-300">Awaiting college reports ({{ count($reportData['missing_colleges']) }})</summary>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Drafts and reports returned for correction are excluded until submitted.</p>
                    <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-slate-600 dark:text-slate-400">@foreach ($reportData['missing_colleges'] as $college)<li>{{ $college }}</li>@endforeach</ul>
                </details>
            @endif
        </section>

        <nav aria-label="Report sections" class="flex flex-wrap gap-2">
            @foreach ($sections as $section => $label)<a href="#rms-{{ $section }}" class="dashboard-action">{{ $label }} <span class="text-slate-500 dark:text-slate-400">{{ count($reportData[$section]) }}</span></a>@endforeach
        </nav>

        <form method="POST" action="{{ route('research_head.rms-quarterly-reports.store') }}" class="space-y-5" id="rms-report-form" x-data="rmsQuarterlyReportForm" @formdata="serialize($event)"
            data-assistant-report-type="rms-quarterly" data-assistant-report-key="{{ $report ? 'rms-quarterly:'.$report->id : '' }}" data-assistant-report-save-mode="manual">
            @csrf
            <input type="hidden" name="reporting_year" value="{{ $year }}">
            <input type="hidden" name="reporting_quarter" value="{{ $quarter }}">
            <input type="hidden" name="source_token" value="{{ $reportData['source_token'] }}">

            <x-assistant-report-editor :context="['report_type' => 'rms-quarterly', 'report_id' => $report?->id]" />

            <details class="dashboard-panel p-5" @if ($errors->has('campus') || $errors->has('signatories.*')) open @endif>
                <summary class="cursor-pointer font-bold text-gray-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-white">Report details and signatories</summary>
                <div class="mt-4 space-y-5">
                    <div class="max-w-lg">
                        <x-input-label for="rms-campus" value="Campus" />
                        <input id="rms-campus" name="campus" value="{{ old('campus', $report?->campus ?? 'ARASOF-Nasugbu') }}" required maxlength="255" class="{{ $inputClass }} mt-1">
                        <x-input-error :messages="$errors->get('campus')" class="mt-1" />
                    </div>
                    @foreach (['prepared' => 'Prepared by', 'reviewed' => 'Reviewed by', 'approved' => 'Approved by'] as $role => $label)
                        <fieldset class="grid gap-3 sm:grid-cols-3">
                            <legend class="mb-2 text-sm font-semibold text-gray-800 dark:text-slate-200">{{ $label }}</legend>
                            @foreach (['name' => 'Name', 'position' => 'Position', 'office' => 'Office'] as $field => $fieldLabel)
                                <div>
                                    <label for="rms-{{ $role }}-{{ $field }}" class="block text-sm text-slate-600 dark:text-slate-400">{{ $fieldLabel }}</label>
                                    <input id="rms-{{ $role }}-{{ $field }}" name="signatories[{{ $role }}][{{ $field }}]" value="{{ old('signatories.'.$role.'.'.$field, $report?->signatories[$role][$field] ?? '') }}" maxlength="255" class="{{ $inputClass }} mt-1">
                                </div>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
            </details>

            @foreach ($sections as $section => $label)
                <section id="rms-{{ $section }}" class="dashboard-panel scroll-mt-6 overflow-hidden" aria-labelledby="rms-{{ $section }}-heading">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-slate-800"><h2 id="rms-{{ $section }}-heading" class="text-lg font-bold text-gray-950 dark:text-white">{{ $label }} <span class="font-normal text-slate-500 dark:text-slate-400">({{ count($reportData[$section]) }})</span></h2></div>
                    <div class="divide-y divide-gray-200 dark:divide-slate-800">
                        @forelse ($reportData[$section] as $row)
                            @php
                                $key = $row['key'];
                                $title = $row['values'][match ($section) {'presentations' => 'presentation_title', 'publications' => 'article_title', default => 'title'}] ?: 'Untitled record';
                            @endphp
                            <details class="group px-5 py-4" @if ($errors->has('row_details.'.$key.'.*') || $errors->has('row_details.'.$key)) open @endif>
                                <summary class="cursor-pointer rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                    <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $loop->iteration }}. {{ $title }}</span>
                                    <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">{{ $row['college'] }} · {{ count($row['missing']) }} fields to complete</span>
                                </summary>
                                <div class="mt-5 space-y-4">
                                    @if ($row['missing'] !== [])<p class="text-sm leading-6 text-amber-800 dark:text-amber-300">To complete: {{ implode(', ', $row['missing']) }}.</p>@endif
                                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach (\App\Services\RmsQuarterlyReportData::FIELDS[$section] as $field => $definition)
                                            @php
                                                $path = 'row_details.'.$key.'.'.$field;
                                                $value = $restoreRows ? old($path, $row['values'][$field]) : $row['values'][$field];
                                                $id = $key.'-'.$field;
                                            @endphp
                                            <div class="min-w-0">
                                                <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-slate-300">{{ $definition['label'] }}</label>
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
                                                @if ($field === 'sdgs')<p id="{{ $id }}-help" class="mt-1 text-xs text-slate-500 dark:text-slate-400">SDG numbers 1–17, separated by commas.</p>
                                                @elseif ($field === 'project_status')<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank for non-funded research.</p>@endif
                                                <x-input-error :messages="$errors->get($path)" class="mt-1" />
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </details>
                        @empty
                            <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">No {{ strtolower($label) }} in the submitted college reports for this quarter.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach

            <input type="hidden" name="form_complete" value="1">
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 py-4 dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $missingCount }} fields to complete. Saving and export are available for partial drafts.</p>
                <div class="flex flex-wrap gap-2"><button type="submit" name="intent" value="save" class="dashboard-action">Save draft</button><x-primary-button name="intent" value="download">Save and download Excel</x-primary-button></div>
            </div>
        </form>
    </div>
</x-app-layout>
