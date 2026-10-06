@props(['report'])

@php($evidencedRows = collect($report->accomplishments ?? [])->filter(fn ($row) => count($row['evidence'] ?? []) > 0))

@if ($report->report_type === 'progress' && $evidencedRows->isNotEmpty())
    <section {{ $attributes->class(['space-y-4 print:hidden']) }} data-progress-report-saved-evidence aria-label="Progress Report supporting evidence">
        <div>
            <h5 class="text-lg font-bold text-gray-950 dark:text-white">Supporting evidence</h5>
            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">Attachments for review. These files are excluded from the report preview and downloaded PDF.</p>
        </div>
        @foreach ($evidencedRows as $row)
            <div class="space-y-2">
                <p class="whitespace-pre-line text-sm font-semibold text-gray-800 dark:text-slate-100">{{ $row['objective'] }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($row['evidence'] as $file)
                        <a href="{{ route('project-narrative-reports.evidence', ['topic' => $report->topic_id, 'evidence' => $file['id']]) }}" class="inline-flex min-h-10 max-w-full items-center break-all rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:border-red-300 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">{{ $file['name'] }}</a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>
@endif
