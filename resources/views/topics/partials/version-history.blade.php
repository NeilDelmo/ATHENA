@php($expanded = $expanded ?? false)

<section class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <details @if ($expanded) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
            <div>
                <h3 class="text-sm font-black text-gray-900">Proposal version history</h3>
                <p class="mt-0.5 text-xs text-gray-500">Immutable proposal-package snapshots with file-level change tracking.</p>
            </div>
            <span class="whitespace-nowrap rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600">
                {{ $topic->versions->count() }} {{ Str::plural('version', $topic->versions->count()) }}
            </span>
        </summary>

        <div class="border-t border-gray-100">
            @forelse ($topic->versions->sortByDesc('version_number') as $version)
                <article class="border-b border-gray-100 p-5 last:border-b-0">
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
                                <span>Total cost: PHP {{ number_format((float) $version->estimated_budget, 2) }}</span>
                                <span>Duration: {{ $version->estimated_duration_months }} months</span>
                            </div>

                            @if ($version->change_summary)
                                <div class="mt-3 rounded-xl bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-800">
                                    <span class="font-black">Revision summary:</span> {{ $version->change_summary }}
                                </div>
                            @endif

                            @if ($version->files->isNotEmpty())
                                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                                    @foreach ($version->files as $file)
                                        <div class="flex flex-col gap-2 border-b border-gray-100 p-3 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-xs font-black text-gray-800">{{ $file->label() }}</p>
                                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $file->is_carried_forward ? 'bg-gray-100 text-gray-500' : ($version->version_number > 1 ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700') }}">
                                                        {{ $file->is_carried_forward ? 'Unchanged' : ($version->version_number > 1 ? 'Changed' : 'Submitted') }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 truncate text-[11px] text-gray-500" title="{{ $file->original_filename }}">{{ $file->original_filename }} @if ($file->file_size) - {{ number_format($file->file_size / 1024, 1) }} KB @endif</p>
                                                @if ($file->checksum)
                                                    <p class="mt-1 font-mono text-[9px] text-gray-400" title="SHA-256: {{ $file->checksum }}">ID {{ substr($file->checksum, 0, 12) }}</p>
                                                @endif
                                            </div>
                                            <a href="{{ route('topics.versions.files.download', [$topic, $version, $file]) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-200 px-3 py-1.5 text-[11px] font-bold text-gray-700 transition hover:bg-gray-50">Download</a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-4 flex flex-col gap-2 rounded-xl border border-gray-200 p-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-xs font-black text-gray-800">Detailed Proposal</p>
                                        <p class="mt-1 truncate text-[11px] text-gray-500">{{ $version->original_filename }} @if ($version->file_size) - {{ number_format($version->file_size / 1024, 1) }} KB @endif</p>
                                    </div>
                                    <a href="{{ route('topics.versions.download', [$topic, $version]) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-gray-200 px-3 py-1.5 text-[11px] font-bold text-gray-700 transition hover:bg-gray-50">Download</a>
                                </div>
                            @endif
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
