<div class="space-y-6" data-report-review-queue>
    <section aria-label="Reports awaiting review" class="overflow-hidden rounded-xl border border-brand bg-brand text-white dark:border-red-900">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/20 px-5 py-4">
            <div><h2 class="text-lg font-bold">Reports awaiting review</h2><p class="mt-1 text-sm text-red-100">Submitted reports, ready for your decision.</p></div>
            <p class="text-3xl font-bold tabular-nums">{{ $pendingByType->sum() }}<span class="sr-only"> awaiting review</span></p>
        </div>
        <div class="grid divide-y divide-white/20 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            @foreach (\App\Services\ResearchHeadReportQueue::TYPES as $key => $label)
                <a wire:navigate href="{{ route('research_head.report-reviews.index', ['type' => $key, 'status' => 'pending']) }}" class="flex min-h-16 items-center justify-between gap-3 px-5 py-4 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-white" aria-label="{{ $label }}: {{ $pendingByType[$key] ?? 0 }} awaiting review">
                    <span class="text-sm font-semibold">{{ $label }}</span><span class="rounded-lg border border-white/30 px-2.5 py-1 text-base font-bold tabular-nums">{{ $pendingByType[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <form method="GET" action="{{ route('research_head.report-reviews.index') }}" class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_200px_210px_auto]">
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Project or faculty
            <input name="search" type="search" maxlength="200" value="{{ $search }}" placeholder="Search project or faculty name" class="mt-2 block h-11 w-full rounded-lg border-slate-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white">
        </label>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Report type
            <select name="type" class="mt-2 block h-11 w-full rounded-lg border-slate-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="">All report types</option>@foreach (\App\Services\ResearchHeadReportQueue::TYPES as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach</select>
        </label>
        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300">Review status
            <select name="status" class="mt-2 block h-11 w-full rounded-lg border-slate-300 text-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="all" @selected($status === 'all')>All review statuses</option>@foreach (\App\Services\ResearchHeadReportQueue::STATUSES as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select>
        </label>
        <div class="flex gap-2"><button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-brand px-5 text-sm font-bold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Filter</button><a href="{{ route('research_head.report-reviews.index') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Reset</a></div>
    </form>
    <section aria-labelledby="report-results-heading">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><div class="border-l-4 border-brand pl-3"><h2 id="report-results-heading" class="font-bold text-slate-900 dark:text-white">{{ \App\Services\ResearchHeadReportQueue::STATUSES[$status] ?? 'All submitted reports' }}</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $status === 'pending' ? 'Oldest submissions first. Open a report to review it in the project record.' : 'Latest submissions first. Open a report to view its review record.' }}</p></div><span class="text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $reports->total() }} {{ \Illuminate\Support\Str::plural('report', $reports->total()) }}</span></div>
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1fr)_160px_150px_155px_170px] gap-4 bg-slate-100 px-5 py-3 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300 xl:grid"><span>Project and faculty</span><span>Report type</span><span>Received</span><span>Review status</span><span class="text-right">Action</span></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($reports as $report)
                    @php
                        $reportUrl = route('topics.show', $report->topic_id).'#'.($report->report_type === 'quarterly' ? 'monitoring-tool-' : 'narrative-report-').$report->id;
                    @endphp
                    <article class="grid min-w-0 gap-3 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_160px_150px_155px_170px] xl:items-center xl:gap-4" data-report-kind="{{ $report->report_type }}">
                        <div class="min-w-0 sm:col-span-2 xl:col-span-1"><h3 class="break-words text-sm font-semibold leading-6 text-slate-900 dark:text-white">{{ $report->title }}</h3><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $report->faculty_name }}</p></div>
                        <div><p class="text-sm text-slate-700 dark:text-slate-200">{{ \App\Services\ResearchHeadReportQueue::TYPES[$report->report_type] }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Report date: {{ $report->report_date ? \Illuminate\Support\Carbon::parse($report->report_date)->format('M j, Y') : 'Not recorded' }}</p></div>
                        <div class="text-sm text-slate-700 dark:text-slate-200">{{ \Illuminate\Support\Carbon::parse($report->received_at)->format('M j, Y') }}<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Carbon::parse($report->received_at)->diffForHumans() }}</p></div>
                        <span class="w-fit whitespace-nowrap rounded-md px-2.5 py-1.5 text-xs font-semibold {{ $report->review_status === 'pending' ? 'bg-brand-wash text-brand dark:bg-rose-950/40 dark:text-rose-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">{{ \App\Services\ResearchHeadReportQueue::STATUSES[$report->review_status] ?? ucfirst($report->review_status) }}</span>
                        <a wire:navigate href="{{ $reportUrl }}" class="inline-flex min-h-11 w-fit items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-brand px-4 text-sm font-bold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:justify-self-end xl:w-full"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v12a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 18V6a2.25 2.25 0 0 1 2.25-2.25Z" /></svg>{{ $report->review_status === 'pending' ? 'Review report' : 'View report' }}</a>
                    </article>
                @empty
                    <div class="px-5 py-12 text-center"><h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $status === 'pending' && $type === '' && $search === '' ? 'No reports awaiting review' : 'No matching reports' }}</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $status === 'pending' && $type === '' && $search === '' ? 'Submitted project reports will appear here when they are ready for review.' : 'Try another report type, review status, or search term.' }}</p></div>
                @endforelse
            </div>
        </div>
        @if ($reports->hasPages())<div class="mt-4">{{ $reports->links() }}</div>@endif
    </section>
</div>
