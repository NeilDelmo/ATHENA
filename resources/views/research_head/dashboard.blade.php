<x-app-layout>
    <x-slot name="header"><div><h2 class="text-2xl font-black tracking-tight text-gray-900">Research Head Dashboard</h2><p class="mt-1 text-xs text-gray-500">Screen proposals, coordinate subject-expert review, and issue signed decisions.</p></div></x-slot>

    <div class="space-y-6">
        @if (session('success'))<div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><p class="font-bold">The review could not be submitted.</p><p class="mt-1 text-xs">{{ $errors->first() }}</p></div>@endif

        @php
            $screening = $topics->whereIn('status', ['pending', 'resubmitted'])->count();
            $expertReview = $topics->where('status', 'expert_review')->count();
            $finalDecision = $topics->where('status', 'for_final_decision')->count();
            $approved = $topics->where('status', 'approved')->count();
        @endphp
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Awaiting screening', $screening, 'text-amber-700 bg-amber-50'], ['With co-evaluators', $expertReview, 'text-purple-700 bg-purple-50'], ['Screening complete', $finalDecision, 'text-blue-700 bg-blue-50'], ['Approved', $approved, 'text-green-700 bg-green-50']] as [$label, $count, $style])
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p><p class="mt-2 inline-flex rounded-xl px-3 py-1 text-2xl font-black {{ $style }}">{{ $count }}</p></div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-5"><h3 class="text-base font-black text-gray-900">Proposal pipeline</h3><p class="mt-1 text-xs text-gray-400">Expert recommendations are advisory; the Research Head retains the final decision.</p></div>
            <div class="divide-y divide-gray-100">
                @forelse ($topics as $topic)
                    @php
                        $canDecide = in_array($topic->status, ['pending', 'resubmitted', 'for_final_decision'], true);
                        $isCurrent = (string) old('reviewing_topic_id') === (string) $topic->id;
                        $latestFiles = $topic->versions->sortByDesc('version_number')->first()?->files ?? collect();
                    @endphp
                    <article class="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_380px]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2"><h4 class="text-sm font-black text-gray-900">{{ $topic->title }}</h4><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ str_replace('_', ' ', $topic->status) }}</span></div>
                            <p class="mt-1 text-xs font-semibold text-gray-400">{{ $topic->researchCall->title }}@if ($topic->category) · {{ $topic->category->name }}@endif</p>
                            <p class="mt-3 text-xs leading-5 text-gray-600">{{ $topic->description ?: 'No proposal summary provided.' }}</p>
                            <div class="mt-3 flex flex-wrap gap-4 text-xs font-bold text-gray-600"><span>Total cost: PHP {{ number_format((float) $topic->estimated_budget, 2) }}</span><span>Duration: {{ $topic->estimated_duration_months }} months</span><span>Faculty: {{ $topic->user->name }}</span></div>
                            <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('topics.show', $topic) }}" class="inline-flex rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white">Open review workspace</a><a href="{{ route('topics.download', $topic) }}" class="inline-flex rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700">Download latest proposal</a></div>

                            @if ($topic->expertAssignments->isNotEmpty())
                                <div class="mt-4 rounded-xl border border-purple-100 bg-purple-50/50 p-4"><p class="text-[11px] font-black uppercase tracking-wider text-purple-700">Initial Screening co-evaluations</p><div class="mt-3 space-y-3">@foreach ($topic->expertAssignments as $assignment)<div class="border-l-2 border-purple-200 pl-3"><p class="text-xs font-bold text-gray-800">{{ $assignment->expert->name }} · {{ $assignment->status }}</p>@if ($assignment->recommendation)<p class="mt-1 text-[11px] font-black uppercase text-purple-700">{{ str_replace('_', ' ', $assignment->recommendation) }}</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $assignment->comment }}</p>@endif</div>@endforeach</div></div>
                            @endif

                            @if ($topic->reviews->isNotEmpty())
                                <details class="mt-3 rounded-xl bg-gray-50 p-3"><summary class="cursor-pointer text-xs font-bold text-gray-600">Research Head review history ({{ $topic->reviews->count() }})</summary><div class="mt-3 space-y-2">@foreach ($topic->reviews as $review)<div class="border-l-2 border-gray-200 pl-3"><p class="text-[11px] font-bold uppercase text-gray-600">{{ str_replace('_', ' ', $review->decision) }}</p>@if ($review->comment)<p class="mt-1 whitespace-pre-line text-xs text-gray-600">{{ $review->comment }}</p>@endif</div>@endforeach</div></details>
                            @endif

                            @include('topics.partials.version-history', ['topic' => $topic])
                        </div>

                        <div>
                            @if ($canDecide)
                                <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50 p-4">@csrf @method('PATCH')<input type="hidden" name="reviewing_topic_id" value="{{ $topic->id }}">
                                    <label class="block text-xs font-black uppercase tracking-wider text-gray-500">Next action</label>
                                    <select name="status" required class="block w-full rounded-xl border-gray-200 text-xs font-bold"><option value="">Choose an action</option>@if ($topic->status !== 'for_final_decision')<option value="expert_review" @selected($isCurrent && old('status') === 'expert_review')>Send to co-evaluator(s)</option>@endif @if ($topic->status === 'for_final_decision')<option value="approved" @selected($isCurrent && old('status') === 'approved')>Approve after Initial Screening</option>@endif<option value="revision_requested" @selected($isCurrent && old('status') === 'revision_requested')>Request revision</option><option value="rejected" @selected($isCurrent && old('status') === 'rejected')>Reject proposal</option></select>
                                    @include('topics.partials.revision-file-selector', ['files' => $latestFiles])
                                    <div><label class="text-[11px] font-bold text-gray-500">Experts (required when sending for expert review)</label><select name="expert_ids[]" multiple size="{{ min(max($experts->count(), 2), 5) }}" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">@foreach ($experts as $expert)<option value="{{ $expert->id }}">{{ $expert->name }} — {{ $expert->email }}</option>@endforeach</select>@if ($experts->isEmpty())<p class="mt-1 text-[11px] text-red-600">No users currently have the expert role.</p>@endif</div>
                                    <div><label class="text-[11px] font-bold text-gray-500">Signed approval PDF (required for approval)</label><input type="file" name="signed_approval" accept=".pdf" class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs"></div>
                                    <textarea name="comment" rows="4" maxlength="5000" placeholder="Screening notes or decision rationale (required for revision/rejection)" class="block w-full rounded-xl border-gray-200 text-xs">{{ $isCurrent ? old('comment') : '' }}</textarea>
                                    <button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white">Submit action</button>
                                </form>
                            @elseif ($topic->status === 'expert_review')
                                <div class="rounded-xl bg-purple-50 p-4 text-center text-xs font-bold text-purple-700">Waiting for all assigned experts</div>
                            @elseif ($topic->status === 'revision_requested')
                                <div class="rounded-xl bg-blue-50 p-4 text-center text-xs font-bold text-blue-700">Waiting for faculty revision</div>
                            @else
                                <div class="rounded-xl bg-gray-100 p-4 text-center text-xs font-bold text-gray-600">Finalized: {{ str_replace('_', ' ', $topic->status) }}</div>
                                @if ($topic->signed_approval_path)<a href="{{ route('topics.approval', $topic) }}" class="mt-2 flex justify-center rounded-xl bg-green-700 px-4 py-2.5 text-xs font-bold text-white">Download signed approval</a>@endif
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="p-12 text-center"><p class="text-sm font-bold text-gray-700">No proposals submitted yet</p></div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
