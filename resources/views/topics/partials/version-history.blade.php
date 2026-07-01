@php($expanded = $expanded ?? false)

<section class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <details @if ($expanded) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
            <div>
                <h3 class="text-sm font-black text-gray-900">Proposal version history</h3>
                <p class="mt-0.5 text-xs text-gray-500">Immutable document and proposal snapshots for auditing.</p>
            </div>
            <span class="whitespace-nowrap rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600">
                {{ $topic->versions->count() }} {{ Str::plural('version', $topic->versions->count()) }}
            </span>
        </summary>

        <div class="border-t border-gray-100">
            @forelse ($topic->versions->sortByDesc('version_number') as $version)
                <article class="border-b border-gray-100 p-5 last:border-b-0">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-700">Version {{ $version->version_number }}</span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ $version->submission_type }}</span>
                                @if ($loop->first)
                                    <span class="rounded-full bg-green-50 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-green-700">Latest</span>
                                @endif
                            </div>
                            <h4 class="mt-3 text-sm font-black text-gray-900">{{ $version->title }}</h4>
                            <p class="mt-1 text-xs text-gray-500">
                                Submitted by {{ $version->submitter?->name ?? 'Former user' }} on {{ $version->created_at->format('M d, Y h:i A') }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] font-semibold text-gray-500">
                                <span>Budget: PHP {{ number_format((float) $version->estimated_budget, 2) }}</span>
                                <span>Duration: {{ $version->estimated_duration_months }} months</span>
                                <span>File: {{ $version->original_filename }}</span>
                                @if ($version->file_size)<span>{{ number_format($version->file_size / 1024, 1) }} KB</span>@endif
                            </div>
                            @if ($version->checksum)
                                <p class="mt-2 break-all font-mono text-[10px] text-gray-400" title="SHA-256 checksum">SHA-256: {{ $version->checksum }}</p>
                            @endif
                        </div>
                        <a href="{{ route('topics.versions.download', [$topic, $version]) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50">Download version {{ $version->version_number }}</a>
                    </div>
                </article>
            @empty
                <div class="p-6 text-center">
                    <p class="text-sm font-bold text-gray-700">No version records available</p>
                    <p class="mt-1 text-xs text-gray-500">Legacy proposals will begin version tracking on their next upload.</p>
                </div>
            @endforelse
        </div>
    </details>
</section>
