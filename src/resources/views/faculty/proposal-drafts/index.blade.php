<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Proposal Package Workspace</h2>
                <p class="mt-1 text-xs text-gray-500">Create, resume, and manage your research proposal drafts.</p>
            </div>
            <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Proposal
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">The requested draft action could not be completed.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section aria-labelledby="saved-drafts-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h3 id="saved-drafts-heading" class="text-lg font-black text-gray-900">Saved drafts</h3>
                    <p class="mt-1 text-xs text-gray-500">Drafts stay here until you submit or delete them.</p>
                </div>
                <span class="text-xs font-bold text-gray-500">{{ $proposalDrafts->total() }} {{ Str::plural('draft', $proposalDrafts->total()) }}</span>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($proposalDrafts as $proposalDraft)
                    @php
                        $draftChecklist = app(\App\Support\ProposalDraftReadiness::class)->checklist($proposalDraft);
                        $completeCount = $draftChecklist->where('complete', true)->count();
                    @endphp
                    <article class="flex min-h-64 flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-800">Draft</span>
                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-blue-800">{{ $proposalDraft->user_id === auth()->id() ? 'Owner' : 'Collaborator' }}</span>
                            </div>
                            <span class="text-[11px] text-gray-500">Saved {{ $proposalDraft->updated_at->diffForHumans() }}</span>
                        </div>
                        <h4 class="mt-4 line-clamp-2 text-base font-black leading-6 text-gray-900">{{ $proposalDraft->project_title }}</h4>
                        <p class="mt-2 text-xs leading-5 text-gray-500">{{ $proposalDraft->researchCall->title }}</p>
                        <p class="mt-1 text-[11px] font-semibold text-gray-500">Workspace owner: {{ $proposalDraft->owner->name }}</p>

                        <div class="mt-5" aria-label="{{ $completeCount }} of {{ $draftChecklist->count() }} papers complete">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-600">
                                <span>Package progress</span>
                                <span>{{ $completeCount }}/{{ $draftChecklist->count() }} papers</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-red-600" style="width: {{ $draftChecklist->isEmpty() ? 0 : ($completeCount / $draftChecklist->count()) * 100 }}%"></div>
                            </div>
                        </div>

                        <div class="mt-auto grid gap-2 pt-6 {{ $proposalDraft->user_id === auth()->id() ? 'sm:grid-cols-[1fr_auto]' : '' }}">
                            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">Resume</a>
                            @can('delete', $proposalDraft)
                            <form action="{{ route('faculty.proposal-drafts.destroy', $proposalDraft) }}" method="POST" onsubmit="return confirm('Delete this proposal draft and all staged papers?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Delete</button>
                            </form>
                            @endcan
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center md:col-span-2 xl:col-span-3">
                        <h4 class="text-base font-black text-gray-900">No saved proposal drafts</h4>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-500">Start a proposal package and complete each required paper at your own pace.</p>
                        <a href="{{ route('faculty.proposal-drafts.create') }}" class="mt-5 inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">New Proposal</a>
                    </div>
                @endforelse
            </div>

            @if ($proposalDrafts->hasPages())
                <div class="mt-6">{{ $proposalDrafts->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
