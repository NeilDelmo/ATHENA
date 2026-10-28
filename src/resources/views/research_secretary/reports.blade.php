<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" title="Report reviews" subtitle="Check submitted reports and send reviewed reports to the Research Head."><x-slot name="actions"><x-back-link href="{{ route('research_secretary.dashboard') }}">Back to dashboard</x-back-link></x-slot></x-page-header></x-slot>
    <div class="secretary-workspace" data-secretary-workspace="reports">
        <nav data-report-review-filters class="min-w-0 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1.5 dark:border-slate-800 dark:bg-slate-900" aria-label="Filter report reviews">
            <div class="flex w-max min-w-full gap-1">
            @foreach (['' => 'All pending', 'monitoring' => 'Monitoring Tools', 'progress' => 'Progress Reports', 'returned' => 'Returned by Research Head'] as $type => $label)
                @php($active = request('type', '') === $type)
                <a href="{{ route('research_secretary.reports.index', $type ? ['type' => $type] : []) }}" @if ($active) aria-current="page" @endif
                    @class([
                        'inline-flex min-h-11 shrink-0 items-center justify-center whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                        'bg-gray-900 text-white shadow-sm dark:bg-white dark:text-gray-950' => $active,
                        'bg-white text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $active,
                    ])>{{ $label }}</a>
            @endforeach
            </div>
        </nav>
        <x-secretary-report-queue :reports="$reportReviewTasks" />
    </div>
</x-app-layout>
