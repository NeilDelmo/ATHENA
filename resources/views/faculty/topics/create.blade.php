<x-app-layout>
    @php
        $packageInputs = [
            ['name' => 'detailed_proposal', 'label' => 'Detailed Research Proposal', 'help' => 'The complete proposal manuscript.', 'accept' => '.doc,.docx,.pdf', 'multiple' => false],
            ['name' => 'work_plan', 'label' => 'Attachment A - Work Plan', 'help' => 'The activities, schedule, and expected outputs.', 'accept' => '.doc,.docx,.pdf', 'multiple' => false],
            ['name' => 'line_item_budget', 'label' => 'Attachment B - Line-Item Budget', 'help' => 'The detailed project budget document.', 'accept' => '.doc,.docx,.pdf', 'multiple' => false],
            ['name' => 'expense_breakdown', 'label' => 'Estimated Expense Breakdown', 'help' => 'Upload the completed spreadsheet.', 'accept' => '.xls,.xlsx', 'multiple' => false],
            ['name' => 'curricula_vitae', 'label' => 'Attachment C - Curriculum Vitae', 'help' => 'You may select multiple files for the project team.', 'accept' => '.doc,.docx,.pdf', 'multiple' => true],
        ];
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <a href="{{ route('faculty.dashboard') }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 transition hover:text-red-600">&larr; Back to dashboard</a>
            <div>
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Submit a Research Proposal</h2>
                <p class="mt-1 text-xs text-gray-500">Follow the faculty submission guide and upload the complete proposal package.</p>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
        <aside class="space-y-5">
            <section class="rounded-2xl border border-red-200 bg-red-50 p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-600">Faculty guide</p>
                <h3 class="mt-2 text-base font-black text-gray-900">Before you submit</h3>
                <ol class="mt-4 space-y-4 text-xs leading-5 text-gray-700">
                    <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white">1</span><span>Choose the open research call that applies to your proposal.</span></li>
                    <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white">2</span><span>Download and complete the official templates below.</span></li>
                    <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white">3</span><span>Check every file for completeness before uploading the package.</span></li>
                    <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white">4</span><span>Submit once. You can track review feedback and revisions from your dashboard.</span></li>
                </ol>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-black text-gray-900">Process contacts</h3>
                <dl class="mt-3 space-y-3 text-xs leading-5">
                    <div><dt class="font-black text-gray-700">Constituent Campus</dt><dd class="text-gray-500">Research Office</dd></div>
                    <div><dt class="font-black text-gray-700">Extension Campus</dt><dd class="text-gray-500">RDES Head Office</dd></div>
                    <div><dt class="font-black text-gray-700">Research Office - Central</dt><dd><a href="mailto:research.grants@g.batstate-u.edu.ph" class="break-all font-bold text-red-600 hover:text-red-700">research.grants@g.batstate-u.edu.ph</a></dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-black text-gray-900">Official templates</h3>
                    <p class="mt-1 text-xs text-gray-400">Use the latest files provided by the Research Office.</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($proposalTemplates as $template)
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-black text-gray-800">{{ $template['name'] }}</p>
                                    @if ($template['revision_label'])<p class="mt-0.5 text-[10px] font-bold text-red-600">{{ $template['revision_label'] }}</p>@endif
                                    @if ($template['description'])<p class="mt-1 text-[11px] leading-4 text-gray-500">{{ $template['description'] }}</p>@endif
                                </div>
                                <a href="{{ route('proposal-templates.download', $template['key']) }}" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-[10px] font-bold text-white transition hover:bg-gray-800">Download</a>
                            </div>
                            @if ($template['instructions'])<p class="mt-2 rounded-lg bg-gray-50 p-2 text-[10px] leading-4 text-gray-500">{{ $template['instructions'] }}</p>@endif
                        </div>
                    @empty
                        <div class="p-5 text-xs font-semibold leading-5 text-amber-700">Templates are temporarily unavailable. Contact the Research Office before submitting.</div>
                    @endforelse
                </div>
            </section>
        </aside>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-5">
                <h3 class="text-base font-black text-gray-900">Proposal package</h3>
                <p class="mt-1 text-xs text-gray-500">Fields marked required must be completed before submission.</p>
            </div>

            <form action="{{ route('faculty.topics') }}" method="POST" enctype="multipart/form-data" class="space-y-7 p-6">
                @csrf

                @if ($errors->submission->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                        <p class="font-black">Please review the following:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-4">@foreach ($errors->submission->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="research_call_id" class="block text-xs font-black uppercase tracking-wider text-gray-500">Research Call <span class="text-red-600">Required</span></label>
                        <select id="research_call_id" name="research_call_id" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                            <option value="">Select an open call</option>
                            @foreach ($activeCalls as $call)<option value="{{ $call->id }}" @selected(old('research_call_id') == $call->id)>{{ $call->title }} ({{ $call->academic_year }})</option>@endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label for="title" class="block text-xs font-black uppercase tracking-wider text-gray-500">Proposal Title <span class="text-red-600">Required</span></label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Enter the research title">
                    </div>
                </div>

                <div>
                    <div class="flex flex-col gap-1 border-b border-gray-100 pb-4 sm:flex-row sm:items-end sm:justify-between">
                        <div><h4 class="text-sm font-black text-gray-900">Required documents</h4><p class="mt-1 text-xs text-gray-500">Upload each completed document in its designated field.</p></div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Maximum 25 MB per file</p>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        @foreach ($packageInputs as $packageInput)
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 {{ $packageInput['multiple'] ? 'lg:col-span-2' : '' }}">
                                <label for="{{ $packageInput['name'] }}" class="block text-xs font-black text-gray-800">{{ $packageInput['label'] }} <span class="text-red-600">Required</span></label>
                                <p class="mt-1 text-[11px] leading-4 text-gray-500">{{ $packageInput['help'] }}</p>
                                <input id="{{ $packageInput['name'] }}" name="{{ $packageInput['name'] }}{{ $packageInput['multiple'] ? '[]' : '' }}" type="file" accept="{{ $packageInput['accept'] }}" @if ($packageInput['multiple']) multiple @endif required class="mt-3 block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-bold file:text-gray-700">
                                @error($packageInput['multiple'] ? $packageInput['name'].'.*' : $packageInput['name'], 'submission')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                    <a href="{{ route('faculty.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-5 py-3 text-xs font-bold text-gray-600 transition hover:bg-gray-50">Cancel</a>
                    <button type="submit" class="rounded-xl bg-red-600 px-6 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-red-700">Submit proposal package</button>
                </div>
            </form>
        </section>
    </div>

    <section class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-5">
            <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-600">Initial screening process</p>
            <h3 class="mt-2 text-lg font-black text-gray-900">What happens after you submit</h3>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">This summarizes the institutional process for constituent campuses and extension campuses. The portal replaces the email handoffs where possible while preserving the same responsibilities.</p>
        </div>

        <div class="grid gap-px bg-gray-100 md:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['01', 'Faculty proponents', 'Submit the research proposal with every required attachment.', 'Portal submission to the Research Office / RDES Head Office'],
                ['02', 'Research Office / RDES', 'Check document completeness and verify that the correct official forms were used.', 'Incomplete or incorrect documents are returned for correction.'],
                ['03', 'Research Office / Central', 'Prepare the Initial Screening Forms and assign the Central co-evaluator.', 'The Research/RDES Head and assigned co-evaluator conduct screening.'],
                ['04', 'Two evaluators', 'The Research/RDES Head and co-evaluator accomplish the initial screening and return their results.', 'Screening must be completed before a final decision.'],
                ['05', 'VCRDES', 'Check and verify the evaluation result, then sign the Initial Screening Forms.', 'The signed forms are returned to the Research Office / RDES.'],
                ['06', 'Research Office / RDES', 'Average ratings, consolidate comments and suggestions, and communicate the result.', 'Results and required corrections are sent to the proponents.'],
                ['07', 'Faculty proponents', 'Revise and resubmit when comments require changes.', 'The revised proposal undergoes initial screening again with the same evaluators.'],
                ['08', 'Final evaluation', 'Only proposals that pass initial screening and comply with all comments proceed to LREC.', 'Initial screening is a required gate, not the final evaluation.'],
            ] as [$number, $role, $action, $remark])
                <article class="bg-white p-5">
                    <span class="text-[10px] font-black tracking-wider text-red-600">STEP {{ $number }}</span>
                    <h4 class="mt-2 text-sm font-black text-gray-900">{{ $role }}</h4>
                    <p class="mt-2 text-xs leading-5 text-gray-600">{{ $action }}</p>
                    <p class="mt-3 border-t border-gray-100 pt-3 text-[11px] leading-4 text-gray-400">{{ $remark }}</p>
                </article>
            @endforeach
        </div>

        <div class="border-t border-amber-200 bg-amber-50 px-6 py-4 text-xs leading-5 text-amber-900">
            <span class="font-black">Important:</span> Every proposal must pass Initial Screening and comply with all evaluator comments before presentation to the Local Research Evaluation Committee (LREC) for final evaluation.
        </div>
    </section>
</x-app-layout>
