<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-black text-2xl text-gray-900 tracking-tight">
                Research Head Dashboard
            </h2>
            <p class="text-xs text-gray-500 mt-1">
                Review incoming faculty proposals and update their evaluation status.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        @php
            $reviewableTopics = $topics->whereIn('status', ['pending', 'resubmitted']);
            $revisionTopics = $topics->where('status', 'revision_requested');
            $approvedTopics = $topics->where('status', 'approved');
            $rejectedTopics = $topics->where('status', 'rejected');
        @endphp

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Ready for Review</span>
                <span class="mt-1 block text-3xl font-black text-amber-700">{{ $reviewableTopics->count() }}</span>
            </div>
            <div class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Awaiting Revision</span>
                <span class="mt-1 block text-3xl font-black text-blue-700">{{ $revisionTopics->count() }}</span>
            </div>
            <div class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Approved</span>
                <span class="mt-1 block text-3xl font-black text-green-700">{{ $approvedTopics->count() }}</span>
            </div>
            <div class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Rejected</span>
                <span class="mt-1 block text-3xl font-black text-red-700">{{ $rejectedTopics->count() }}</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200/60 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-6">
                <h3 class="text-base font-bold text-gray-900">All Topic Proposals</h3>
                <p class="mt-0.5 text-xs text-gray-400">Review each topic and estimated proposal budget before finalizing a decision.</p>
            </div>

            @forelse ($topics as $topic)
                <div class="grid gap-4 border-b border-gray-100 p-5 last:border-b-0 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-bold text-gray-900">{{ $topic->title }}</h4>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider
                                {{ $topic->status === 'approved' ? 'bg-green-50 text-green-700' : '' }}
                                {{ $topic->status === 'rejected' ? 'bg-red-50 text-red-700' : '' }}
                                {{ $topic->status === 'pending' ? 'bg-amber-50 text-amber-700' : '' }}
                                {{ $topic->status === 'revision_requested' ? 'bg-blue-50 text-blue-700' : '' }}
                                {{ $topic->status === 'resubmitted' ? 'bg-purple-50 text-purple-700' : '' }}">
                                {{ str_replace('_', ' ', $topic->status) }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $topic->description ?: 'No description provided.' }}</p>
                        <p class="mt-2 text-xs font-bold text-gray-700">
                            Estimated Budget: {{ $topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Not provided' }}
                        </p>
                        <p class="mt-2 text-[11px] font-medium text-gray-400">
                            Submitted by {{ $topic->user->name }} on {{ $topic->created_at->format('M d, Y h:i A') }}
                        </p>

                        @if ($topic->reviews->isNotEmpty())
                            <details class="mt-3 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">
                                <summary class="cursor-pointer text-xs font-bold text-gray-600">
                                    Review history ({{ $topic->reviews->count() }})
                                </summary>
                                <div class="mt-3 space-y-3">
                                    @foreach ($topic->reviews as $review)
                                        <div class="border-l-2 border-gray-200 pl-3">
                                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-600">
                                                {{ str_replace('_', ' ', $review->decision) }}
                                            </p>
                                            <p class="mt-0.5 text-[11px] text-gray-400">
                                                {{ $review->reviewer?->name ?? 'Former research head' }} · {{ $review->created_at->format('M d, Y h:i A') }}
                                            </p>
                                            @if ($review->comment)
                                                <p class="mt-1 whitespace-pre-line text-xs leading-relaxed text-gray-600">{{ $review->comment }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>

                    <div class="flex flex-col gap-2 lg:w-96">
                        <a href="{{ route('topics.download', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50">
                            Download latest document
                        </a>

                        @if (in_array($topic->status, ['pending', 'resubmitted'], true))
                            @php($isCurrentReviewForm = (string) old('reviewing_topic_id') === (string) $topic->id)
                            <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" class="space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="reviewing_topic_id" value="{{ $topic->id }}">
                                <select name="status" required class="block w-full rounded-xl border-gray-200 text-xs font-bold text-gray-700 shadow-sm focus:border-red-600 focus:ring-red-600">
                                    <option value="" selected disabled>Choose decision</option>
                                    <option value="approved" @selected($isCurrentReviewForm && old('status') === 'approved')>Approve topic and budget</option>
                                    <option value="revision_requested" @selected($isCurrentReviewForm && old('status') === 'revision_requested')>Request revision</option>
                                    <option value="rejected" @selected($isCurrentReviewForm && old('status') === 'rejected')>Reject proposal</option>
                                </select>
                                <textarea name="comment" rows="3" maxlength="5000" class="block w-full rounded-xl border-gray-200 text-xs text-gray-700 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Review comments (required for revision or rejection)">{{ $isCurrentReviewForm ? old('comment') : '' }}</textarea>
                                <button type="submit" class="w-full rounded-xl bg-red-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-700">
                                    Submit review
                                </button>
                            </form>
                        @elseif ($topic->status === 'revision_requested')
                            <span class="inline-flex items-center justify-center rounded-xl bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700">
                                Waiting for faculty revision
                            </span>
                        @else
                            <span class="inline-flex items-center justify-center rounded-xl bg-gray-100 px-3 py-2 text-xs font-bold text-gray-500">
                                Finalized
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <h4 class="text-sm font-bold text-gray-800">No proposals submitted yet</h4>
                    <p class="mt-1 text-xs text-gray-400">Faculty proposal uploads will appear here.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
