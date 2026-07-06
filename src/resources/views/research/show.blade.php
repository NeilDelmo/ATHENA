<x-app-layout>
    @php
        $statusClass = match ($topic->status) {
            'approved' => 'bg-green-50 text-green-700',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-blue-50 text-blue-700',
            'resubmitted' => 'bg-purple-50 text-purple-700',
            default => 'bg-amber-50 text-amber-700',
        };

        $statusDescription = match ($topic->status) {
            'approved' => 'This research proposal has been approved.',
            'rejected' => 'This proposal received a final rejection decision.',
            'revision_requested' => 'The Research Head requested changes before another review.',
            'resubmitted' => 'The revised proposal is waiting for another review.',
            'expert_review' => 'The assigned co-evaluator is completing Initial Screening.',
            'for_final_decision' => 'Initial Screening is complete and the Research Head is preparing the decision.',
            default => 'This proposal is waiting for its initial review.',
        };
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <a href="{{ route('research.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 transition hover:text-red-600">
                <span aria-hidden="true">&larr;</span> Back to research list
            </a>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $topic->title }}</h2>
                    <p class="mt-1 text-xs text-gray-500">Submitted by {{ $topic->user->name }}</p>
                </div>
                <span class="self-start rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-wider {{ $statusClass }}">{{ str_replace('_', ' ', $topic->status) }}</span>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-gray-900">Proposal overview</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-600">{{ $topic->description ?: 'No description was provided for this proposal.' }}</p>
            </section>

            @include('topics.partials.version-history', ['topic' => $topic, 'expanded' => true])

            <section class="rounded-2xl border border-gray-200/60 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-gray-900">Review history</h3>
                    <p class="mt-0.5 text-xs text-gray-400">Decisions and feedback from the Research Head.</p>
                </div>
                <div class="p-6">
                    @forelse ($topic->reviews as $review)
                        <div class="relative border-l-2 border-gray-100 pb-6 pl-5 last:pb-0">
                            <span class="absolute -left-[5px] top-1 h-2 w-2 rounded-full bg-red-500 ring-4 ring-white"></span>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-black uppercase tracking-wider text-gray-700">{{ str_replace('_', ' ', $review->decision) }}</p>
                                <time class="text-[11px] text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</time>
                            </div>
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $review->reviewer?->name ?? 'Former Research Head' }}</p>
                            @if ($review->comment)
                                <p class="mt-2 whitespace-pre-line rounded-xl bg-gray-50 p-3 text-sm leading-6 text-gray-600">{{ $review->comment }}</p>
                            @endif
                        </div>
                    @empty
                        <div class="py-6 text-center">
                            <p class="text-sm font-bold text-gray-700">No review yet</p>
                            <p class="mt-1 text-xs text-gray-400">Feedback and decisions will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-gray-200/60 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Current status</h3>
                <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusClass }}">{{ str_replace('_', ' ', $topic->status) }}</span>
                <p class="mt-3 text-xs leading-5 text-gray-500">{{ $statusDescription }}</p>
            </section>

            <section class="rounded-2xl border border-gray-200/60 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Research details</h3>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total project cost</dt>
                        <dd class="mt-1 text-lg font-black text-gray-900">{{ $topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Not provided' }}</dd>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Research call and category</dt>
                        <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->researchCall->title }}</dd>
                        <dd class="mt-0.5 text-xs text-gray-400">@if ($topic->category){{ $topic->category->name }} · @endif{{ $topic->estimated_duration_months }} months</dd>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Researcher</dt>
                        <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->user->name }}</dd>
                        <dd class="mt-0.5 text-xs text-gray-400">{{ $topic->user->email }}</dd>
                    </div>
                    <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-4">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Submitted</dt>
                            <dd class="mt-1 text-xs font-semibold text-gray-600">{{ $topic->created_at->format('M d, Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Updated</dt>
                            <dd class="mt-1 text-xs font-semibold text-gray-600">{{ $topic->updated_at->format('M d, Y') }}</dd>
                        </div>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-gray-200/60 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Document</h3>
                <p class="mt-2 text-xs leading-5 text-gray-500">Downloads the latest submitted version of this proposal.</p>
                <a href="{{ route('topics.download', $topic) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800">Download latest document</a>
            </section>

            @if ($topic->signed_approval_path)
                <section class="rounded-2xl border border-green-200 bg-green-50 p-5">
                    <h3 class="text-xs font-black uppercase tracking-wider text-green-700">Signed approval</h3>
                    <p class="mt-2 text-xs leading-5 text-green-800">The Research Head has issued the signed authorization to proceed.</p>
                    <a href="{{ route('topics.approval', $topic) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-green-700 px-4 py-2.5 text-xs font-bold text-white">Download signed approval</a>
                </section>
            @endif

            @if ($topic->status === 'revision_requested' && $topic->user_id === Auth::id())
                <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                    <h3 class="text-sm font-bold text-blue-900">Revision required</h3>
                    <p class="mt-1 text-xs leading-5 text-blue-700">Review the feedback above, then upload your revised proposal from the Faculty Dashboard.</p>
                    <a href="{{ route('faculty.dashboard') }}" class="mt-3 inline-flex rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-blue-800">Go to revision form</a>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
