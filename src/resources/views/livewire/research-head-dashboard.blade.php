<div class="space-y-5 text-gray-950 dark:text-white" wire:key="research-head-dashboard">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="text-base font-black tracking-tight">Proposal pipeline</h3>
            <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Workload, deadlines, and review progress at a glance.</p>
        </div>
        <label class="flex items-center gap-2 text-xs font-bold text-gray-600 dark:text-gray-300">Research call
            <select wire:model.live="call" class="max-w-[240px] rounded-full border-rose-100 bg-white py-2 pl-4 pr-8 text-xs font-semibold shadow-bubble-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-red-950 dark:bg-slate-950"><option value="">All research calls</option>@foreach($calls as $researchCall)<option value="{{ $researchCall->id }}">{{ $researchCall->title }}</option>@endforeach</select>
        </label>
    </div>

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        @php
            $statIcons = [
                'awaiting_review' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                'revision_requested' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.862 4.487z',
                'deadlines' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
                'approved' => 'M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z',
            ];
            $statTints = [
                'awaiting_review' => 'bg-rose-50 text-[#7A0019] dark:bg-red-950/60 dark:text-red-300',
                'revision_requested' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300',
                'deadlines' => 'bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-300',
                'approved' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300',
            ];
        @endphp
        @foreach ([['awaiting_review', 'Awaiting your review'], ['revision_requested', 'Awaiting faculty revision'], ['deadlines', 'Deadlines in 14 days'], ['approved', 'Active projects']] as [$key, $label])
            @if ($key === 'deadlines')
                <a href="#dashboard-deadlines" class="group relative overflow-hidden rounded-3xl border border-rose-100 bg-white px-4 py-4 shadow-bubble transition duration-200 hover:-translate-y-0.5 hover:shadow-bubble-lg dark:border-red-950/70 dark:bg-slate-950">
                    <span class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded-2xl {{ $statTints[$key] }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $statIcons[$key] }}" /></svg></span>
                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $label }}</span>
                    <span class="mt-1 block text-3xl font-black tabular-nums tracking-tight">{{ $summary[$key] }}</span>
                </a>
            @else
                <button type="button" wire:click="setPipeline('{{ $key }}')" aria-pressed="{{ $pipeline === $key ? 'true' : 'false' }}" class="group relative overflow-hidden rounded-3xl border px-4 py-4 text-left shadow-bubble transition duration-200 hover:-translate-y-0.5 hover:shadow-bubble-lg {{ $pipeline === $key ? 'border-transparent bg-gradient-to-br from-[#7A0019] to-rose-700 text-white shadow-bubble-lg' : 'border-rose-100 bg-white dark:border-red-950/70 dark:bg-slate-950' }}">
                    <span class="mb-2 inline-flex h-9 w-9 items-center justify-center rounded-2xl {{ $pipeline === $key ? 'bg-white/15 text-white' : $statTints[$key] }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $statIcons[$key] }}" /></svg></span>
                    <span class="block text-xs font-semibold {{ $pipeline === $key ? 'text-rose-100' : 'text-gray-500 dark:text-gray-400' }}">{{ $label }}</span>
                    <span class="mt-1 block text-3xl font-black tabular-nums tracking-tight">{{ $summary[$key] }}</span>
                </button>
            @endif
        @endforeach
    </div>

    <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <livewire:dashboard-calendar :call-id="ctype_digit($call) ? (int) $call : null" />
        <section class="rounded-3xl border border-rose-100 bg-white p-5 shadow-bubble dark:border-red-950/70 dark:bg-slate-950" aria-label="Needs attention">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-2xl bg-rose-50 text-[#7A0019] dark:bg-red-950/60 dark:text-red-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg></span>
                <div>
                    <h3 class="text-sm font-black tracking-tight">Needs attention</h3>
                    <p class="mt-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">Oldest waiting items first. Waiting time alone does not mean overdue.</p>
                </div>
            </div>
            <div class="mt-3 space-y-2">
                @forelse ($analytics['attention'] as $item)
                    <a href="{{ route('topics.show', $item['topic']) }}#proposal-review" class="flex items-center justify-between gap-3 rounded-2xl border border-transparent px-3 py-2.5 transition hover:border-rose-100 hover:bg-rose-50/60 hover:text-[#7A0019] dark:hover:border-red-950 dark:hover:bg-red-950/20 dark:hover:text-red-300">
                        <span class="flex min-w-0 items-center gap-3">
                            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#7A0019] to-rose-500 text-xs font-black text-white shadow-bubble-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($item['topic']->user?->name ?? '·', 0, 1)) }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-bold">{{ $item['topic']->title }}</span>
                                <span class="mt-0.5 block truncate text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $stageLabels[$item['topic']->status] }} · {{ $item['topic']->user?->name }}</span>
                                @if($item['past_revision_deadline'])<span class="mt-1 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black text-[#7A0019] dark:bg-red-950 dark:text-red-300">Past call revision deadline</span>@endif
                            </span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="inline-flex items-center justify-center rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-black tabular-nums text-[#7A0019] ring-1 ring-rose-100 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-900">{{ $item['days'] !== null ? $item['days'].'d' : '—' }}</span>
                            <span class="mt-1 block text-[10px] font-medium text-gray-400 dark:text-gray-500">{{ $item['days'] !== null ? $item['basis'] : 'Timing unavailable' }}</span>
                        </span>
                    </a>
                @empty
                    <p class="rounded-2xl bg-rose-50/60 py-4 text-center text-xs font-semibold text-gray-500 dark:bg-red-950/20">No proposals waiting for action.</p>
                @endforelse
            </div>
            <div id="dashboard-deadlines" class="mt-3 border-t border-rose-100 pt-3 dark:border-red-950/70">
                <h4 class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Official deadlines · next 14 days</h4>
                @forelse ($deadlines->take(3) as $deadline)
                    @php
                        $when = \Illuminate\Support\Carbon::parse($deadline['at']);
                    @endphp
                    <a href="{{ $deadline['url'] }}" class="mt-2 flex items-center gap-3 rounded-2xl px-2 py-1.5 transition hover:bg-rose-50/70 dark:hover:bg-red-950/20">
                        <x-date-chip :date="$when" compact weekday />
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-bold">{{ $deadline['title'] }}</span>
                            <span class="block truncate text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $deadline['context'] }} · {{ $when->diffForHumans(short: true) }}</span>
                        </span>
                    </a>
                @empty
                    <p class="mt-2 rounded-2xl bg-rose-50/60 py-3 text-center text-xs font-semibold text-gray-500 dark:bg-red-950/20">No official deadlines in the next 14 days.</p>
                @endforelse
                @if ($deadlines->count() > 3)<p class="mt-2 text-[11px] font-semibold text-gray-500 dark:text-gray-400">{{ $deadlines->count() - 3 }} more in the calendar.</p>@endif
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <section class="rounded-3xl border border-rose-100 bg-white p-5 shadow-bubble dark:border-red-950/70 dark:bg-slate-950" aria-label="Submission trends">
            <div class="flex flex-wrap justify-between gap-2">
                <h3 class="text-sm font-black tracking-tight">Submissions by week</h3>
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $analytics['weeks']->sum('count') }} submissions · 8 weeks</p>
            </div>
            <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Includes initial submissions and resubmissions. Current week is partial.</p>
            <div class="mt-4 flex h-32 items-end gap-2 rounded-2xl bg-gradient-to-b from-rose-50/70 to-transparent p-3 dark:from-red-950/20" role="img" aria-label="Weekly submissions: {{ $analytics['weeks']->map(fn ($week) => $week['label'].': '.$week['count'])->join('; ') }}">
                @foreach ($analytics['weeks'] as $week)
                    <div class="flex h-full min-w-0 flex-1 flex-col justify-end text-center" title="{{ $week['start'] }} to {{ $week['end'] }}: {{ $week['count'] }} submissions">
                        <span class="mb-1.5 text-[10px] font-black tabular-nums text-gray-600 dark:text-gray-300">{{ $week['count'] }}</span>
                        <div class="mx-auto w-full max-w-8 rounded-full bg-gradient-to-t from-[#7A0019] via-[#9F1239] to-rose-400 shadow-bubble-sm transition duration-200 hover:from-rose-600 hover:to-rose-300" style="height: {{ max(6, ($week['count'] / $analytics['chartMax']) * 72) }}px"></div>
                        <span class="mt-2 text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ $week['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-xs font-medium text-gray-600 dark:text-gray-300">Last 4 weeks: <strong class="rounded-full bg-rose-50 px-2 py-0.5 text-[#7A0019] dark:bg-red-950/60 dark:text-red-300">{{ $analytics['recentTotal'] }}</strong> · Previous 4 weeks: <strong class="rounded-full bg-gray-100 px-2 py-0.5 dark:bg-slate-900">{{ $analytics['previousTotal'] }}</strong></p>
        </section>
        <section class="rounded-3xl border border-rose-100 bg-white p-5 shadow-bubble dark:border-red-950/70 dark:bg-slate-950" aria-label="Review delays">
            <h3 class="text-sm font-black tracking-tight">Where reviews slow down</h3>
            <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">Average time per completed stage · last 90 days</p>
            @if ($analytics['stageDurations']->isNotEmpty())
                @php
                    $slowest = $analytics['stageDurations']->first();
                    $slowestDays = (float) $analytics['stageDurations']->max('average_days');
                @endphp
                <p class="mt-3 text-xs leading-5"><strong>{{ $stageLabels[$slowest->from_status] }}</strong> has the longest recorded average: <strong class="rounded-full bg-rose-50 px-2 py-0.5 text-[#7A0019] dark:bg-red-950/60 dark:text-red-300">{{ number_format($slowest->average_days, 1) }} days</strong>. Check this stage when planning follow-ups.</p>
                <div class="mt-3 max-h-28 space-y-2.5 overflow-y-auto pr-1">
                    @foreach ($analytics['stageDurations'] as $duration)
                        <div>
                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                <span class="truncate font-semibold">{{ $stageLabels[$duration->from_status] }} <span class="font-medium text-gray-400">({{ $duration->samples }} completed)</span></span>
                                <strong class="shrink-0 tabular-nums">{{ number_format($duration->average_days, 1) }}d</strong>
                            </div>
                            <div class="mt-1 h-2 w-full rounded-full bg-rose-50 dark:bg-red-950/40"><div class="h-2 rounded-full bg-gradient-to-r from-[#7A0019] to-rose-400" style="width: {{ max(4, ($duration->average_days / max($slowestDays, 1)) * 100) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-xs leading-5 text-gray-600 dark:text-gray-300">Stage timing starts with new workflow changes. A comparison will appear once a tracked stage is completed.</p>
            @endif
            <p class="mt-3 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $analytics['unknownTiming'] }} current {{ str('proposal')->plural($analytics['unknownTiming']) }} without a recorded stage start; unknown durations are excluded.</p>
            <button type="button" wire:click="toggleRepeatedRevisions" aria-pressed="{{ $attention === 'repeat' ? 'true' : 'false' }}" class="mt-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-black transition {{ $attention === 'repeat' ? 'bg-[#7A0019] text-white shadow-bubble-sm' : 'bg-rose-50 text-[#7A0019] hover:bg-rose-100 dark:bg-red-950/60 dark:text-red-300 dark:hover:bg-red-950' }}">{{ $analytics['repeatRevisions'] }} waiting {{ str('proposal')->plural($analytics['repeatRevisions']) }} with 2+ revision requests · {{ $attention === 'repeat' ? 'Clear filter' : 'View' }}</button>
        </section>
    </div>

    <section class="overflow-hidden rounded-3xl border border-rose-100 bg-white shadow-bubble dark:border-red-950/70 dark:bg-slate-950" aria-labelledby="received-proposals-heading">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rose-100 bg-gradient-to-r from-rose-50/80 to-transparent p-4 dark:border-red-950/70 dark:from-red-950/20">
            <div>
                <h3 id="received-proposals-heading" class="text-sm font-black tracking-tight">Received proposal inbox</h3>
                <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }}{{ $attention === 'repeat' ? ' · Repeated revision requests' : '' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2" aria-label="Inbox controls">
                <label class="sr-only" for="proposal-search">Search proposals</label><input id="proposal-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Proposal or faculty…" class="w-44 rounded-full border-rose-100 py-2 pl-4 pr-4 text-xs font-semibold shadow-bubble-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-red-950 dark:bg-slate-900">
                <label class="sr-only" for="proposal-status">Review status</label><select id="proposal-status" wire:model.live="status" class="max-w-44 rounded-full border-rose-100 py-2 pl-4 pr-8 text-xs font-semibold shadow-bubble-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-red-950 dark:bg-slate-900"><option value="">All statuses</option>@foreach($stageLabels + ['approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                @if($search !== '' || $pipeline || $status || $attention)<button wire:click="$set('search', ''); clearPipeline()" type="button" class="rounded-full bg-rose-50 px-3 py-2 text-xs font-black text-[#7A0019] transition hover:bg-rose-100 dark:bg-red-950/60 dark:text-red-300">Reset filters</button>@endif
            </div>
        </div>
        <div class="divide-y divide-rose-50 dark:divide-red-950/50">
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
                    $badgeTone = match ($topic->status) {
                        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-900',
                        'rejected' => 'bg-stone-100 text-stone-500 ring-stone-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700',
                        'revision_requested' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-900',
                        'resubmitted' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:ring-sky-900',
                        'ready_for_signature' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-950/60 dark:text-violet-300 dark:ring-violet-900',
                        default => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-900',
                    };
                @endphp
                <article class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5 transition hover:bg-rose-50/50 dark:hover:bg-red-950/20">
                    <div class="flex min-w-0 flex-1 basis-60 items-center gap-3">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#7A0019] to-rose-500 text-sm font-black text-white shadow-bubble-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($topic->user?->name ?? '·', 0, 1)) }}</span>
                        <div class="min-w-0">
                            <h4 class="truncate text-sm font-bold">{{ $topic->title }}</h4>
                            <p class="mt-0.5 truncate text-xs font-medium text-gray-500 dark:text-gray-400">{{ $topic->user?->name }} · {{ $topic->researchCall?->title }}</p>
                            <p class="mt-1 flex items-center gap-2 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                @if ($version)
                                    <x-date-chip :date="$version->created_at" compact />
                                    <span>v{{ $version->version_number }} · {{ $version->files_count }} files received</span>
                                @else
                                    <span>No submitted version available</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-[11px] font-black ring-1 {{ $badgeTone }}">{{ $label }}</span>
                    <a href="{{ route('topics.show', $topic) }}#proposal-review" class="rounded-full border border-rose-200 bg-white px-4 py-2 text-xs font-black text-gray-800 transition hover:border-[#7A0019] hover:bg-[#7A0019] hover:text-white dark:border-red-950 dark:bg-slate-950 dark:text-gray-100 dark:hover:border-red-500 dark:hover:bg-red-900">{{ $action }} &rarr;</a>
                </article>
            @empty
                <p class="p-8 text-center text-sm font-semibold text-gray-500">No proposals found. Try changing the search or filters.</p>
            @endforelse
        </div>
        @if($topics->hasPages())<div class="border-t border-rose-100 p-4 dark:border-red-950/70">{{ $topics->links() }}</div>@endif
    </section>
</div>
