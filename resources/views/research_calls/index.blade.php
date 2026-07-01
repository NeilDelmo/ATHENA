<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900">Research Calls</h2>
            <p class="mt-1 text-xs text-gray-500">Submission periods, rules, categories, and previous-call history.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif

        @role('research_head')
            <details class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" @if ($errors->any()) open @endif>
                <summary class="cursor-pointer text-sm font-black text-gray-900">Create a research call</summary>
                <form method="POST" action="{{ route('research-calls.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div><label class="text-xs font-bold text-gray-600">Call title</label><input name="title" value="{{ old('title') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Academic year</label><input name="academic_year" value="{{ old('academic_year') }}" placeholder="2026-2027" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Term / semester</label><input name="term" value="{{ old('term') }}" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Categories</label><input name="categories" value="{{ old('categories') }}" placeholder="Environment, Education, Technology" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><p class="mt-1 text-[11px] text-gray-400">Separate category names with commas.</p></div>
                    <div><label class="text-xs font-bold text-gray-600">Opens at</label><input type="datetime-local" name="opens_at" value="{{ old('opens_at') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Closes at</label><input type="datetime-local" name="closes_at" value="{{ old('closes_at') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Proposal limit per faculty</label><input type="number" name="max_proposals_per_faculty" value="{{ old('max_proposals_per_faculty', 2) }}" min="1" max="20" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div><label class="text-xs font-bold text-gray-600">Maximum budget (PHP)</label><input type="number" name="maximum_budget" value="{{ old('maximum_budget') }}" min="0" step="0.01" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                    <div class="md:col-span-2"><label class="text-xs font-bold text-gray-600">Description / guidelines</label><textarea name="description" rows="3" class="mt-1 block w-full rounded-xl border-gray-200 text-sm">{{ old('description') }}</textarea></div>
                    <div><label class="text-xs font-bold text-gray-600">Initial status</label><select name="status" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="draft">Draft / upcoming</option><option value="open">Open</option></select></div>
                    <div class="flex items-end justify-end"><button class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-bold text-white">Create call</button></div>
                    @if ($errors->any())<div class="md:col-span-2 rounded-xl bg-red-50 p-3 text-xs text-red-700">{{ $errors->first() }}</div>@endif
                </form>
            </details>
        @endrole

        @foreach ([['Active calls', $activeCalls], ['Upcoming calls', $upcomingCalls], ['Previous calls', $previousCalls]] as [$heading, $calls])
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4"><h3 class="text-sm font-black text-gray-900">{{ $heading }}</h3><p class="mt-0.5 text-xs text-gray-400">{{ $calls->count() }} {{ Str::plural('call', $calls->count()) }}</p></div>
                <div class="divide-y divide-gray-100">
                    @forelse ($calls as $call)
                        <article class="p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2"><h4 class="text-sm font-black text-gray-900">{{ $call->title }}</h4><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ $call->status }}</span></div>
                                    <p class="mt-1 text-xs font-semibold text-gray-500">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</p>
                                    <p class="mt-3 max-w-3xl text-xs leading-5 text-gray-500">{{ $call->description ?: 'No additional guidelines.' }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2">@foreach ($call->categories as $category)<span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700">{{ $category->name }}</span>@endforeach</div>
                                </div>
                                <dl class="grid min-w-72 grid-cols-2 gap-3 text-xs">
                                    <div><dt class="font-bold text-gray-400">Submission period</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->opens_at->format('M d, Y') }} – {{ $call->closes_at->format('M d, Y') }}</dd></div>
                                    <div><dt class="font-bold text-gray-400">Per-faculty limit</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->max_proposals_per_faculty }} proposals</dd></div>
                                    <div><dt class="font-bold text-gray-400">Maximum budget</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->maximum_budget ? 'PHP '.number_format((float) $call->maximum_budget, 2) : 'No stated cap' }}</dd></div>
                                    <div><dt class="font-bold text-gray-400">Submissions</dt><dd class="mt-1 font-semibold text-gray-700">{{ $call->topics_count }}</dd></div>
                                </dl>
                            </div>
                            @role('research_head')
                                <form method="POST" action="{{ route('research-calls.update-status', $call) }}" class="mt-4 flex justify-end gap-2">@csrf @method('PATCH')<select name="status" class="rounded-xl border-gray-200 text-xs"><option value="draft" @selected($call->status === 'draft')>Draft</option><option value="open" @selected($call->status === 'open')>Open</option><option value="closed" @selected($call->status === 'closed')>Closed</option></select><button class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700">Update status</button></form>
                            @endrole
                        </article>
                    @empty
                        <div class="p-8 text-center text-xs text-gray-400">No {{ strtolower($heading) }} yet.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
