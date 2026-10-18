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
    $crumbs = [
        ['label' => 'Quarterly monitoring', 'url' => route('research_head.rms-quarterly-reports.index')],
        ['label' => $year, 'url' => route('research_head.rms-quarterly-reports.index', ['year' => $year])],
        ['label' => 'Q'.$quarter],
    ];
    $errorSection = collect(['presentation' => 'presentations', 'publication' => 'publications', 'ip' => 'ip_assets'])
        ->first(fn (string $section, string $prefix): bool => $errors->has('row_details.'.$prefix.'-*.*'));
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Quarterly Monitoring Report" subtitle="Review college outputs and export the campus report.">
            <x-slot name="actions"><a href="{{ route('research_head.quarterly-reports.index', ['year' => $year]) }}" class="{{ $iconAction }}" aria-label="Open college reports" title="College reports"><x-report-library-icon name="college" /></a></x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5" data-rms-quarterly-reports data-report-library x-data="reportLibrary(@js('athena-report-library:'.auth()->id()))" data-view="grid" :data-view="view" data-size="medium" :data-size="size">
        @if (session('status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-base text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-base text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p class="font-semibold">Check the report before saving.</p>
                <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <x-report-library-path :crumbs="$crumbs" :back-url="route('research_head.rms-quarterly-reports.index', ['year' => $year])">
                <div class="flex gap-2" role="group" aria-label="Report actions">
                    <button type="submit" form="rms-report-form" name="intent" value="save" class="{{ $iconAction }}" aria-label="Save draft" title="Save draft"><x-report-library-icon name="save" /></button>
                    <button type="submit" form="rms-report-form" name="intent" value="download" class="{{ $iconAction }} !bg-brand !text-white" aria-label="Save and download spreadsheet" title="Save and download spreadsheet"><x-report-library-icon name="export" /></button>
                </div>
            </x-report-library-path>
        </div>

        <details class="dashboard-panel p-5" aria-labelledby="rms-source-heading">
            <summary class="disclosure-summary rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
            <span class="inline-flex flex-wrap items-start justify-between gap-4 align-top">
                <span>
                    <span id="rms-source-heading" class="block text-xl font-bold text-gray-950 dark:text-white">{{ $period['start']->format('F') }}–{{ $period['end']->format('F Y') }} · Q{{ $quarter }}</span>
                    <span class="mt-1 block text-base text-slate-600 dark:text-slate-400">{{ count($reportData['sources']) }} {{ Illuminate\Support\Str::plural('college report', count($reportData['sources'])) }} · {{ $rows->count() }} records · {{ $missingCount }} fields to complete · {{ count($reportData['missing_colleges']) }} colleges pending</span>
                </span>
            </span>
            </summary>
            <p class="mt-4 text-base text-slate-500 dark:text-slate-400">{{ $report ? 'Draft saved '.$report->updated_at->format('M j, Y g:i A') : 'Draft not yet saved' }}</p>
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

        {{-- Server validation reopens the folder containing an invalid field. --}}
        <form method="POST" action="{{ route('research_head.rms-quarterly-reports.store') }}" novalidate class="space-y-5" id="rms-report-form" x-data="rmsQuarterlyReportForm" x-init="openSection(@js($errorSection))" @formdata="serialize($event)"
            data-assistant-report-type="rms-quarterly" data-assistant-report-key="{{ $report ? 'rms-quarterly:'.$report->id : '' }}" data-assistant-report-save-mode="manual">
            @csrf
            <input type="hidden" name="reporting_year" value="{{ $year }}">
            <input type="hidden" name="reporting_quarter" value="{{ $quarter }}">
            <input type="hidden" name="source_token" value="{{ $reportData['source_token'] }}">

            <nav data-rms-section-folders aria-label="Report sections" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex min-w-0 flex-wrap items-center gap-3">
                    <h2 x-show="section === null" class="text-xl font-semibold text-slate-950 dark:text-white">Report contents</h2>
                    <div x-cloak x-show="section !== null" class="flex min-w-0 flex-wrap items-center gap-3">
                        <button type="button" x-ref="sectionBack" @click="const previousSection = section; openSection(null); $nextTick(() => $refs[previousSection + 'Folder'].focus())" class="{{ $iconAction }}" aria-label="Back to report contents" title="Back to report contents"><x-report-library-icon name="back" /></button>
                        <span class="hidden text-base text-slate-500 dark:text-slate-400 sm:inline">Report contents /</span>
                        <span class="text-lg font-semibold text-slate-950 dark:text-white" x-text="{ presentations: 'Paper presentations', publications: 'Publications', ip_assets: 'IP assets' }[section]"></span>
                    </div>
                    <x-report-library-view-controls class="ml-auto" />
                </div>
                <div x-show="section === null">
                    <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="report-library-items mt-4">
                        @foreach ($sections as $section => $label)
                            <button type="button" x-ref="{{ $section }}Folder" @click="openSection('{{ $section }}'); $nextTick(() => $refs.sectionBack.focus())" class="report-library-item" aria-label="Open {{ strtolower($label) }} folder">
                                <x-report-library-icon name="folder" class="report-library-graphic" />
                                <span class="min-w-0">
                                    <span class="report-library-name"><span class="inline-flex items-center gap-2"><x-report-library-icon :name="match ($section) { 'presentations' => 'presentation', 'publications' => 'publication', default => 'ip' }" />{{ $label }}</span></span>
                                    <span class="report-library-meta">{{ count($reportData[$section]) }} {{ Illuminate\Support\Str::plural('record', count($reportData[$section])) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
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
                    <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="report-library-items report-library-files p-4">
                        @forelse ($reportData[$section] as $row)
                            @php
                                $key = $row['key'];
                                $title = $row['values'][match ($section) {'presentations' => 'presentation_title', 'publications' => 'article_title', default => 'title'}] ?: 'Untitled record';
                            @endphp
                            <details class="report-library-file group" @if ($errors->has('row_details.'.$key.'.*') || $errors->has('row_details.'.$key)) open @endif>
                                <summary class="disclosure-summary rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
                                    <x-document-file-icon class="report-library-file-graphic" />
                                    <span class="min-w-0">
                                        <span class="ml-1 font-semibold text-gray-900 dark:text-white">{{ $loop->iteration }}. {{ $title }}</span>
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
                            <p class="col-span-full flex min-h-[300px] items-center justify-center px-5 py-6 text-center text-base text-slate-500 dark:text-slate-400">No {{ strtolower($label) }} in the submitted college reports for this quarter.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach

            <input type="hidden" name="form_complete" value="1">
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 py-4 dark:border-slate-800">
                <p class="text-base text-slate-500 dark:text-slate-400">{{ $missingCount }} fields to complete. Saving and export are available for partial drafts.</p>
                <p class="text-base text-slate-500 dark:text-slate-400">Partial exports may contain blank fields. Review each folder before sending the report.</p>
            </div>
        </form>
    </div>
</x-app-layout>
