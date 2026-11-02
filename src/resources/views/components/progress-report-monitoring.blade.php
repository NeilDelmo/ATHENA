@props(['topic', 'report' => null, 'monitoringReport' => null, 'accomplishments' => []])

@php
    $monitoring = app(\App\Services\ProgressReportMonitoringService::class);
    $source = $report ? $monitoring->forReport($report) : $monitoringReport;
@endphp

@if ($source)
    <section {{ $attributes->class(['space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800 print:hidden']) }} data-progress-report-monitoring aria-label="Linked Monitoring Tool and evidence">
        <div>
            <h5 class="text-base font-bold text-gray-950 dark:text-white">Linked Monitoring Tool · {{ $source->quarter_label }} · Version {{ $source->version_number }}</h5>
            <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">Calculated overall project progress: <strong>{{ $source->progress_percentage }}%</strong></p>
            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">Evidence stays in the project folder and supports this report without another upload. It is excluded from the report PDF.</p>
        </div>
        @foreach ($accomplishments as $row)
            @php($files = $monitoring->evidenceForObjective($topic, $source, (string) ($row['objective'] ?? '')))
            @if ($files !== [])
                <div class="space-y-2">
                    <p class="whitespace-pre-line text-sm font-semibold text-gray-800 dark:text-slate-100">{{ $row['objective'] }}</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($files as $file)
                            @php($url = route($report ? 'project-narrative-reports.evidence' : 'project-progress.evidence', [$topic, $file['id']]))
                            <a href="{{ $url }}" class="inline-flex max-w-full items-center gap-3 rounded-lg border border-gray-300 bg-white p-2 text-sm font-medium text-gray-700 hover:border-red-300 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                                @if (in_array($file['mime_type'], ['image/jpeg', 'image/png'], true))
                                    <img src="{{ $url }}?preview=1" alt="" loading="lazy" width="64" height="64" class="h-16 w-16 shrink-0 rounded object-cover">
                                @endif
                                <span class="break-all">{{ $file['name'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </section>
@endif
