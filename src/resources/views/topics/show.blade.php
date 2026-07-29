<x-app-layout>
    @php
        $statusClass = match ($topic->status) {
            'approved' => 'bg-green-50 text-green-700',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-blue-50 text-blue-700',
            'resubmitted', 'expert_review' => 'bg-purple-50 text-purple-700',
            default => 'bg-amber-50 text-amber-700',
        };
        $backRoute = Auth::user()->isUsingWorkspace('research_head')
            ? route('research_head.dashboard')
            : (Auth::user()->isUsingWorkspace('expert') ? route('expert.dashboard') : route('faculty.dashboard'));
        $canDecide = Auth::user()->isUsingWorkspace('research_head') && in_array($topic->status, ['pending', 'resubmitted', 'for_final_decision'], true);
        $isResearchHead = Auth::user()->isUsingWorkspace('research_head');
        $isFacultyWorkspace = Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']);
        $canAskAthenaAboutProposal = $topic->user_id === Auth::id() && $isFacultyWorkspace;
        $resubmissionErrors = $errors->getBag('resubmission');
        $headUploadErrors = $errors->getBag('headUpload');
        $reviewTabHash = $isResearchHead ? 'review-and-upload-files' : 'review-and-submit';
        $initialTopicTab = $resubmissionErrors->any() || $headUploadErrors->any()
            ? 'review'
            : (in_array(session('topic_tab'), ['details', 'review', 'history'], true) ? session('topic_tab') : null);
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <a href="{{ $backRoute }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 transition hover:text-red-600">&larr; Back to dashboard</a>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $topic->title }}</h2>
                    <p class="mt-1 text-xs text-gray-500">Proposal #{{ $topic->id }} &middot; {{ $topic->user->name }} &middot; {{ $topic->researchCall?->title ?? 'Research proposal' }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($draftHistoryCount > 0)
                        <a href="{{ route('topics.draft-history.index', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-[11px] font-black text-gray-700 shadow-sm transition hover:bg-gray-50">Draft history ({{ $draftHistoryCount }})</a>
                    @endif
                    @if ($canAskAthenaAboutProposal)
                        <button type="button" @click="$store.researchAssistant.openWithContext({{ $topic->id }})" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3 py-2 text-[11px] font-black text-red-700 shadow-sm transition hover:bg-red-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" />
                            </svg>
                            Ask Athena about this proposal
                        </button>
                    @endif
                    <span class="rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-wider {{ $statusClass }}">{{ str_replace('_', ' ', $topic->status) }}</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="{
            activeTopicTab: @js($initialTopicTab) || (
                ['#review-and-submit', '#review-and-upload-files', '#submit-revision'].includes(window.location.hash)
                    ? 'review'
                    : window.location.hash === '#version-history'
                        ? 'history'
                        : 'details'
            ),
            setTopicTab(tab, hash) {
                this.activeTopicTab = tab;
                window.location.hash = hash;
            },
            syncTopicTab() {
                this.activeTopicTab = ['#review-and-submit', '#review-and-upload-files', '#submit-revision'].includes(window.location.hash)
                    ? 'review'
                    : window.location.hash === '#version-history'
                        ? 'history'
                        : 'details';
            },
        }"
        @hashchange.window="syncTopicTab()"
    >
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif
        @if ($errors->any() || $resubmissionErrors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-bold">The action could not be completed.</p>
                <p class="mt-1 text-xs">{{ $resubmissionErrors->first() ?: $errors->first() }}</p>
            </div>
        @endif

        <div class="overflow-x-auto border-b border-gray-200" role="tablist" aria-label="Proposal workspace sections">
            <nav class="flex min-w-max gap-6">
                <button id="proposal-details-tab-button" type="button" role="tab" aria-controls="proposal-details-tab" :aria-selected="activeTopicTab === 'details'" @click="setTopicTab('details', 'proposal-details')" :class="activeTopicTab === 'details' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h11.5A2.25 2.25 0 0 1 20 5.25v13.5A2.25 2.25 0 0 1 17.75 21H6.25A2.25 2.25 0 0 1 4 18.75V5.25Z" /><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5" /></svg>
                    Proposal details
                </button>
                <button id="proposal-review-tab-button" type="button" role="tab" aria-controls="proposal-review-tab" :aria-selected="activeTopicTab === 'review'" @click="setTopicTab('review', '{{ $reviewTabHash }}')" :class="activeTopicTab === 'review' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v14.25H4.5V6a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" d="M8.25 9.5h7.5M8.25 13h5.25" /></svg>
                    {{ $isResearchHead ? 'Review & Upload Files' : 'Review & Submit' }}
                </button>
                <button id="version-history-tab-button" type="button" role="tab" aria-controls="version-history-tab" :aria-selected="activeTopicTab === 'history'" @click="setTopicTab('history', 'version-history')" :class="activeTopicTab === 'history' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Version history
                </button>
            </nav>
        </div>

        <section id="proposal-details-tab" x-show="activeTopicTab === 'details'" x-cloak role="tabpanel" aria-labelledby="proposal-details-tab-button">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section id="submitted-files" aria-labelledby="submitted-files-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-wider text-red-600">Received package</p>
                            <h3 id="submitted-files-heading" class="mt-1 text-base font-black text-gray-900">Submitted proposal files</h3>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($latestVersion)
                                    Version {{ $latestVersion->version_number }} submitted by {{ $latestVersion->submitter?->name ?? $topic->user->name }} on {{ $latestVersion->created_at->format('M j, Y g:i A') }}.
                                @else
                                    No submitted version is available.
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wider {{ $availableSubmittedFileIds->count() === $submittedFiles->count() && $submittedFiles->isNotEmpty() ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800' }}">
                                {{ $availableSubmittedFileIds->count() }}/{{ $submittedFiles->count() }} PDFs available
                            </span>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse ($submittedFiles as $file)
                            @php
                                $fileAvailable = $availableSubmittedFileIds->contains($file->id);
                                $fileViewable = $viewableSubmittedFileIds->contains($file->id);
                            @endphp
                            <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $fileAvailable ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-400' }} text-[10px] font-black">PDF</span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-black text-gray-900">{{ $file->label() }}</h4>
                                            @if (! $fileAvailable)
                                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-red-700">File unavailable</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 break-all text-xs font-semibold text-gray-600">{{ $file->original_filename }}</p>
                                        <p class="mt-1 text-[11px] text-gray-400">{{ $file->file_size ? \Illuminate\Support\Number::fileSize($file->file_size) : 'Size unavailable' }}@if ($file->is_carried_forward) &middot; Carried forward from an earlier version @endif</p>
                                    </div>
                                </div>

                                <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                    @if ($fileViewable)
                                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $file]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:flex-none">View PDF</a>
                                    @endif
                                    @if ($fileAvailable)
                                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $file]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 sm:flex-none">Download PDF</a>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="p-8 text-center">
                                <p class="text-sm font-black text-gray-800">No individual submitted files are available</p>
                                <p class="mt-1 text-xs text-gray-500">Legacy proposals may only provide a combined proposal download.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                <section aria-labelledby="research-details-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 id="research-details-heading" class="text-xs font-black uppercase tracking-wider text-gray-400">Research details</h3>
                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt class="text-[11px] font-bold uppercase text-gray-400">Total project cost</dt>
                            <dd class="mt-1 text-lg font-black text-gray-900">PHP {{ number_format($displayProjectCost, 2) }}</dd>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            <dt class="text-[11px] font-bold uppercase text-gray-400">Duration</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->estimated_duration_months }} months</dd>
                        </div>
                        @if ($topic->category)
                            <div class="border-t border-gray-100 pt-3">
                                <dt class="text-[11px] font-bold uppercase text-gray-400">Category</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->category->name }}</dd>
                            </div>
                        @endif
                    </dl>
                    <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-xs leading-5 text-gray-500">{{ $topic->description ?: 'No proposal summary provided.' }}</p>
                </section>
            </div>
        </section>

        <section id="proposal-review-tab" x-show="activeTopicTab === 'review'" x-cloak role="tabpanel" aria-labelledby="proposal-review-tab-button" class="space-y-5">
            @if ($isResearchHead && $headUploadWorkspace)
                <x-research-head-file-workspace :topic="$topic" :workspace="$headUploadWorkspace" />
            @endif

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-red-600">Proposal review</p>
                    <h3 class="mt-1 text-sm font-black text-gray-900">Review and decision timeline</h3>
                    <p class="mt-1 text-xs text-gray-500">Research Head decisions and assigned co-evaluator recommendations.</p>
                </div>
                <div class="space-y-5 p-6">
                    @forelse ($topic->reviews as $review)
                        <div class="border-l-2 border-red-200 pl-4">
                            <div class="flex flex-wrap justify-between gap-2"><p class="text-xs font-black uppercase text-gray-700">{{ str_replace('_', ' ', $review->decision) }}</p><time class="text-[11px] text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</time></div>
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $review->reviewer?->name ?? 'Former Research Head' }}</p>
                            @if ($review->comment)<p class="mt-2 whitespace-pre-line rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600">{{ $review->comment }}</p>@endif
                            @if ($review->fileRevisions->isNotEmpty())
                                <div class="mt-2 space-y-2">
                                    @foreach ($review->fileRevisions as $fileRevision)
                                        @php($annotationVersion = $topic->versions->firstWhere('id', $fileRevision->file?->proposal_version_id))
                                        <div class="rounded-xl border px-3 py-2 text-xs {{ $fileRevision->resolved_at ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
                                            <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-black">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}</span><span class="text-[9px] font-black uppercase">{{ $fileRevision->resolved_at ? 'Resolved' : 'Revision required' }}</span></div>
                                            <p class="mt-0.5 text-[10px] opacity-75">{{ $fileRevision->original_filename }}</p>
                                            @if ($fileRevision->revision_note)<p class="mt-1 leading-5">{{ $fileRevision->revision_note }}</p>@endif
                                            @if ($fileRevision->annotations->isNotEmpty() && $annotationVersion && $fileRevision->file)
                                                <a href="{{ route('topics.versions.files.annotations.index', [$topic, $annotationVersion, $fileRevision->file]) }}" class="mt-2 inline-flex rounded-lg bg-amber-600 px-3 py-1.5 text-[10px] font-black text-white hover:bg-amber-700">View {{ $fileRevision->annotations->count() }} highlighted comment(s)</a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-center text-xs text-gray-400">No Research Head decision has been recorded.</p>
                    @endforelse

                    @foreach ($topic->expertAssignments as $assignment)
                        <div class="border-l-2 border-purple-200 pl-4">
                            <div class="flex flex-wrap justify-between gap-2"><p class="text-xs font-black uppercase text-purple-700">Co-evaluation &middot; {{ str_replace('_', ' ', $assignment->status) }}</p><time class="text-[11px] text-gray-400">{{ ($assignment->reviewed_at ?: $assignment->created_at)->format('M d, Y h:i A') }}</time></div>
                            <p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $assignment->expert->name }}</p>
                            @if ($assignment->recommendation)<p class="mt-2 text-[11px] font-black uppercase text-purple-700">{{ str_replace('_', ' ', $assignment->recommendation) }}</p><p class="mt-1 whitespace-pre-line rounded-xl bg-purple-50/50 p-3 text-xs leading-5 text-gray-600">{{ $assignment->comment }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($canDecide)
                <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    @csrf @method('PATCH')
                    <input type="hidden" name="redirect_to" value="topic">
                    <h3 class="text-sm font-black text-gray-900">Research Head action</h3>
                    @if ($screeningTemplates->isNotEmpty())<div class="rounded-xl bg-purple-50 p-3 text-xs leading-5 text-purple-800"><p class="font-black">Initial Screening form</p><div class="mt-2 flex flex-wrap gap-2">@foreach ($screeningTemplates as $template)<a href="{{ route('proposal-templates.download', $template) }}" class="rounded-lg bg-purple-700 px-3 py-2 text-[11px] font-bold text-white">Download {{ $template->name }}</a>@endforeach</div></div>@endif
                    <select name="status" required class="block w-full rounded-xl border-gray-200 text-xs font-bold"><option value="">Choose an action</option>@if ($topic->status !== 'for_final_decision')<option value="expert_review">Send to co-evaluator(s)</option>@endif @if ($topic->status === 'for_final_decision')<option value="approved">Approve after Initial Screening</option>@endif<option value="revision_requested">Request revision</option><option value="rejected">Reject proposal</option></select>
                    @include('topics.partials.revision-file-selector', ['files' => $latestVersion?->files ?? collect()])
                    <div><label class="text-[11px] font-bold text-gray-500">Assigned co-evaluator(s)</label><select name="expert_ids[]" multiple size="{{ min(max($experts->count(), 2), 5) }}" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">@foreach ($experts as $expert)<option value="{{ $expert->id }}">{{ $expert->name }} - {{ $expert->email }}</option>@endforeach</select></div>
                    <x-file-dropzone
                        id="signed_approval"
                        name="signed_approval"
                        label="Signed approval PDF"
                        accept=".pdf"
                        data-topic-file-dropzone="signed_approval"
                    />
                    <textarea name="comment" rows="4" maxlength="5000" placeholder="Decision rationale (required for revision or rejection)" class="block w-full rounded-xl border-gray-200 text-xs"></textarea>
                    <button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white">Submit action</button>
                </form>
            @elseif (Auth::user()->isUsingWorkspace('research_head'))
                <div class="rounded-2xl bg-gray-100 p-5 text-center text-xs font-bold text-gray-600">No Research Head action is available while this proposal is {{ str_replace('_', ' ', $topic->status) }}.</div>
            @endif

            @if ($expertAssignment)
                <section class="rounded-2xl border border-purple-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-black text-gray-900">Co-evaluator recommendation</h3>
                    @if ($screeningTemplates->isNotEmpty())<div class="mt-3 rounded-xl bg-purple-50 p-3 text-xs leading-5 text-purple-800"><p class="font-black">Use the official Initial Screening form.</p><div class="mt-2 flex flex-wrap gap-2">@foreach ($screeningTemplates as $template)<a href="{{ route('proposal-templates.download', $template) }}" class="rounded-lg bg-purple-700 px-3 py-2 text-[11px] font-bold text-white">Download form</a>@endforeach</div></div>@endif
                    @if ($expertAssignment->status === 'pending')
                        <form method="POST" action="{{ route('expert.assignments.submit', $expertAssignment) }}" class="mt-3 space-y-3">@csrf @method('PATCH')<input type="hidden" name="redirect_to" value="topic"><select name="recommendation" required class="block w-full rounded-xl border-gray-200 text-xs font-bold"><option value="">Choose recommendation</option><option value="recommend_approval">Recommend approval</option><option value="recommend_revision">Recommend revision</option><option value="recommend_rejection">Recommend rejection</option></select><textarea name="comment" rows="5" required maxlength="5000" placeholder="Explain your assessment." class="block w-full rounded-xl border-gray-200 text-xs"></textarea><button class="w-full rounded-xl bg-purple-700 px-4 py-2.5 text-xs font-bold text-white">Submit recommendation</button></form>
                    @else
                        <p class="mt-3 text-xs font-black uppercase text-purple-700">{{ str_replace('_', ' ', $expertAssignment->recommendation) }}</p><p class="mt-2 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $expertAssignment->comment }}</p>
                    @endif
                </section>
            @endif

            @if ($isFacultyWorkspace && $topic->status === 'revision_requested' && $topic->user_id === Auth::id())
                <form id="submit-revision" action="{{ route('faculty.topics.resubmit', $topic) }}" method="POST" enctype="multipart/form-data" data-proposal-confirm data-confirm-title="Upload this revision to the Research Head?" data-confirm-text="Your updated metadata and selected files will be saved as a new version. Unchanged files will carry forward automatically. Continue?" data-confirm-button="Save and submit revision" data-confirm-icon="question" class="space-y-4 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                    @csrf @method('PATCH')
                    <input type="hidden" name="redirect_to" value="topic">
                    <input type="hidden" name="topic_tab" value="review">
                    @if ($topic->revisionDraft)
                        <input type="hidden" name="revision_draft_id" value="{{ $topic->revisionDraft->id }}">
                    @endif
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-blue-700">Revision workspace</p>
                        <h3 class="mt-1 text-sm font-black text-gray-900">Submit revision</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500">Update the metadata and upload only changed files. Unchanged files carry forward automatically.</p>
                    </div>

                    @if ($pendingFileRevisions->isNotEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <p class="text-[11px] font-black uppercase tracking-wider text-amber-800">Required replacements</p>
                            <div class="mt-2 space-y-2">
                                @foreach ($pendingFileRevisions as $fileRevision)
                                    <div class="text-xs text-amber-900"><span class="font-black">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}:</span> {{ $fileRevision->original_filename }}@if ($fileRevision->revision_note)<p class="mt-0.5 pl-2 text-[11px] text-amber-700">{{ $fileRevision->revision_note }}</p>@endif @if ($fileRevision->annotations->isNotEmpty() && $latestVersion && $fileRevision->file)<a href="{{ route('topics.versions.files.annotations.index', [$topic, $latestVersion, $fileRevision->file]) }}" class="mt-1 inline-flex rounded-lg bg-amber-600 px-3 py-1.5 text-[10px] font-black text-white hover:bg-amber-700">View highlighted comments ({{ $fileRevision->annotations->count() }})</a>@endif</div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @php($requiredRevisionTypes = $pendingFileRevisions->pluck('document_type')->unique())
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-[11px] font-bold text-gray-500">Project title<input name="title" value="{{ old('title', $topic->title) }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Project title"></label>
                        <label class="block text-[11px] font-bold text-gray-500">Total project cost<input name="estimated_budget" type="number" min="0" max="{{ $topic->researchCall?->budgetCeiling() ?? 0 }}" step="0.01" value="{{ old('estimated_budget', $displayProjectCost) }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Total project cost"></label>
                        <label class="block text-[11px] font-bold text-gray-500 md:col-span-2">Description<textarea name="description" rows="3" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Description">{{ old('description', $topic->description) }}</textarea></label>
                        <label class="block text-[11px] font-bold text-gray-500">Duration in months<input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Duration in months"></label>
                        <label class="block text-[11px] font-bold text-gray-500">What changed in this version?<textarea name="change_summary" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Revision summary">{{ old('change_summary') }}</textarea></label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ([['detailed_proposal', 'Detailed proposal', '.doc,.docx,.pdf'], ['work_plan', 'Work plan', '.doc,.docx,.pdf'], ['line_item_budget', 'Line-item budget', '.doc,.docx,.pdf'], ['expense_breakdown', 'Expense breakdown', '.xls,.xlsx'], ['gad_checklist', 'GAD checklist', '.doc,.docx,.pdf']] as [$name, $label, $accept])
                            @php($stagedFile = $stagedRevisionFiles->get($name))
                            <x-file-dropzone
                                id="revision_{{ $name }}"
                                name="{{ $name }}"
                                :label="$label"
                                :accept="$accept"
                                :required="$requiredRevisionTypes->contains($name) && ! $stagedFile"
                                data-topic-file-dropzone="{{ $name }}"
                            >
                                @if ($stagedFile)<span class="mt-1 block rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-[10px] font-black text-green-800">Automatically uploaded: {{ $stagedFile->original_filename }}</span>@endif
                            </x-file-dropzone>
                        @endforeach
                        @php($stagedCurriculumVitae = $stagedRevisionFiles->get('curriculum_vitae'))
                        <x-file-dropzone
                            id="revision_curricula_vitae"
                            name="curricula_vitae[]"
                            label="Curriculum vitae files"
                            accept=".doc,.docx,.pdf"
                            :multiple="true"
                            :required="$requiredRevisionTypes->contains('curriculum_vitae') && ! $stagedCurriculumVitae"
                            data-topic-file-dropzone="curricula_vitae"
                        >
                            @if ($stagedCurriculumVitae)<span class="mt-1 block rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-[10px] font-black text-green-800">Automatically uploaded: {{ $stagedCurriculumVitae->original_filename }}</span>@endif
                        </x-file-dropzone>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2 sm:w-auto">Save and submit revision</button>
                    </div>
                </form>
            @endif
        </section>

        <section id="version-history-tab" x-show="activeTopicTab === 'history'" x-cloak role="tabpanel" aria-labelledby="version-history-tab-button" class="space-y-5">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4"><p class="text-[10px] font-black uppercase tracking-wider text-red-600">Compare package versions</p><h3 class="mt-1 text-sm font-black text-gray-900">Version comparison</h3><p class="mt-1 text-xs text-gray-500">Metadata and file changes in the latest revision.</p></div>
                @if ($previousVersion && $latestVersion)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-left text-xs">
                            <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-wider text-gray-400"><tr><th class="px-5 py-3">Field</th><th class="px-5 py-3">Version {{ $previousVersion->version_number }}</th><th class="px-5 py-3">Version {{ $latestVersion->version_number }}</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($comparisonRows as $row)
                                    <tr class="{{ $row['changed'] ? 'bg-amber-50/50' : '' }}"><th class="px-5 py-3 font-black text-gray-700">{{ $row['label'] }} @if ($row['changed'])<span class="ml-1 text-[9px] uppercase text-amber-700">Changed</span>@endif</th><td class="max-w-xs px-5 py-3 text-gray-500">{{ $row['previous'] }}</td><td class="max-w-xs px-5 py-3 font-semibold text-gray-700">{{ $row['latest'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-100 p-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Files changed in version {{ $latestVersion->version_number }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($latestVersion->files->where('is_carried_forward', false)->whereNotIn('document_type', [\App\Models\ProposalVersionFile::TYPE_COMMENT_RESPONSE, \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD]) as $file)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">{{ $file->label() }}</span>
                            @empty
                                <span class="text-xs text-gray-400">No package files were replaced.</span>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="p-8 text-center"><p class="text-sm font-bold text-gray-700">Initial version</p><p class="mt-1 text-xs text-gray-400">A comparison will appear after the first revision.</p></div>
                @endif
            </section>

            @include('topics.partials.version-history', ['topic' => $topic, 'expanded' => true])

            @if ($topic->status === 'approved' && (Auth::user()->isUsingWorkspace('research_head') || $topic->user_id === Auth::id()))
                @include('topics.partials.project-monitoring')
            @endif
        </section>

        @if ($topic->signed_approval_path)
            <a href="{{ route('topics.approval', $topic) }}" class="flex justify-center rounded-xl bg-green-700 px-4 py-3 text-xs font-bold text-white">Download signed approval</a>
        @endif
    </div>
</x-app-layout>
