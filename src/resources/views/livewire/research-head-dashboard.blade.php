<div class="space-y-5 text-gray-950 dark:text-white" wire:key="research-head-dashboard">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><h3 class="text-sm font-bold">Proposal pipeline</h3><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Workload, deadlines, and review progress at a glance.</p></div>
        <label class="flex items-center gap-2 text-xs font-semibold">Research call
            <select wire:model.live="call" class="max-w-[240px] rounded-lg border-gray-300 py-2 text-xs dark:border-gray-700 dark:bg-gray-950"><option value="">All research calls</option>@foreach($calls as $researchCall)<option value="{{ $researchCall->id }}">{{ $researchCall->title }}</option>@endforeach</select>
        </label>
    </div>
    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([['awaiting_review', 'Awaiting your review'], ['revision_requested', 'Awaiting faculty revision'], ['deadlines', 'Deadlines in 14 days'], ['approved', 'Active projects']] as [$key, $label])
            @if ($key === 'deadlines')
                <a href="#dashboard-deadlines" class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-950"><span class="block text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span><span class="mt-1 block text-2xl font-bold">{{ $summary[$key] }}</span></a>
            @else
                <button type="button" wire:click="setPipeline('{{ $key }}')" aria-pressed="{{ $pipeline === $key ? 'true' : 'false' }}" class="rounded-xl border bg-white px-4 py-3 text-left shadow-sm hover:border-red-400 dark:bg-gray-950 {{ $pipeline === $key ? 'border-[#7A0019] ring-1 ring-[#7A0019]' : 'border-gray-200 dark:border-gray-800' }}"><span class="block text-xs text-gray-500 dark:text-gray-400">{{ $label }}</span><span class="mt-1 block text-2xl font-bold">{{ $summary[$key] }}</span></button>
            @endif
        @endforeach
    </div>
    <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <livewire:dashboard-calendar :call-id="ctype_digit($call) ? (int) $call : null" />
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-950" aria-label="Needs attention">
            <h3 class="text-sm font-bold">Needs attention</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Oldest waiting items first. Waiting time alone does not mean overdue.</p>
            <div class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($analytics['attention'] as $item)
                    <a href="{{ route('topics.show', $item['topic']) }}#proposal-review" class="flex items-start justify-between gap-3 py-2.5 hover:text-[#7A0019] dark:hover:text-red-300">
                        <span class="min-w-0"><span class="block truncate text-xs font-semibold">{{ $item['topic']->title }}</span><span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400">{{ $stageLabels[$item['topic']->status] }} · {{ $item['topic']->user?->name }}</span>@if($item['past_revision_deadline'])<span class="mt-1 block text-[11px] font-semibold text-red-700 dark:text-red-300">Past call revision deadline</span>@endif</span>
                        <span class="shrink-0 text-right text-xs font-semibold">{{ $item['days'] !== null ? $item['days'].'d' : '—' }}<span class="mt-1 block text-[10px] font-normal text-gray-500 dark:text-gray-400">{{ $item['days'] !== null ? $item['basis'] : 'Timing unavailable' }}</span></span>
                    </a>
                @empty
                    <p class="py-4 text-xs text-gray-500">No proposals waiting for action.</p>
                @endforelse
            </div>
            <div id="dashboard-deadlines" class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                <h4 class="text-xs font-semibold">Official deadlines · next 14 days</h4>
                @forelse ($deadlines->take(3) as $deadline)
                    <a href="{{ $deadline['url'] }}" class="mt-2 block text-xs hover:underline"><span class="font-semibold">{{ $deadline['title'] }}</span><span class="block truncate text-gray-500 dark:text-gray-400">{{ $deadline['context'] }} · {{ $deadline['display_at'] }}</span></a>
                @empty
                    <p class="mt-2 text-xs text-gray-500">No official deadlines in the next 14 days.</p>
                @endforelse
                @if ($deadlines->count() > 3)<p class="mt-2 text-xs text-gray-500">{{ $deadlines->count() - 3 }} more in the calendar.</p>@endif
            </div>
        </section>
    </div>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-950" aria-label="Submission trends">
            <div class="flex flex-wrap justify-between gap-2"><h3 class="text-sm font-bold">Submissions by week</h3><p class="text-xs text-gray-500 dark:text-gray-400">{{ $analytics['weeks']->sum('count') }} submissions · 8 weeks</p></div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Includes initial submissions and resubmissions. Current week is partial.</p>
            <div class="mt-4 flex h-28 items-end gap-2" role="img" aria-label="Weekly submissions: {{ $analytics['weeks']->map(fn ($week) => $week['label'].': '.$week['count'])->join('; ') }}">
                @foreach ($analytics['weeks'] as $week)
                    <div class="flex h-full min-w-0 flex-1 flex-col justify-end text-center" title="{{ $week['start'] }} to {{ $week['end'] }}: {{ $week['count'] }} submissions">
                        <span class="mb-1 text-[10px] font-semibold">{{ $week['count'] }}</span>
                        <div class="mx-auto w-full max-w-10 rounded-t bg-[#7A0019] dark:bg-red-400" style="height: {{ max(2, ($week['count'] / $analytics['chartMax']) * 70) }}px"></div>
                        <span class="mt-2 text-[10px] text-gray-500 dark:text-gray-400">{{ $week['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs text-gray-600 dark:text-gray-300">Last 4 weeks: <strong>{{ $analytics['recentTotal'] }}</strong> · Previous 4 weeks: <strong>{{ $analytics['previousTotal'] }}</strong></p>
        </section>
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-950" aria-label="Review delays">
            <h3 class="text-sm font-bold">Where reviews slow down</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Average time per completed stage · last 90 days</p>
            @if ($analytics['stageDurations']->isNotEmpty())
                @php
                    $slowest = $analytics['stageDurations']->first();
                @endphp
                <p class="mt-3 text-xs leading-5"><strong>{{ $stageLabels[$slowest->from_status] }}</strong> has the longest recorded average: <strong>{{ number_format($slowest->average_days, 1) }} days</strong>. Check this stage when planning follow-ups.</p>
                <div class="mt-2 max-h-28 space-y-2 overflow-y-auto">
                    @foreach ($analytics['stageDurations'] as $duration)
                        <div class="flex justify-between gap-2 text-xs"><span>{{ $stageLabels[$duration->from_status] }} <span class="text-gray-500">({{ $duration->samples }} completed)</span></span><strong>{{ number_format($duration->average_days, 1) }}d</strong></div>
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-xs leading-5 text-gray-600 dark:text-gray-300">Stage timing starts with new workflow changes. A comparison will appear once a tracked stage is completed.</p>
            @endif
            <p class="mt-3 text-[11px] text-gray-500 dark:text-gray-400">{{ $analytics['unknownTiming'] }} current {{ str('proposal')->plural($analytics['unknownTiming']) }} without a recorded stage start; unknown durations are excluded.</p>
            <button type="button" wire:click="toggleRepeatedRevisions" aria-pressed="{{ $attention === 'repeat' ? 'true' : 'false' }}" class="mt-3 text-xs font-semibold text-[#7A0019] hover:underline dark:text-red-300">{{ $analytics['repeatRevisions'] }} waiting {{ str('proposal')->plural($analytics['repeatRevisions']) }} with 2+ revision requests · {{ $attention === 'repeat' ? 'Clear filter' : 'View' }}</button>
        </section>
    </div>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950" aria-labelledby="received-proposals-heading">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-4 dark:border-gray-800">
            <div><h3 id="received-proposals-heading" class="text-sm font-bold">Received proposal inbox</h3><p class="mt-1 text-xs text-gray-500">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }}{{ $attention === 'repeat' ? ' · Repeated revision requests' : '' }}</p></div>
            <div class="flex flex-wrap items-center gap-2" aria-label="Inbox controls">
                <label class="sr-only" for="proposal-search">Search proposals</label><input id="proposal-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Proposal or faculty…" class="w-44 rounded-lg border-gray-300 py-2 text-xs dark:border-gray-700 dark:bg-gray-900">
                <label class="sr-only" for="proposal-status">Review status</label><select id="proposal-status" wire:model.live="status" class="max-w-44 rounded-lg border-gray-300 py-2 text-xs dark:border-gray-700 dark:bg-gray-900"><option value="">All statuses</option>@foreach($stageLabels + ['approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                @if($search !== '' || $pipeline || $status || $attention)<button wire:click="$set('search', ''); clearPipeline()" type="button" class="px-2 text-xs font-semibold text-[#7A0019] dark:text-red-300">Reset filters</button>@endif
            </div>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($topics as $topic)
                @php
                    $version = $topic->latestVersion;
                    $label = $topic->workflowStatusLabel();
                    $action = match(true) {
                        $topic->isAwaitingNoticeToProceed() => 'Issue Notice to Proceed',
                        $topic->status === 'ready_for_signature' => 'Complete signatures',
                        in_array($topic->status, ['approved', 'rejected']) => 'Open proposal record',
                        default => 'Review proposal',
                    };
                @endphp
                <article class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-900">
                    <div class="min-w-0 flex-1 basis-60"><h4 class="truncate text-sm font-semibold">{{ $topic->title }}</h4><p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $topic->user?->name }} · {{ $topic->researchCall?->title }}</p><p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $version ? 'v'.$version->version_number.' · '.$version->files_count.' files received · '.$version->created_at->format('M j, Y') : 'No submitted version available' }}</p></div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium dark:bg-gray-800">{{ $label }}</span>
                    <a href="{{ route('topics.show', $topic) }}#proposal-review" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold hover:border-[#7A0019] hover:text-[#7A0019] dark:border-gray-700 dark:hover:text-red-300">{{ $action }} &rarr;</a>
                </article>
            @empty
                <p class="p-8 text-center text-sm text-gray-500">No proposals found. Try changing the search or filters.</p>
            @endforelse
        </div>
        @if($topics->hasPages())<div class="border-t border-gray-100 p-4 dark:border-gray-800">{{ $topics->links() }}</div>@endif
    </section>
</div>