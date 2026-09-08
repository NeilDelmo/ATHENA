@props(['topic', 'draft' => null, 'defaults' => [], 'evidence' => []])
@php
    $data = array_replace($defaults, $draft?->source_data ?? []);
    $terminal = $data['terminal_data'] ?? [];
    $value = fn ($key, $fallback = '') => old($key, data_get($data, $key, $fallback));
    $input = 'mt-1 block w-full rounded-xl border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white';
    $authors = $value('terminal_data.authors', []);
    $accomplishments = $value('accomplishments', []) ?: [['objective' => '', 'target' => '', 'actual' => '']];
@endphp
<section class="p-5 sm:p-6" data-narrative-progress-autosave="true" x-data="narrativeProgressReportForm({previewUrl: @js(route('project-narrative-reports.preview', $topic)), draftSaveUrl: @js(route('project-narrative-reports.draft', $topic)), initialDraftVersion: @js((int) ($draft?->lock_version ?? 0)), csrfToken: @js(csrf_token())})">
<form x-ref="form" data-narrative-progress-autosave-form method="POST" action="{{ route('project-narrative-reports.prepare', $topic) }}" enctype="multipart/form-data" class="space-y-7 text-gray-800 dark:text-slate-200" @submit="submitting = true">
    @csrf
    <input type="hidden" name="report_type" value="terminal">
    <input type="hidden" name="draft_version" value="{{ $draft?->lock_version ?? 0 }}">
    <x-proposal-autosave-status />
    @if ($errors->narrativeProgress->any())
        <div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800"><p class="font-bold">Please review these fields:</p><ul class="list-disc pl-5">@foreach ($errors->narrativeProgress->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <p class="rounded-xl bg-blue-50 p-4 text-sm leading-6 text-blue-900 dark:bg-blue-950 dark:text-blue-100">Review the carried-over proposal content and confirm final dates, spending and findings. Text saves privately. Select new image files again if you leave before preparing the PDF.</p>
    @if (($defaults['missing_monitoring_periods'] ?? []) !== [])
        <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Before preparing the final PDF, complete monitoring reports for {{ implode(', ', $defaults['missing_monitoring_periods']) }}. You can save and preview this draft.</p>
    @endif
    <details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700"><summary class="cursor-pointer font-bold">Earlier monitoring information</summary>
        @forelse ($defaults['monitoring_reference'] ?? [] as $source)
            <div class="mt-4 space-y-2 border-t border-gray-200 pt-3 dark:border-slate-700"><h4 class="font-semibold">{{ $source['period'] }}</h4><p class="whitespace-pre-line text-sm">{{ $source['accomplishments'] }}</p>
                @foreach ($source['work_plan'] ?? [] as $activity)<p class="text-sm"><strong>{{ $activity['activity'] ?? '' }}:</strong> {{ $activity['actual_accomplishment'] ?? '' }} {{ $activity['findings'] ?? '' }}</p>@endforeach
                @foreach ($source['budget_utilization'] ?? [] as $budget)<p class="text-xs">{{ $budget['type'] ?? '' }} — {{ $budget['details'] ?? '' }}: ₱{{ number_format((float) ($budget['actual_amount'] ?? 0), 2) }}</p>@endforeach
            </div>
        @empty<p class="mt-3 text-sm">No submitted monitoring information is available yet.</p>@endforelse
    </details>
    <section class="space-y-4">
        <h3 class="text-lg font-bold">I–II. Cover and project details</h3>
        <div class="rounded-xl border border-gray-200 p-4 dark:border-slate-700"><p class="font-bold">{{ $terminal['project_title'] ?? $topic->title }}</p><p class="mt-2 text-sm">Approved period: {{ $terminal['approved_start'] ?? 'Not recorded' }} – {{ $terminal['approved_end'] ?? 'Not recorded' }} ({{ $terminal['approved_duration_months'] ?? $topic->estimated_duration_months }} months)</p><p class="text-sm">Approved budget: ₱{{ number_format((float) ($terminal['approved_budget'] ?? $topic->estimated_budget), 2) }}</p></div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (['submission_date' => 'Submission date', 'implementation_start' => 'Actual start date', 'implementation_end' => 'Actual completion date'] as $field => $label)
                <label class="text-sm font-semibold">{{ $label }}<input type="date" name="{{ $field }}" value="{{ $value($field) }}" max="{{ now()->toDateString() }}" required class="{{ $input }}"></label>
            @endforeach
            <label class="text-sm font-semibold">Tracking number (optional)<input name="tracking_number" value="{{ $value('tracking_number') }}" maxlength="100" class="{{ $input }}"></label>
            <div x-data="{spent: @js($value('terminal_data.total_expenditure')), budget: @js((float) ($terminal['approved_budget'] ?? $topic->estimated_budget))}"><label class="text-sm font-semibold">Final total expenditure (₱)<input type="number" min="0" step="0.01" name="terminal_data[total_expenditure]" x-model="spent" required class="{{ $input }}"></label><p class="mt-2 text-xs" x-text="budget > 0 && spent !== '' ? 'Budget utilization: ' + (Number(spent) / budget * 100).toFixed(2) + '%' : 'Budget utilization: N/A'"></p><p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Confirm the final total. Do not add repeated cumulative monitoring amounts together.</p></div>
            <label class="text-sm font-semibold">Collaborating agency (if any)<input name="terminal_data[collaborating_agency]" value="{{ $value('terminal_data.collaborating_agency') }}" maxlength="1000" placeholder="None" class="{{ $input }}"></label>
        </div>
        <div class="space-y-3" x-data="{authors: @js($authors)}">
            <h4 class="font-bold">Authors and prepared-by signatures</h4>
            <template x-for="(author, index) in authors" :key="index"><div class="grid gap-3 rounded-xl border border-gray-200 p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-700">
                <template x-for="field in ['name', 'rank', 'campus', 'college']" :key="field"><label class="text-xs font-semibold"><span x-text="field === 'rank' ? 'Academic rank' : field.charAt(0).toUpperCase() + field.slice(1)"></span><input :name="`terminal_data[authors][${index}][${field}]`" x-model="author[field]" :required="field === 'name'" maxlength="255" class="{{ $input }}"></label></template>
                <label class="text-xs font-semibold">Project role<select :name="`terminal_data[authors][${index}][role]`" x-model="author.role" class="{{ $input }}"><option>Project Leader</option><option>Project Staff</option></select></label>
                <label class="text-xs font-semibold">Date signed (optional)<input type="date" :name="`terminal_data[authors][${index}][date_signed]`" x-model="author.date_signed" max="{{ now()->toDateString() }}" class="{{ $input }}"></label>
                <button type="button" @click="authors.splice(index, 1); $nextTick(() => $el.dispatchEvent(new Event('input', {bubbles: true})))" :disabled="authors.length === 1" class="text-left text-xs font-bold text-red-600 disabled:opacity-40">Remove author</button>
            </div></template>
            <button type="button" @click="authors.push({name:'',rank:'',campus:'',college:'',role:'Project Staff',date_signed:''})" :disabled="authors.length >= 30" class="text-sm font-bold text-red-600">Add author</button>
            <p class="text-xs text-gray-500 dark:text-slate-400">Names populate the cover, author list and prepared-by blocks. Leave signing dates blank until signed.</p>
        </div>
    </section>
    <section class="space-y-3" x-data="{rows: @js($accomplishments)}">
        <h3 class="text-lg font-bold">III. Summary of Accomplishment</h3><p class="text-sm">Confirm each approved objective and its final outcome. These objectives also populate the narrative objectives section.</p>
        <template x-for="(row, index) in rows" :key="index"><div class="grid gap-3 rounded-xl border border-gray-200 p-4 lg:grid-cols-3 dark:border-slate-700">
            <template x-for="field in ['objective','target','actual']" :key="field"><label class="text-sm font-semibold"><span x-text="field === 'objective' ? 'Objective '+(index+1) : (field === 'target' ? 'Target accomplishment' : 'Actual accomplishment')"></span><textarea :name="`accomplishments[${index}][${field}]`" x-model="row[field]" :maxlength="field === 'objective' ? 1000 : 2000" required rows="4" class="{{ $input }}"></textarea></label></template>
            <button type="button" @click="rows.splice(index,1); $nextTick(() => $el.dispatchEvent(new Event('input', {bubbles:true})))" :disabled="rows.length === 1" class="text-left text-xs font-bold text-red-600 disabled:opacity-40">Remove row</button>
        </div></template>
        <button type="button" @click="rows.push({objective:'',target:'',actual:''})" :disabled="rows.length >= 30" class="text-sm font-bold text-red-600">Add objective</button>
    </section>
    <section class="space-y-5">
        <div x-data="{abstract: @js(app(\App\Support\TerminalReportData::class)->plain($value('terminal_data.abstract')))}"><label class="block font-bold">IV. Abstract (200–250 words)<textarea name="terminal_data[abstract]" x-model="abstract" required rows="7" class="{{ $input }}"></textarea></label><p class="mt-2 text-xs" x-text="(abstract.trim() ? abstract.trim().split(/\s+/).length : 0) + ' words'"></p></div>
        <h3 class="text-lg font-bold">V. Introduction, literature and objectives</h3>
        @foreach (['introduction' => 'Introduction', 'rationale' => 'Rationale', 'terminal_data.literature_review' => 'Review of Literature', 'objectives' => 'General objective (optional)', 'methodology' => 'VI. Materials and Methods / Methodology', 'results_discussion' => 'VII. Results and Discussion', 'terminal_data.conclusions' => 'Conclusions', 'terminal_data.recommendations' => 'Recommendations', 'terminal_data.bibliography' => 'Bibliography'] as $field => $label)
            <label class="block font-semibold">{{ $label }}<textarea id="terminal-{{ str_replace('.', '-', $field) }}" name="{{ str_contains($field, '.') ? 'terminal_data['.substr($field, 14).']' : $field }}" data-semantic-editor rows="7" maxlength="100000" @required($field !== 'objectives') class="{{ $input }}">{{ $value($field) }}</textarea></label>
        @endforeach
    </section>
    <x-terminal-report-tables :tables="$value('terminal_data.tables', [])" />
    <section class="space-y-3">
        <h3 class="text-lg font-bold">Figures (optional)</h3><p class="text-sm">Use relevant JPG or PNG evidence, up to 10 MB each. Reuse an earlier figure or upload a replacement. A paragraph position of 0 places it at the end of the section.</p>
        @foreach (range(1, 30) as $index)
            @if ($index === 4)<details class="rounded-xl border border-gray-200 p-4 dark:border-slate-700"><summary class="cursor-pointer font-bold">More figures (4–30)</summary><div class="mt-4 space-y-3">@endif
            <div class="grid gap-3 rounded-xl border border-gray-200 p-4 sm:grid-cols-2 dark:border-slate-700" x-data="{caption: @js($value('photo_caption_'.$index)), section: @js($value('photo_section_'.$index, 'results_discussion')), evidence: @js($evidence)}">
                <label class="text-sm font-semibold">Figure slot {{ $index }} — new upload<input type="file" name="photo_{{ $index }}" accept=".jpg,.jpeg,.png" class="{{ $input }}"></label>
                <label class="text-sm font-semibold">Or reuse earlier evidence<select name="reuse_photo_{{ $index }}" @change="if (evidence[$event.target.value]) { caption = evidence[$event.target.value].caption; section = evidence[$event.target.value].section; }" class="{{ $input }}"><option value="">No earlier figure selected</option>@foreach ($evidence as $key => $photo)<option value="{{ $key }}" @selected($value('reuse_photo_'.$index) === $key)>{{ $photo['label'] }}</option>@endforeach</select></label>
                <label class="text-sm font-semibold">Caption<input name="photo_caption_{{ $index }}" x-model="caption" maxlength="200" class="{{ $input }}"></label>
                <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm">Section<select name="photo_section_{{ $index }}" x-model="section" class="{{ $input }}"><option value="methodology">Methodology</option><option value="results_discussion">Results and Discussion</option></select></label><label class="text-sm">After paragraph (0 = end)<input type="number" min="0" max="1000" name="photo_after_paragraph_{{ $index }}" value="{{ $value('photo_after_paragraph_'.$index, 0) }}" class="{{ $input }}"></label></div>
            </div>
            @if ($index === 30)</div></details>@endif
        @endforeach
    </section>
    <section class="space-y-4"><h3 class="text-lg font-bold">Review and approval signatories</h3><p class="text-sm">Confirm names for each role. Leave dates blank for unsigned copies. Selecting a name does not apply a signature or approve the report.</p>
        @foreach (\App\Support\TerminalReportRules::SIGNATORY_ROLES as $key => [$group, $role])
            <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm font-semibold">{{ $group }} — {{ $role }}<input name="terminal_data[signatories][{{ $key }}][name]" value="{{ $value('terminal_data.signatories.'.$key.'.name') }}" list="terminal-signatories" maxlength="255" required class="{{ $input }}"></label><label class="text-sm">Date signed (optional)<input type="date" name="terminal_data[signatories][{{ $key }}][date_signed]" value="{{ $value('terminal_data.signatories.'.$key.'.date_signed') }}" max="{{ now()->toDateString() }}" class="{{ $input }}"></label></div>
        @endforeach
        <datalist id="terminal-signatories">@foreach ($defaults['signatory_options'] ?? [] as $name)<option value="{{ $name }}">@endforeach</datalist>
    </section>
    <div class="flex flex-wrap justify-end gap-3"><button type="button" @click="generatePreview" :disabled="previewLoading || submitting" class="rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold">Preview Terminal report</button><button type="submit" :disabled="previewLoading || submitting" class="rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white">Prepare official PDF</button></div>
    <p x-show="previewError" x-text="previewError" role="alert" class="text-sm text-red-600"></p>
    <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3"><p class="text-sm">Review this draft preview. Prepare the PDF to review final pagination before submission.</p><iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Terminal report preview" class="h-[75vh] w-full rounded-xl border border-gray-300 bg-white"></iframe></section>
</form>
</section>
