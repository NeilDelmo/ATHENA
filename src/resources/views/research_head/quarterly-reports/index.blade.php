@php
    $root = route('research_head.quarterly-reports.index');
    $crumbs = [['label' => 'College reports', 'url' => $libraryLocation === null ? null : $root], ...$libraryCrumbs];
    $backUrl = $libraryBackUrl;
    $iconAction = 'workspace-icon focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand';
    $libraryConfig = [
        'sources' => $librarySources->values(), 'workspace' => $libraryWorkspace, 'location' => $libraryLocation,
        'visibleIds' => $libraryItems->filter(fn ($item) => $item['kind'] === 'folder' || $libraryPageFiles->contains('id', $item['id']))->pluck('id')->values(),
        'storageKey' => 'athena-college-report-library:'.auth()->id(), 'rootUrl' => $root,
        'updateUrl' => route('research_head.quarterly-reports.workspace.update'),
        'sortKey' => $sortKey, 'sortDirection' => $sortDirection,
    ];
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

        <section data-report-library data-library-details x-data="collegeReportLibrary(@js($libraryConfig))" @keydown="handleFileKey($event)" data-view="grid" :data-view="view" data-size="medium" :data-size="size" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <x-report-library-path :crumbs="$crumbs" :back-url="$backUrl">
                <form method="GET" action="{{ $root }}" class="flex items-center gap-2">
                    <input name="year" type="number" min="2000" max="2100" value="{{ $year }}" required aria-label="Reporting year" class="workspace-input w-28">
                    <button type="submit" class="{{ $iconAction }}" aria-label="Open reporting year" title="Open reporting year"><x-report-library-icon name="calendar" /></button>
                </form>
            </x-report-library-path>
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                <button type="button" class="workspace-secondary" :disabled="selection.length !== 1 || saving || stale" @click="openSelection()"><x-report-library-icon name="folder-open" class="!h-5 !w-5" />Open</button>
                <button type="button" class="workspace-secondary" :disabled="!selection.length || saving || stale" @click="copySelection('cut')" title="Cut (Ctrl/⌘+X)"><x-report-library-icon name="cut" class="!h-5 !w-5" />Cut</button>
                <button type="button" class="workspace-secondary" :disabled="!selection.length || saving || stale" @click="copySelection()" title="Copy (Ctrl/⌘+C)"><x-report-library-icon name="copy" class="!h-5 !w-5" />Copy</button>
                <button type="button" class="workspace-secondary" :disabled="!canPaste" @click="pasteSelection()" title="Paste here (Ctrl/⌘+V)"><x-report-library-icon name="paste" class="!h-5 !w-5" />Paste</button>
                <label class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">Sort by<select aria-label="Sort items by" :value="sortKey" @change="setSort($event.target.value)" :disabled="saving || stale" class="workspace-input max-w-[180px]">
                    @foreach (\App\Services\CollegeReportLibrary::SORT_OPTIONS as $field => $label)<option value="{{ $field }}" @selected($sortKey === $field)>{{ $label }}</option>@endforeach
                </select></label>
                <button type="button" class="workspace-icon" @click="setSort(sortKey)" :disabled="saving || stale" :aria-label="sortDirection === 'asc' ? 'Sort descending' : 'Sort ascending'" :title="sortDirection === 'asc' ? 'Sort descending' : 'Sort ascending'"><x-report-library-icon name="sort-asc" x-show="sortDirection === 'asc'" /><x-report-library-icon name="sort-desc" x-show="sortDirection === 'desc'" /></button>
                <span class="sr-only" role="status" x-text="selectedIds.length ? selectedIds.length + ' selected' : 'No selection'"></span>
                <x-report-library-view-controls class="ml-auto" :sort-options="\App\Services\CollegeReportLibrary::SORT_OPTIONS" :show-trigger-icon="true" />
            </div>
            <div x-cloak x-show="error || notice || clipboard" role="status" class="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-3 text-base dark:border-slate-700" :class="error ? 'text-red-700 dark:text-red-300' : 'text-slate-600 dark:text-slate-300'">
                <span class="min-w-0 flex-1" x-text="error || notice || (clipboard?.mode === 'cut' ? 'Cut pending. Paste into the destination folder to move.' : 'Copied. Paste into the destination folder.')"></span>
                <button x-show="clipboard" type="button" class="workspace-secondary" @click="cancelClipboard()">Cancel</button>
                <button x-show="stale" type="button" class="workspace-secondary" @click="window.location.reload()">Reload</button>
            </div>

            <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" @click="if (!$event.target.closest('[data-library-item]')) selectedIds = []" @class(['report-library-items min-h-[320px] p-5', 'report-library-files' => $libraryItems->contains('kind', 'file')])>
                <div class="report-library-detail-heading"><span></span>@foreach (\App\Services\CollegeReportLibrary::SORT_OPTIONS as $field => $label)<button type="button" @click="setSort('{{ $field }}')" :disabled="saving || stale" aria-label="Sort by {{ $label }}" :title="sortKey === '{{ $field }}' && sortDirection === 'asc' ? 'Sort descending' : 'Sort ascending'" class="flex min-h-11 min-w-0 items-center gap-2 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $label }}<span x-show="sortKey === '{{ $field }}'" aria-hidden="true" x-text="sortDirection === 'asc' ? '↑' : '↓'"></span></button>@endforeach</div>
                @foreach ($libraryItems->where('kind', 'folder') as $item)
                    <x-report-library-folder :href="$library->url($item)" :name="$item['name']" :description="$item['description']" :details="$item" data-library-item="{{ $item['id'] }}"
                        @click.prevent="selectItem($el.dataset.libraryItem, $event)" @dblclick="window.location.assign($el.href)"
                        ::class="{ 'ring-2 ring-brand bg-rose-50 dark:bg-rose-950/20': selectedIds.includes($el.dataset.libraryItem), 'opacity-40': isCut($el.dataset.libraryItem) }" />
                @endforeach
                @foreach ($libraryPageFiles as $item)
                    @php
                        $report = $reports->firstWhere('id', $item['report_id']);
                    @endphp
                    @continue($report === null)
                    @php
                        $submission = $report->latestSubmission;
                        $cardId = $item['id'] === $item['source_id'] ? $report->id : sha1($item['id']);
                    @endphp
                    <article data-library-item="{{ $item['id'] }}" tabindex="0" @click="selectItem(@js($item['id']), $event)" @dblclick="if (!$event.target.closest('a, button, input, textarea, summary')) window.location.assign(@js($item['url']))" :class="{ 'ring-2 ring-brand': selectedIds.includes(@js($item['id'])), 'opacity-40': isCut(@js($item['id'])) }" aria-labelledby="college-report-{{ $cardId }}" class="report-library-file">
                        <div class="report-library-file-header">
                            <x-document-file-icon class="report-library-file-graphic" />
                            <div class="min-w-0 flex-1">
                                <h2 id="college-report-{{ $cardId }}" class="text-xl font-bold leading-8 text-slate-950 dark:text-white">{{ $item['name'] }}</h2>
                                <p class="mt-1 text-base leading-7 text-slate-600 dark:text-slate-300">{{ $report->college }}</p>
                                <p class="report-library-file-summary mt-2 text-base leading-7 text-slate-500 dark:text-slate-400">{{ $submission->submittedBy?->name }} · Version {{ $submission->version_number }}<br>Submitted {{ $submission->submitted_at->format('M j, Y g:i A') }}</p>
                                <p class="report-library-file-summary mt-3 text-base font-semibold {{ $submission->returned_at ? 'text-amber-800 dark:text-amber-300' : 'text-emerald-800 dark:text-emerald-300' }}">{{ $submission->returned_at ? 'Returned for correction' : 'Submitted' }}</p>
                                @if ($submission->returned_at)<p class="mt-2 whitespace-pre-line text-base leading-7 text-slate-600 dark:text-slate-300">{{ $submission->return_reason }}</p>@endif
                            </div>
                            <x-report-library-details :item="$item" />
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
                                    <label for="return-reason-{{ $cardId }}" class="block text-base text-slate-700 dark:text-slate-300">What should the coordinator correct?</label>
                                    <textarea id="return-reason-{{ $cardId }}" name="reason" rows="3" maxlength="5000" required class="workspace-input block w-full">{{ (int) old('submission_id') === $submission->id ? old('reason') : '' }}</textarea>
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
                @endforeach
                @if ($libraryItems->isEmpty())
                    <div class="col-span-full flex min-h-[300px] flex-col items-center justify-center gap-4 px-6 py-10 text-center"><x-report-library-icon name="folder" /><h2 class="text-xl font-semibold text-slate-900 dark:text-white">This folder is empty</h2><p class="max-w-xl text-base leading-7 text-slate-500 dark:text-slate-400">Paste copied or cut items here.</p></div>
                @endif
            </div>
            @if ($reports->hasPages())<div class="border-t border-slate-200 p-5 dark:border-slate-700">{{ $reports->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
