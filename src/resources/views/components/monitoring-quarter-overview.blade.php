@props(['quarterRows'])

@php
    $statusClasses = [
        'reviewed' => 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300',
        'revision_required' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
        'submitted' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        'resubmitted' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
        'prepared' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
        'not_submitted' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        'not_yet_due' => 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    ];
@endphp

<section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
        <p class="text-xs font-black uppercase tracking-wider text-blue-700 dark:text-blue-300">REC-RES-03 quarterly record</p>
        <h4 class="mt-1 text-base font-black text-gray-900 dark:text-white">Monitoring Tool quarters</h4>
        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Each quarter keeps its own official submission history. Select a submitted quarter to open its current PDF and review record.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Quarter</th>
                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Monitoring Tool</th>
                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Submitted</th>
                    <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-400">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($quarterRows->groupBy('year') as $year => $rows)
                    @foreach ($rows as $row)
                        @php
                            $report = $row['report'];
                            $statusClass = $statusClasses[$row['state']] ?? $statusClasses['not_submitted'];
                        @endphp
                        <tr class="{{ $report ? 'bg-white dark:bg-gray-950' : 'bg-gray-50/40 dark:bg-gray-900/30' }}">
                            <td class="px-5 py-4 align-top">
                                @if ($report)
                                    <a href="#monitoring-tool-{{ $report->id }}" class="block font-black text-blue-700 hover:text-blue-900 dark:text-blue-300 dark:hover:text-blue-100">
                                        {{ $row['label'] }} <span class="font-semibold text-gray-400 dark:text-gray-500">{{ $year }}</span>
                                    </a>
                                @else
                                    <p class="font-black text-gray-700 dark:text-gray-200">{{ $row['label'] }} <span class="font-semibold text-gray-400 dark:text-gray-500">{{ $year }}</span></p>
                                @endif
                                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ $row['period'] }}</p>
                            </td>
                            <td class="px-5 py-4 align-top">
                                @if ($report)
                                    <a href="#monitoring-tool-{{ $report->id }}" class="text-xs font-black text-gray-900 hover:text-blue-700 dark:text-white dark:hover:text-blue-300">
                                        Monitoring Tool · {{ $report->version_label }}
                                    </a>
                                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">{{ $report->nextVersion ? 'Historical submission' : 'Current submission' }}</p>
                                @else
                                    <span class="text-xs font-semibold text-gray-400 dark:text-gray-500">No submission</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 align-top text-xs text-gray-600 dark:text-gray-300">
                                {{ $report?->submitted_at?->format('M j, Y g:i A') ?? '—' }}
                            </td>
                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusClass }}">{{ $row['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</section>
