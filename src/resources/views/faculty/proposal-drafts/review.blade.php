<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">← Proposal package</a>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Review and Turn In</h2>
                <p class="mt-1 text-xs text-gray-500">Confirm the shared details and seven PDF attachments before turning in the proposal.</p>
            </div>
            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-black {{ $readyToSubmit ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $readyToSubmit ? 'Ready to turn in' : 'Incomplete package' }}</span>
        </div>
    </x-slot>

    @php
        $workPlanDocument = $checklist->get('work-plan')['documents']->first();
        $workPlanSource = $workPlanDocument?->source_data;
        $lineItemBudgetDocument = $checklist->get('line-item-budget')['documents']->first();
        $lineItemBudgetSource = $lineItemBudgetDocument?->source_data;
        $curriculumVitaeDocument = $checklist->get('curriculum-vitae')['documents']->first();
        $curriculumVitaeSource = $curriculumVitaeDocument?->source_data;
    @endphp

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800">
                <p class="font-black">This proposal package cannot be turned in yet.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @elseif (! $readyToSubmit)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete the items below before submitting.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($readinessErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section aria-labelledby="review-details-heading" class="rounded-2xl border {{ $projectDetailsComplete ? 'border-green-200' : 'border-amber-200' }} bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><h3 id="review-details-heading" class="text-lg font-black text-gray-900">Project Details</h3><p class="mt-1 text-xs text-gray-500">Shared across the proposal package.</p></div>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Edit details</a>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-bold text-gray-900">{{ $proposalDraft->project_title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Research Call</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->researchCall->title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->duration_months ? $proposalDraft->duration_months.' '.Str::plural('month', $proposalDraft->duration_months) : 'Missing' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Missing' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned Start</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_start?->format('M j, Y') ?? 'Missing' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned End</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_end?->format('M j, Y') ?? 'Missing' }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="review-papers-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div><h3 id="review-papers-heading" class="text-lg font-black text-gray-900">PDF attachments</h3><p class="mt-1 text-xs text-gray-500">Generated papers become fixed PDFs during Turn in. Uploaded papers are preserved exactly as attached.</p></div>

            <div class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200">
                @foreach ($checklist as $item)
                    @php($paper = $item['paper'])
                    <article class="p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100 text-[10px] font-black text-red-700">PDF</span>
                                <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-black text-gray-900">{{ $paper['label'] }}</h4>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $item['complete'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $item['status'] }}</span>
                                </div>
                                <p class="mt-2 break-all text-xs font-bold text-gray-800">{{ $item['submission_filename'] }}</p>
                                <div class="mt-2 text-xs leading-5 text-gray-500">
                                    @if ($paper['mode'] === 'automatic')
                                        <p>ATHENA will generate this PDF from the shared Project Title and Project Leader. Evaluator fields remain blank.</p>
                                    @elseif ($item['documents']->isEmpty())
                                        <p>No PDF attachment is ready.</p>
                                    @elseif ($paper['mode'] === 'generated')
                                        <p>Saved form data will be rendered into the official template and attached as a PDF.</p>
                                    @else
                                        <p>Faculty-uploaded PDF attached {{ $item['documents']->first()->updated_at->diffForHumans() }}.</p>
                                    @endif
                                </div>
                                </div>
                            </div>
                            <a href="{{ match ($paper['slug']) { 'detailed-proposal' => route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft), 'work-plan' => route('faculty.proposal-drafts.work-plan.edit', $proposalDraft), 'line-item-budget' => route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft), 'curriculum-vitae' => route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft), 'gad-checklist' => route('faculty.proposal-drafts.gad-checklist.edit', $proposalDraft), 'initial-screening-form' => route('faculty.proposal-drafts.initial-screening-form.show', $proposalDraft), default => route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']]) } }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">{{ $paper['mode'] === 'automatic' ? 'Preview' : ($item['complete'] ? 'Edit' : 'Complete paper') }}</a>
                        </div>

                        @if ($paper['slug'] === 'work-plan' && is_array($workPlanSource))
                            <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                                @foreach (['preview' => 'Preview Work Plan'] as $action => $label)
                                    <form action="{{ route('faculty.proposal-drafts.work-plan.'.$action, $proposalDraft) }}" method="POST" @if ($action === 'preview') target="_blank" @endif class="w-full sm:w-auto">
                                        @csrf
                                        @foreach ($workPlanSource['entries'] as $entryIndex => $entry)
                                            <input type="hidden" name="entries[{{ $entryIndex }}][objective]" value="{{ $entry['objective'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][expected_output]" value="{{ $entry['expected_output'] }}">
                                            <input type="hidden" name="entries[{{ $entryIndex }}][activity]" value="{{ $entry['activity'] }}">
                                            @foreach ($entry['months'] as $month)<input type="hidden" name="entries[{{ $entryIndex }}][months][]" value="{{ $month }}">@endforeach
                                        @endforeach
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @elseif ($paper['slug'] === 'line-item-budget' && is_array($lineItemBudgetSource))
                            <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                                @foreach (['preview' => 'Preview Line-Item Budget'] as $action => $label)
                                    <form action="{{ route('faculty.proposal-drafts.line-item-budget.'.$action, $proposalDraft) }}" method="POST" @if ($action === 'preview') target="_blank" @endif class="w-full sm:w-auto">
                                        @csrf
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @elseif ($paper['slug'] === 'curriculum-vitae' && is_array($curriculumVitaeSource))
                            <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                                @foreach (['preview' => 'Preview CV Package'] as $action => $label)
                                    <form action="{{ route('faculty.proposal-drafts.curriculum-vitae.'.$action, $proposalDraft) }}" method="POST" @if ($action === 'preview') target="_blank" @endif class="w-full sm:w-auto">
                                        @csrf
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @elseif ($paper['slug'] === 'initial-screening-form')
                            <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                                <a href="{{ route('faculty.proposal-drafts.initial-screening-form.preview', $proposalDraft) }}" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Preview Initial Screening Form</a>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border {{ $readyToSubmit ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-5 sm:p-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-black {{ $readyToSubmit ? 'text-green-900' : 'text-gray-900' }}">Turn in proposal</h3>
                    <p class="mt-1 max-w-2xl text-sm leading-6 {{ $readyToSubmit ? 'text-green-800' : 'text-gray-600' }}">{{ $readyToSubmit ? 'Turn in creates seven immutable PDF attachments and sends version 1 to the Research Head.' : 'Complete Project Details and every required paper to enable Turn in.' }}</p>
                </div>
                @can('submit', $proposalDraft)
                <form action="{{ route('faculty.proposal-drafts.submit', $proposalDraft) }}" method="POST" onsubmit="return confirm('Turn in these seven PDF attachments to the Research Head?')" class="w-full shrink-0 sm:w-auto">
                    @csrf
                    <button type="submit" @disabled(! $readyToSubmit) class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-6 py-3 text-sm font-black text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 sm:w-auto">Turn in proposal</button>
                </form>
                @else
                    <p class="rounded-xl bg-blue-100 px-4 py-3 text-sm font-bold text-blue-900">Only {{ $proposalDraft->owner->name }} can submit this shared workspace.</p>
                @endcan
            </div>
        </section>
    </div>
</x-app-layout>
