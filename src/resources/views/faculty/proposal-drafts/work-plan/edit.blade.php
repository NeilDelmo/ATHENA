<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">← Proposal package</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $workPlanDocument?->completed_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $workPlanDocument?->completed_at ? 'Complete' : 'Not started' }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Build the official BatStateU-FO-RES-02 Work Plan from structured inputs.</p>
        </div>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $initialEntries = old('entries', $sourceData['entries'] ?? []);
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-dirty="false"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.work-plan.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}"
        x-data="proposalDraftWorkPlan({
            initialEntries: @js($initialEntries),
            maxEntries: @js(config('work_plan.max_objectives')),
            durationMonths: @js($proposalDraft->duration_months ?: 12),
            previewUrl: @js(route('faculty.proposal-drafts.work-plan.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.work-plan.download', $proposalDraft)),
            csrfToken: @js(csrf_token()),
        })"
    >
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">The Work Plan could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>

        <x-paper-editor-submit-status />
        <x-paper-editor-shortcuts />

        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">Project title, duration, planned dates, and project leader are required before Attachment A can be saved or generated.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><h3 class="text-base font-black text-gray-900">Shared project information</h3><p class="mt-1 text-xs text-gray-500">Edit these values from Project Details; they are applied automatically to the paper.</p></div>
                <div class="flex gap-2">
                    @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a>@endif
                    <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-5">
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->duration_months ? $proposalDraft->duration_months.' months' : 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned Start</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_start?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned End</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_end?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div class="sm:col-span-2 lg:col-span-5"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader / Prepared by</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
            </dl>
        </section>

        <form data-paper-form x-ref="form" x-on:submit="if (!validateForm()) $event.preventDefault()" action="{{ route('faculty.proposal-drafts.work-plan.update', $proposalDraft) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ $workPlanDocument?->lock_version ?? 0 }}">

            <section aria-labelledby="work-plan-objectives-heading" class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="work-plan-objectives-heading" class="text-lg font-black text-gray-900">Objectives and Gantt schedule</h3>
                        <p class="mt-1 text-sm text-gray-500">Add one row per objective and select its active months. Each month can belong to only one objective.</p>
                        <p class="mt-1 text-xs text-gray-400">The generated paper automatically expands each row to fit the longest objective, output, or activity text.</p>
                    </div>
                    <button type="button" x-on:click="addEntry" x-bind:disabled="!canAddEntry()" x-bind:title="canAddEntry() ? 'Add another objective' : 'No unassigned project month is available for another objective.'" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Add another objective</button>
                </div>

                <template x-for="(entry, index) in entries" :key="entry.id">
                    <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-sm font-black text-gray-900">Objective <span x-text="index + 1"></span></h4>
                            <button type="button" x-on:click="removeEntry(index)" x-bind:disabled="entries.length === 1" class="rounded-lg px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40">Remove</button>
                        </div>

                        <div class="mt-5 grid gap-5 lg:grid-cols-3">
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`objective-${entry.id}`">Objective <span class="text-red-600">Required</span></label>
                                <textarea x-bind:id="`objective-${entry.id}`" x-bind:name="`entries[${index}][objective]`" x-model="entry.objective" rows="4" maxlength="500" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`output-${entry.id}`">Expected Output <span class="text-red-600">Required</span></label>
                                <textarea x-bind:id="`output-${entry.id}`" x-bind:name="`entries[${index}][expected_output]`" x-model="entry.expectedOutput" rows="4" maxlength="500" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`activity-${entry.id}`">Activities or Workplan <span class="text-red-600">Required</span></label>
                                <textarea x-bind:id="`activity-${entry.id}`" x-bind:name="`entries[${index}][activity]`" x-model="entry.activity" rows="4" maxlength="1500" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                            </div>
                        </div>

                        <fieldset class="mt-5">
                            <legend class="text-xs font-black uppercase tracking-wider text-gray-600">Gantt Schedule <span class="text-red-600">Required</span></legend>
                            <p class="mt-2 text-xs leading-5 text-gray-500">Each 12-month block becomes a matching Attachment A year sheet. Months assigned to another objective are locked until they are removed from that objective.</p>
                            <div class="mt-3 grid gap-4">
                                <template x-for="yearGroup in yearGroups" :key="yearGroup.year">
                                    <section class="rounded-xl border border-gray-200 bg-gray-50 p-3" x-bind:aria-label="`Year ${yearGroup.year} schedule`">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs font-black uppercase tracking-wider text-gray-700" x-text="`Y${yearGroup.year}`"></p>
                                            <p class="text-[10px] font-semibold text-gray-500" x-text="`Project months ${yearGroup.months[0]}-${yearGroup.months[yearGroup.months.length - 1]}`"></p>
                                        </div>
                                        <div class="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-12">
                                            <template x-for="month in yearGroup.months" :key="month">
                                                <label
                                                    class="relative flex cursor-pointer flex-col items-center justify-center rounded-xl border px-2 py-2.5 text-xs font-black transition focus-within:ring-2 focus-within:ring-red-600 focus-within:ring-offset-2"
                                                    x-bind:class="entry.months.includes(month) ? 'border-red-600 bg-red-50 text-red-700' : (isMonthSelectable(index, month) ? 'border-gray-200 bg-white text-gray-600 hover:border-gray-300' : 'cursor-not-allowed border-amber-200 bg-amber-50 text-amber-700')"
                                                    x-bind:title="monthSelectionTitle(index, month)"
                                                >
                                                    <input type="checkbox" class="sr-only" x-bind:name="`entries[${index}][months][]`" x-bind:value="month" x-model.number="entry.months" x-bind:disabled="!isMonthSelectable(index, month)" x-on:change="clearMonthError(index)">
                                                    <span x-text="`M${localMonthNumber(month)}`"></span>
                                                    <span x-show="yearGroup.year > 1" class="mt-0.5 text-[9px] font-semibold text-gray-500" x-text="`Project M${month}`"></span>
                                                    <span x-show="monthOwnerLabel(index, month)" x-text="monthOwnerLabel(index, month)" class="mt-0.5 text-[9px] font-bold uppercase tracking-wide"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </section>
                                </template>
                            </div>
                            <p x-show="monthErrorIndexes.includes(index)" x-cloak class="mt-2 text-xs font-semibold text-red-600">Select at least one month for this objective.</p>
                            <p x-show="monthConflictIndexes.includes(index)" x-cloak class="mt-2 text-xs font-semibold text-red-600">This objective shares a month with an earlier objective. Remove the duplicate month.</p>
                        </fieldset>
                    </article>
                </template>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <p class="text-[10px] font-black uppercase tracking-wider text-gray-500">Checked &amp; Verified by</p>
                    <p class="mt-2 font-black text-gray-900">{{ config('work_plan.verifier.name') }}</p>
                    <p class="text-xs text-gray-600">{{ config('work_plan.verifier.role') }}</p>
                    <p class="mt-2 text-xs text-gray-500">Both Date Signed fields remain blank for handwritten signatures.</p>
                </div>
            </section>

            @include('faculty.proposal-drafts.partials.change-note')

            <div class="flex flex-col-reverse gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:justify-end">
                <a data-paper-discard href="{{ route('faculty.proposal-drafts.work-plan.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Discard changes</a>
                <a data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Cancel and exit</a>
                <button type="button" x-on:click="generatePreview" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!previewLoading">Preview paper</span><span x-show="previewLoading" x-cloak>Generating…</span></button>
                <button type="button" x-on:click="downloadDocument" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!downloadLoading">Download Word file</span><span x-show="downloadLoading" x-cloak>Preparing…</span></button>
                <button data-paper-save-exit type="submit" name="exit_after_save" value="1" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Save and exit</button>
                <button data-paper-save type="submit" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Save changes</button>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        <section x-show="previewHtml" x-cloak aria-labelledby="work-plan-preview-heading" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><h3 id="work-plan-preview-heading" class="text-base font-black text-gray-900">Work Plan preview</h3><p class="mt-1 text-xs text-gray-500">This preview follows the official paper layout.</p></div>
                <button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 disabled:opacity-50 sm:w-auto">Print preview</button>
            </div>
            <iframe x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="Attachment A Work Plan preview" class="h-[75vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
