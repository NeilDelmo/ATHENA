<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Research Calls</h2>
                <p class="mt-1 text-xs text-gray-500">Submission periods, rules, categories, and previous-call history.</p>
            </div>
            @if (Auth::user()->isUsingWorkspace('research_head'))
                <a href="{{ route('announcement-images.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white shadow-sm shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Announcement
                </a>
            @endif
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif

        @if (Auth::user()->isUsingWorkspace('research_head'))
            <details class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" @if ($errors->any()) open @endif>
                <summary class="cursor-pointer text-sm font-black text-gray-900">Create a research call</summary>
                <form method="POST" action="{{ route('research-calls.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]" data-research-call-form data-extract-url="{{ route('research-calls.extract-image') }}">
                    @csrf
                    <div class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                    <div><label class="text-xs font-bold text-gray-600">Call title</label><input name="title" value="{{ old('title') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Academic year</label><input name="academic_year" value="{{ old('academic_year') }}" placeholder="2026-2027" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Term / semester</label><input name="term" value="{{ old('term') }}" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Categories</label><input name="categories" value="{{ old('categories') }}" placeholder="Environment, Education, Technology" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><p class="mt-1 text-[11px] text-gray-400">Separate category names with commas.</p></div>
                    <div><label class="text-xs font-bold text-gray-600">Submission starts</label><input type="datetime-local" name="opens_at" value="{{ old('opens_at') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Submission ends</label><input type="datetime-local" name="closes_at" value="{{ old('closes_at') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Active research limit per faculty</label><input type="number" name="max_active_research_per_faculty" value="{{ old('max_active_research_per_faculty', 2) }}" min="1" max="20" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><p class="mt-1 text-[11px] text-gray-400">Maximum projects that may be approved for one faculty researcher in this academic year. Proposal applications remain unlimited.</p></div>
                    <div><span class="text-xs font-bold text-gray-600">Maximum budget (PHP)</span><div class="mt-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-black text-gray-700">PHP {{ number_format($institutionalBudgetCeiling, 2) }}</div><p class="mt-1 text-[11px] text-gray-400">Fixed institutional limit for every research call.</p></div>
                    <div class="md:col-span-2">
                        <div class="mb-3">
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-600">Research workflow dates <span class="font-normal normal-case tracking-normal text-gray-400">Optional</span></h3>
                            <p class="mt-1 text-[11px] text-gray-400">Use the start and end dates shown in the call poster. Leave a date blank when the poster does not specify it.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Initial Evaluation</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="initial_evaluation_start_date" value="{{ old('initial_evaluation_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="initial_evaluation_end_date" value="{{ old('initial_evaluation_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Paper Revisions</p><p class="mt-0.5 text-[10px] text-gray-400">Based on Initial Screening</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="paper_revisions_start_date" value="{{ old('paper_revisions_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="paper_revisions_end_date" value="{{ old('paper_revisions_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Tentative LREC</p><p class="mt-0.5 text-[10px] text-gray-400">Local Research Evaluation</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="lrec_start_date" value="{{ old('lrec_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="lrec_end_date" value="{{ old('lrec_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Implementation</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="implementation_start_date" value="{{ old('implementation_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="implementation_end_date" value="{{ old('implementation_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                        </div>
                    </div>
                    <div class="md:col-span-2"><label class="text-xs font-bold text-gray-600">Description / guidelines</label><textarea name="description" rows="5" class="mt-1 block w-full rounded-xl border-gray-200 text-sm">{{ old('description') }}</textarea><p class="mt-1 text-[11px] text-gray-400">Poster requirements such as “The research proposals must be:” are stored here and can be edited before saving.</p></div>
                    <div><label class="text-xs font-bold text-gray-600">Publication</label><select name="status" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="draft">Save as draft</option><option value="open">Publish and follow schedule</option></select><p class="mt-1 text-[11px] text-gray-400">A published call opens and ends automatically according to the dates above.</p></div>
                    <div class="flex items-end justify-end"><button class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-bold text-white">Create call</button></div>
                            @if ($errors->any())<div class="md:col-span-2 rounded-xl bg-red-50 p-3 text-xs text-red-700">{{ $errors->first() }}</div>@endif
                        </div>
                    </div>

                    <aside class="lg:sticky lg:top-6 lg:self-start">
                        <div class="rounded-2xl border border-dashed border-red-200 bg-red-50/50 p-4 dark:border-red-900/70 dark:bg-red-950/20">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <label class="text-xs font-black text-gray-700 dark:text-slate-200" for="research-call-reference-image">Reference poster image</label>
                                    <p class="mt-1 text-[11px] font-semibold text-gray-500 dark:text-slate-400">Optional · drop, paste, or browse your files</p>
                                </div>
                                <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase tracking-wider text-red-700 shadow-sm dark:bg-slate-900 dark:text-red-300">Poster</span>
                            </div>

                            <label for="research-call-reference-image" data-research-call-image-dropzone tabindex="0" class="group mt-4 flex min-h-[24rem] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-red-200 bg-white p-3 text-center transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-red-900 dark:bg-slate-950 dark:hover:border-red-700 dark:hover:bg-red-950/30">
                                <input id="research-call-reference-image" name="reference_image" type="file" accept="image/jpeg,image/png,image/webp" data-research-call-image class="sr-only">
                                <span data-research-call-image-empty class="flex flex-col items-center gap-3 px-5 py-8">
                                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300">
                                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H8.25M3 16.5l3.75-3.75a2.25 2.25 0 013.182 0L12 13.818m-9 2.682 2.25 2.25m12-7.5 1.5-1.5M15 8.25h.008v.008H15V8.25z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 19.5h6" /></svg>
                                    </span>
                                    <span>
                                        <span class="block text-sm font-black text-gray-700 dark:text-slate-200">Drag and drop poster</span>
                                        <span class="mt-1 block text-xs text-gray-400">or click to choose · Ctrl+V also works</span>
                                    </span>
                                </span>
                                <img data-research-call-image-preview src="" alt="Selected research call poster preview" class="hidden max-h-[34rem] w-full rounded-lg object-contain">
                            </label>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <p data-research-call-image-name class="min-w-0 truncate text-[11px] font-bold text-gray-600 dark:text-slate-300"></p>
                                <button type="button" data-research-call-extract class="shrink-0 rounded-xl bg-red-700 px-3 py-2.5 text-[11px] font-black text-white transition hover:bg-red-800 disabled:cursor-wait disabled:opacity-60">Read image</button>
                            </div>
                            <p class="mt-2 text-[11px] leading-4 text-gray-500 dark:text-slate-400">The image reader can copy the title, requirements, budget, categories, and dates into the fields on the left. Review everything before saving.</p>
                            <p data-research-call-image-status role="status" class="mt-2 hidden text-xs font-semibold text-red-700 dark:text-red-300"></p>
                        </div>
                    </aside>
                </form>
            </details>
        @endif

        @foreach ([['Active calls', $activeCalls], ['Upcoming calls', $upcomingCalls], ['Previous calls', $previousCalls]] as [$heading, $calls])
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4"><h3 class="text-sm font-black text-gray-900">{{ $heading }}</h3><p class="mt-0.5 text-xs text-gray-400">{{ $calls->count() }} {{ Str::plural('call', $calls->count()) }}</p></div>
                <div class="divide-y divide-gray-100">
                    @forelse ($calls as $call)
                        @php
                            $lifecycleStatus = $call->lifecycleStatus();
                        @endphp
                        <article class="p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2"><h4 class="text-sm font-black text-gray-900">{{ $call->title }}</h4><span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $lifecycleStatus === 'open' ? 'bg-green-100 text-green-700' : ($lifecycleStatus === 'scheduled' ? 'bg-blue-100 text-blue-700' : ($lifecycleStatus === 'ended' || $lifecycleStatus === 'closed' ? 'bg-gray-200 text-gray-700' : 'bg-amber-100 text-amber-700')) }}">{{ $lifecycleStatus }}</span></div>
                                    <p class="mt-1 text-xs font-semibold text-gray-500">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</p>
                                     <p class="mt-3 max-w-3xl whitespace-pre-line text-xs leading-5 text-gray-500">{{ $call->description ?: 'No additional guidelines.' }}</p>
                                     @if ($call->reference_image_path)
                                         <img src="{{ route('research-calls.reference-image', $call) }}" alt="Reference poster for {{ $call->title }}" class="mt-4 max-h-72 w-auto rounded-xl border border-gray-100 object-contain shadow-sm">
                                     @endif
                                    <div class="mt-3 flex flex-wrap gap-2">@foreach ($call->categories as $category)<span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700">{{ $category->name }}</span>@endforeach</div>
                                </div>
                                <dl class="grid min-w-72 grid-cols-2 gap-3 text-xs">
                                    <div><dt class="font-bold text-gray-400">Submission starts</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->opens_at->format('M d, Y') }} &middot; {{ $call->opens_at->format('h:i A') }}</dd></div>
                                    <div><dt class="font-bold text-gray-400">Submission ends</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->closes_at->format('M d, Y') }} &middot; {{ $call->closes_at->format('h:i A') }}</dd></div>
                                    <div><dt class="font-bold text-gray-400">Research workload limit</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->max_active_research_per_faculty }} approved projects per faculty</dd></div>
                                     <div><dt class="font-bold text-gray-400">Maximum budget</dt><dd class="mt-1 font-semibold text-gray-700">PHP {{ number_format($call->budgetCeiling(), 2) }}</dd></div>
                                     <div><dt class="font-bold text-gray-400">Submissions</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->topics_count }}</dd></div>
                                     @foreach ([
                                         ['label' => 'Initial Evaluation', 'start' => $call->initial_evaluation_start_date, 'end' => $call->initial_evaluation_end_date],
                                         ['label' => 'Paper Revisions', 'start' => $call->paper_revisions_start_date, 'end' => $call->paper_revisions_end_date],
                                         ['label' => 'Tentative LREC', 'start' => $call->lrec_start_date, 'end' => $call->lrec_end_date],
                                         ['label' => 'Implementation', 'start' => $call->implementation_start_date, 'end' => $call->implementation_end_date],
                                     ] as $milestone)
                                         @if ($milestone['start'] || $milestone['end'])
                                             <div><dt class="font-bold text-gray-400">{{ $milestone['label'] }}</dt><dd class="mt-1 font-semibold text-gray-700">{{ $milestone['start']?->format('M d, Y') ?: 'Not set' }}@if ($milestone['end']) – {{ $milestone['end']->format('M d, Y') }}@endif</dd></div>
                                         @endif
                                     @endforeach
                                 </dl>
                            </div>
                            @if (Auth::user()->isUsingWorkspace('research_head'))
                                @php
                                    $canReopen = $call->status === 'closed' && $call->closes_at->isFuture();
                                    $nextStatus = $call->status === 'open' ? 'closed' : 'open';
                                    $actionLabel = match (true) {
                                        $call->status === 'draft' => 'Publish schedule',
                                        $call->status === 'open' && $lifecycleStatus !== 'ended' => 'Close early',
                                        $canReopen => 'Reopen call',
                                        default => null,
                                    };
                                @endphp
                                @if ($actionLabel)
                                    <form method="POST" action="{{ route('research-calls.update-status', $call) }}" class="mt-4 flex justify-end" @if ($call->status === 'open') onsubmit="return confirm('Close this research call before its scheduled end date? Faculty will no longer be able to submit new proposals.')" @endif>
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                                        <button class="rounded-xl px-4 py-2.5 text-xs font-black transition {{ $call->status === 'open' ? 'bg-red-600 text-white hover:bg-red-700' : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}">{{ $actionLabel }}</button>
                                    </form>
                                @elseif ($lifecycleStatus === 'ended')
                                    <p class="mt-4 text-right text-[11px] font-semibold text-gray-400">The submission period ended automatically.</p>
                                @endif
                                <details class="mt-4 rounded-2xl border border-gray-200 bg-gray-50 p-4" @if ($errors->any() && old('_method') === 'PUT') open @endif>
                                    <summary class="cursor-pointer text-xs font-black text-gray-800">Edit research call</summary>
                                    @include('research_calls.partials.form', ['researchCall' => $call])
                                </details>
                            @endif
                        </article>
                    @empty
                        <div class="p-8 text-center text-xs text-gray-400">No {{ strtolower($heading) }} yet.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
