<section id="notice-to-proceed" class="rounded-2xl border {{ $topic->isMonitoringAvailable() ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50' }} p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-black uppercase tracking-wider {{ $topic->isMonitoringAvailable() ? 'bg-green-700 text-white' : 'bg-amber-100 text-amber-800' }}">
                {{ $topic->isMonitoringAvailable() ? 'Issued' : 'Waiting for issuance' }}
            </span>
            <h3 class="mt-3 text-xl font-black text-gray-950">Notice to Proceed</h3>

            @if ($topic->isMonitoringAvailable())
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-700">
                    Issued {{ $topic->notice_to_proceed_issued_at->format('M j, Y g:i A') }}
                    @if ($topic->noticeIssuer)
                        by {{ $topic->noticeIssuer->name }}
                    @endif.
                    This proposal is now an active research project and monitoring is open.
                </p>
                <p class="mt-1 break-all text-xs font-semibold text-gray-500">{{ $topic->notice_to_proceed_original_filename }}</p>
            @else
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-700">
                    The proposal papers are approved, but this project cannot enter monitoring yet. The faculty member becomes a Faculty Researcher for this project only after the Research Head issues the completed and signed notice.
                </p>
            @endif
        </div>

        @if ($topic->isMonitoringAvailable())
            <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                <a href="{{ route('topics.notice-to-proceed.download', $topic) }}" class="inline-flex items-center justify-center rounded-xl bg-green-700 px-4 py-3 text-sm font-black text-white transition hover:bg-green-800">Download notice</a>
                @if (! $isResearchHead && $topic->user_id === Auth::id())
                    <a href="{{ route('workspace.select') }}" class="inline-flex items-center justify-center rounded-xl border border-green-300 bg-white px-4 py-3 text-sm font-black text-green-800 transition hover:bg-green-100">Open researcher workspace</a>
                @endif
            </div>
        @endif
    </div>

    @if ($isResearchHead)
        <div class="mt-5 border-t {{ $topic->isMonitoringAvailable() ? 'border-green-200' : 'border-amber-200' }} pt-5">
            @if ($topic->isMonitoringAvailable())
                <details>
                    <summary class="cursor-pointer text-sm font-black text-gray-800">Replace the issued notice</summary>
            @endif

            <form method="POST" action="{{ route('research_head.topics.notice-to-proceed.store', $topic) }}" enctype="multipart/form-data" class="{{ $topic->isMonitoringAvailable() ? 'mt-4' : '' }} grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                @csrf
                <label class="block text-sm font-bold text-gray-800">
                    {{ $topic->isMonitoringAvailable() ? 'Replacement Notice to Proceed PDF' : 'Completed and signed Notice to Proceed PDF' }}
                    <input name="notice_to_proceed" type="file" accept=".pdf" required class="mt-2 block w-full rounded-xl border border-gray-300 bg-white p-3 text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-bold file:text-gray-700 hover:file:bg-gray-200">
                    <span class="mt-2 block text-xs font-normal leading-5 text-gray-600">PDF only, up to 25 MB. Issuing it immediately unlocks Faculty Researcher access and project monitoring.</span>
                    @error('notice_to_proceed')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                </label>
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-black text-white transition hover:bg-red-800">
                    {{ $topic->isMonitoringAvailable() ? 'Replace notice' : 'Issue notice and open monitoring' }}
                </button>
            </form>

            @if ($topic->isMonitoringAvailable())
                </details>
            @endif
        </div>
    @endif
</section>
