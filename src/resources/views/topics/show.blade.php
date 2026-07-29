<x-app-layout>
    @php
        $statusClass = match ($topic->status) {
            'approved' => 'bg-gray-950 text-white dark:bg-white dark:text-gray-950',
            'ready_for_signature' => 'bg-red-50 text-red-800',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
            'resubmitted', 'expert_review', 'for_final_decision' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        };
        $statusLabel = match ($topic->status) {
            'approved' => 'Approved',
            'ready_for_signature' => 'Ready for signature',
            'rejected' => 'Rejected',
            'revision_requested' => 'Revision required',
            'resubmitted' => 'Revision awaiting review',
            'expert_review', 'for_final_decision' => 'Awaiting Research Head',
            default => 'Awaiting Research Head',
        };
        $backRoute = Auth::user()->isUsingWorkspace('research_head')
            ? route('research_head.dashboard')
            : route('faculty.dashboard');
        $canDecide = Auth::user()->isUsingWorkspace('research_head') && in_array($topic->status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision'], true);
        $isResearchHead = Auth::user()->isUsingWorkspace('research_head');
        $isFacultyWorkspace = Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']);
        $canAskAthenaAboutProposal = $topic->user_id === Auth::id() && $isFacultyWorkspace;
        $resubmissionErrors = $errors->getBag('resubmission');
        $reviewTabHash = 'proposal-review';
        $initialTopicTab = $resubmissionErrors->any() || $errors->has('evaluation_document') || $errors->has('status')
            ? 'review'
            : (in_array(session('topic_tab'), ['details', 'review', 'history'], true) ? session('topic_tab') : null);
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <x-back-link href="{{ $backRoute }}">Back to dashboard</x-back-link>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $topic->title }}</h2>
                    <p class="mt-1 text-sm text-gray-600">Proposal #{{ $topic->id }} &middot; {{ $topic->user->name }} &middot; {{ $topic->researchCall?->title ?? 'Research proposal' }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($draftHistoryCount > 0)
                        <a href="{{ route('topics.draft-history.index', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Draft history ({{ $draftHistoryCount }})</a>
                    @endif
                    @if ($canAskAthenaAboutProposal)
                        <button type="button" @click="$store.researchAssistant.openWithContext({{ $topic->id }})" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" />
                            </svg>
                            Ask Athena about this proposal
                        </button>
                    @endif
                    <span class="rounded-full px-3 py-1.5 text-sm font-black {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="{
            activeTopicTab: @js($initialTopicTab) || (
                ['#proposal-review', '#submit-revision'].includes(window.location.hash)
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
                this.activeTopicTab = ['#proposal-review', '#submit-revision'].includes(window.location.hash)
                    ? 'review'
                    : window.location.hash === '#version-history'
                        ? 'history'
                        : 'details';
            },
        }"
        @hashchange.window="syncTopicTab()"
    >
        @if (session('success'))
            <div class="rounded-2xl border border-gray-950 bg-gray-950 px-4 py-3 text-sm font-semibold text-white dark:border-gray-700 dark:bg-white dark:text-gray-950">{{ session('success') }}</div>
        @endif
        @if ($errors->any() || $resubmissionErrors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-bold">The action could not be completed.</p>
                <p class="mt-1 text-sm">{{ $resubmissionErrors->first() ?: $errors->first() }}</p>
            </div>
        @endif

        <div class="overflow-x-auto border-b border-gray-200" role="tablist" aria-label="Proposal workspace sections">
            <nav class="flex min-w-max gap-6">
                <button id="proposal-details-tab-button" type="button" role="tab" aria-controls="proposal-details-tab" :aria-selected="activeTopicTab === 'details'" @click="setTopicTab('details', 'proposal-details')" :class="activeTopicTab === 'details' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h11.5A2.25 2.25 0 0 1 20 5.25v13.5A2.25 2.25 0 0 1 17.75 21H6.25A2.25 2.25 0 0 1 4 18.75V5.25Z" /><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5" /></svg>
                    Proposal
                </button>
                <button id="proposal-review-tab-button" type="button" role="tab" aria-controls="proposal-review-tab" :aria-selected="activeTopicTab === 'review'" @click="setTopicTab('review', '{{ $reviewTabHash }}')" :class="activeTopicTab === 'review' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v14.25H4.5V6a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" d="M8.25 9.5h7.5M8.25 13h5.25" /></svg>
                    {{ $isResearchHead ? 'Review & decision' : 'Review status' }}
                </button>
                <button id="version-history-tab-button" type="button" role="tab" aria-controls="version-history-tab" :aria-selected="activeTopicTab === 'history'" @click="setTopicTab('history', 'version-history')" :class="activeTopicTab === 'history' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Versions
                </button>
            </nav>
        </div>

        <section id="proposal-details-tab" x-show="activeTopicTab === 'details'" x-cloak role="tabpanel" aria-labelledby="proposal-details-tab-button">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section id="submitted-files" aria-labelledby="submitted-files-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-red-600">Received package</p>
                            <h3 id="submitted-files-heading" class="mt-1 text-lg font-black text-gray-900">Submitted proposal files</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                @if ($latestVersion)
                                    Version {{ $latestVersion->version_number }} submitted by {{ $latestVersion->submitter?->name ?? $topic->user->name }} on {{ $latestVersion->created_at->format('M j, Y g:i A') }}.
                                @else
                                    No submitted version is available.
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-black uppercase tracking-wider {{ $availableSubmittedFileIds->count() === $submittedFiles->count() && $submittedFiles->isNotEmpty() ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200' }}">
                                {{ $availableSubmittedFileIds->count() }}/{{ $submittedFiles->count() }} files available
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
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $fileAvailable ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-400' }} text-xs font-black">FILE</span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-black text-gray-900">{{ $file->label() }}</h4>
                                            @if (! $fileAvailable)
                                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-black uppercase tracking-wider text-red-700">Unavailable</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 break-all text-sm font-semibold text-gray-600">{{ $file->original_filename }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $file->file_size ? \Illuminate\Support\Number::fileSize($file->file_size) : 'Size unavailable' }}@if ($file->is_carried_forward) &middot; Carried forward from an earlier version @endif</p>
                                    </div>
                                </div>

                                <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                    @if ($fileViewable)
                                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $file]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:flex-none">View</a>
                                    @endif
                                    @if ($fileAvailable)
                                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $file]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 sm:flex-none">Download</a>
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
                    <h3 id="research-details-heading" class="text-sm font-black text-gray-900">Research details</h3>
                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt class="text-xs font-bold uppercase text-gray-500">Total project cost</dt>
                            <dd class="mt-1 text-lg font-black text-gray-900">PHP {{ number_format($displayProjectCost, 2) }}</dd>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            <dt class="text-xs font-bold uppercase text-gray-500">Duration</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->estimated_duration_months }} months</dd>
                        </div>
                        @if ($topic->category)
                            <div class="border-t border-gray-100 pt-3">
                                <dt class="text-xs font-bold uppercase text-gray-500">Category</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->category->name }}</dd>
                            </div>
                        @endif
                    </dl>
                    <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-sm leading-6 text-gray-600">{{ $topic->description ?: 'No proposal summary provided.' }}</p>
                </section>
            </div>
        </section>

        <section id="proposal-review-tab" x-show="activeTopicTab === 'review'" x-cloak role="tabpanel" aria-labelledby="proposal-review-tab-button" class="space-y-5">
            <section class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
                <h3 class="text-lg font-black text-gray-900">{{ $isResearchHead ? 'One clear review process' : 'What happens next' }}</h3>
                <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-700">
                    @if ($isResearchHead)
                        Review the faculty package, receive the completed evaluation outside ATHENA, then upload that document here with your decision. ATHENA shares both the decision and its proof with the faculty member.
                    @elseif ($topic->status === 'revision_requested')
                        The Research Head requested changes. Read the decision, download the evaluation document, and replace only the files marked for revision.
                    @elseif ($topic->status === 'approved')
                        This proposal is approved. The Research Head’s evaluation document is available below.
                    @elseif ($topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE)
                        The review is complete. Only the papers with official signature blocks are waiting for their signed final PDFs.
                    @elseif ($topic->status === 'rejected')
                        This proposal received a final rejection. The decision and evaluation document are available below.
                    @else
                        The proposal is with the Research Head. You will be notified when a decision or revision request is shared.
                    @endif
                </p>
            </section>

            @if ($isResearchHead && $headUploadWorkspace)
                <x-research-head-file-workspace :topic="$topic" :workspace="$headUploadWorkspace" />
            @endif

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                    <h3 class="text-lg font-black text-gray-900">Evaluation and decision documents</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-600">Files uploaded by the Research Head are visible to the faculty member as proof of the review.</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($reviewDocuments as $reviewDocument)
                        @php
                            $reviewDocumentAvailable = $availableReviewDocumentIds->contains($reviewDocument->id);
                            $reviewDocumentViewable = $viewableReviewDocumentIds->contains($reviewDocument->id);
                            $documentDecision = $reviewDocument->source_data['decision'] ?? null;
                            $documentPurpose = $reviewDocument->source_data['purpose'] ?? null;
                        @endphp
                        <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-black text-gray-900">{{ $reviewDocument->label() }}</h4>
                                    @if ($documentDecision)
                                        <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-black text-red-700">{{ str($documentDecision)->replace('_', ' ')->title() }}</span>
                                    @endif
                                    @if ($documentPurpose)
                                        <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : ($documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION ? 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200') }}">{{ $reviewDocument->headUploadPurposeLabel() }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 break-all text-sm font-semibold text-gray-600">{{ $reviewDocument->original_filename }}</p>
                                <p class="mt-1 text-xs text-gray-500">Shared by {{ $reviewDocument->uploadedBy?->name ?? 'Research Head' }} on {{ $reviewDocument->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                            <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                @if ($reviewDocumentViewable)
                                    <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $reviewDocument]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:flex-none">View</a>
                                @endif
                                @if ($reviewDocumentAvailable)
                                    <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $reviewDocument]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-gray-800 sm:flex-none">Download</a>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-8 text-center">
                            <p class="text-base font-bold text-gray-700">No evaluation document has been shared yet.</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $isResearchHead ? 'Upload one when recording the decision below.' : 'It will appear here when the Research Head records a decision.' }}</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-black text-gray-900">Decision history</h3>
                    <p class="mt-1 text-sm text-gray-600">Research Head decisions and instructions in chronological order.</p>
                </div>
                <div class="space-y-5 p-6">
                    @forelse ($topic->reviews->where('decision', '!=', 'head_upload') as $review)
                        <div class="border-l-2 border-red-200 pl-4">
                            <div class="flex flex-wrap justify-between gap-2"><p class="text-sm font-black text-gray-800">{{ str($review->decision)->replace('_', ' ')->title() }}</p><time class="text-xs text-gray-500">{{ $review->created_at->format('M d, Y h:i A') }}</time></div>
                            <p class="mt-1 text-xs font-semibold text-gray-500">{{ $review->reviewer?->name ?? 'Former Research Head' }}</p>
                            @if ($review->comment)<p class="mt-3 whitespace-pre-line rounded-xl bg-gray-50 p-4 text-sm leading-6 text-gray-700">{{ $review->comment }}</p>@endif
                            @if ($review->fileRevisions->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach ($review->fileRevisions as $fileRevision)
                                        @php
                                            $annotationVersion = $topic->versions->firstWhere('id', $fileRevision->file?->proposal_version_id);
                                        @endphp
                                        <div class="rounded-xl border px-4 py-3 text-sm {{ $fileRevision->resolved_at ? 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200' : 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200' }}">
                                            <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-black">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}</span><span class="text-xs font-black">{{ $fileRevision->resolved_at ? 'Resolved' : 'Revision required' }}</span></div>
                                            <p class="mt-1 text-xs opacity-75">{{ $fileRevision->original_filename }}</p>
                                            @if ($fileRevision->revision_note)<p class="mt-2 leading-6">{{ $fileRevision->revision_note }}</p>@endif
                                            @if ($fileRevision->annotations->isNotEmpty() && $annotationVersion && $fileRevision->file)
                                                <a href="{{ route('topics.versions.files.annotations.index', [$topic, $annotationVersion, $fileRevision->file]) }}" class="mt-3 inline-flex rounded-lg bg-red-700 px-3 py-2 text-xs font-black text-white hover:bg-red-800">View {{ $fileRevision->annotations->count() }} highlighted comment(s)</a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-center text-sm text-gray-500">No Research Head decision has been recorded.</p>
                    @endforelse
                </div>
            </section>

            @if ($canDecide)
                <form
                    id="research-head-decision-form"
                    action="{{ route('research_head.topics.updateStatus', $topic) }}"
                    method="POST"
                    enctype="multipart/form-data"
                    x-data="{ decision: @js(old('status', '')) }"
                    class="space-y-5 rounded-2xl border border-red-200 bg-white p-5 shadow-sm dark:border-red-950 dark:bg-gray-950 sm:p-6"
                >
                    @csrf @method('PATCH')
                    <input type="hidden" name="redirect_to" value="topic">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-xl font-black text-gray-900">Record the Research Head decision</h3>
                            <p class="mt-1 text-sm leading-6 text-gray-600">The uploaded evaluation and your decision will immediately be shared with the faculty member.</p>
                        </div>
                        @if ($screeningTemplates->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($screeningTemplates as $template)
                                    <a href="{{ route('proposal-templates.download', $template) }}" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-100">Download blank form</a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="block text-sm font-bold text-gray-700">
                            Completed evaluation document <span class="text-red-600">Required</span>
                            <input name="evaluation_document" type="file" accept=".pdf,.doc,.docx" required class="mt-2 block w-full rounded-xl border border-gray-300 bg-white p-3 text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-bold file:text-gray-700 hover:file:bg-gray-200">
                            <span class="mt-2 block text-xs font-normal leading-5 text-gray-500">Upload the completed Initial Screening or external evaluation received by the Research Head.</span>
                        </label>
                        <label class="block text-sm font-bold text-gray-700">
                            Document title
                            <input name="evaluation_title" type="text" maxlength="255" value="{{ old('evaluation_title', 'External evaluation document') }}" class="mt-2 block w-full rounded-xl border-gray-300 text-sm" placeholder="External evaluation document">
                            <span class="mt-2 block text-xs font-normal leading-5 text-gray-500">This title is what the faculty member will see.</span>
                        </label>
                    </div>

                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-200">
                        Decision <span class="text-red-600">Required</span>
                        <select name="status" x-model="decision" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm font-bold dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Choose a decision</option>
                            <option value="approved" @selected(old('status') === 'approved')>Approve — no signed copies needed</option>
                            <option value="{{ \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE }}" @selected(old('status') === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE)>Continue to final signing</option>
                            <option value="revision_requested" @selected(old('status') === 'revision_requested')>Request revision</option>
                            <option value="rejected" @selected(old('status') === 'rejected')>Reject proposal</option>
                        </select>
                    </label>

                    @php
                        $oldSignatureFileIds = collect(old('signature_file_ids', []))->map(fn ($fileId) => (int) $fileId);
                    @endphp
                    <section
                        x-show="decision === @js(\App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE)"
                        x-cloak
                        class="rounded-2xl border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30 sm:p-5"
                        aria-labelledby="signature-file-selection-heading"
                    >
                        <h4 id="signature-file-selection-heading" class="text-lg font-black text-gray-950 dark:text-white">Which papers need a signed final PDF?</h4>
                        <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300">Nothing is selected automatically. Choose only the papers that actually require a signature.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($submittedFiles as $signatureCandidate)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-300 bg-white p-3 text-sm font-bold text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200">
                                    <input
                                        type="checkbox"
                                        name="signature_file_ids[]"
                                        value="{{ $signatureCandidate->id }}"
                                        @checked($oldSignatureFileIds->contains($signatureCandidate->id))
                                        class="mt-0.5 rounded border-gray-400 text-red-700 focus:ring-red-700"
                                    >
                                    <span>
                                        <span class="block">{{ $signatureCandidate->label() }}</span>
                                        <span class="mt-1 block break-all text-xs font-normal text-gray-500 dark:text-gray-400">{{ $signatureCandidate->original_filename }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-300 bg-white p-4 dark:border-gray-700 dark:bg-gray-950 sm:p-5" aria-labelledby="file-review-checklist-heading">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h4 id="file-review-checklist-heading" class="text-lg font-black text-gray-900">File review checklist</h4>
                                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Mark only the files that need revision. For PDFs, use <span class="font-black text-red-700 dark:text-red-400">Highlight PDF</span> to attach comments to the exact passage.</p>
                            </div>
                        </div>
                        @include('topics.partials.revision-file-selector', ['files' => $latestVersion?->files ?? collect()])
                        <p class="mt-4 rounded-xl bg-gray-950 px-4 py-3 text-sm font-semibold text-white dark:border dark:border-gray-800">If any file is marked for revision, choose <span class="font-black">Request revision</span> as the decision.</p>
                    </section>

                    <label class="block text-sm font-bold text-gray-700">
                        Decision notes
                        <textarea name="comment" rows="5" maxlength="5000" placeholder="Required when requesting a revision or rejecting the proposal. Give the faculty member clear next steps." class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6">{{ old('comment') }}</textarea>
                    </label>

                    <button class="w-full rounded-xl bg-red-600 px-5 py-3.5 text-base font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Save decision and share with faculty</button>
                </form>
            @elseif (Auth::user()->isUsingWorkspace('research_head'))
                <div class="rounded-2xl bg-gray-100 p-5 text-center text-sm font-bold text-gray-600">This proposal is already {{ $statusLabel }}. No further decision is available.</div>
            @endif

            @if ($isFacultyWorkspace && $topic->status === 'revision_requested' && $topic->user_id === Auth::id())
                <form id="submit-revision" action="{{ route('faculty.topics.resubmit', $topic) }}" method="POST" enctype="multipart/form-data" data-proposal-confirm data-confirm-title="Upload this revision to the Research Head?" data-confirm-text="Your updated metadata and selected files will be saved as a new version. Unchanged files will carry forward automatically. Continue?" data-confirm-button="Save and submit revision" data-confirm-icon="question" class="space-y-4 rounded-2xl border border-red-200 bg-white p-5 shadow-sm dark:border-red-950 dark:bg-gray-950">
                    @csrf @method('PATCH')
                    <input type="hidden" name="redirect_to" value="topic">
                    <input type="hidden" name="topic_tab" value="review">
                    @if ($topic->revisionDraft)
                        <input type="hidden" name="revision_draft_id" value="{{ $topic->revisionDraft->id }}">
                    @endif
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-400">Action required</p>
                        <h3 class="mt-1 text-xl font-black text-gray-900">Submit your revision</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-600">Update the details and upload only the files marked below. Unchanged files carry forward automatically.</p>
                    </div>

                    @if ($pendingFileRevisions->isNotEmpty())
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-900 dark:bg-red-950/30">
                            <p class="text-sm font-black text-red-900 dark:text-red-200">Files you must replace</p>
                            <div class="mt-2 space-y-2">
                                @foreach ($pendingFileRevisions as $fileRevision)
                                    <div class="text-sm leading-6 text-red-900 dark:text-red-200"><span class="font-black">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}:</span> {{ $fileRevision->original_filename }}@if ($fileRevision->revision_note)<p class="pl-2 text-sm text-red-800 dark:text-red-300">{{ $fileRevision->revision_note }}</p>@endif @if ($fileRevision->annotations->isNotEmpty() && $latestVersion && $fileRevision->file)<a href="{{ route('topics.versions.files.annotations.index', [$topic, $latestVersion, $fileRevision->file]) }}" class="mt-2 inline-flex rounded-lg bg-red-700 px-3 py-2 text-xs font-black text-white hover:bg-red-800">View highlighted comments ({{ $fileRevision->annotations->count() }})</a>@endif</div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @php($requiredRevisionTypes = $pendingFileRevisions->pluck('document_type')->unique())
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-bold text-gray-700">Project title<input name="title" value="{{ old('title', $topic->title) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm" placeholder="Project title"></label>
                        <label class="block text-sm font-bold text-gray-700">Total project cost<input name="estimated_budget" type="number" min="0" max="{{ $topic->researchCall?->budgetCeiling() ?? 0 }}" step="0.01" value="{{ old('estimated_budget', $displayProjectCost) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm" placeholder="Total project cost"></label>
                        <label class="block text-sm font-bold text-gray-700 md:col-span-2">Description<textarea name="description" rows="3" class="mt-2 block w-full rounded-xl border-gray-300 text-sm" placeholder="Description">{{ old('description', $topic->description) }}</textarea></label>
                        <label class="block text-sm font-bold text-gray-700">Duration in months<input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm" placeholder="Duration in months"></label>
                        <label class="block text-sm font-bold text-gray-700">What changed?<textarea name="change_summary" rows="2" maxlength="2000" class="mt-2 block w-full rounded-xl border-gray-300 text-sm" placeholder="Briefly explain your changes">{{ old('change_summary') }}</textarea></label>
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
                                @if ($stagedFile)<span class="mt-1 block rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-xs font-black text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Automatically uploaded: {{ $stagedFile->original_filename }}</span>@endif
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
                            @if ($stagedCurriculumVitae)<span class="mt-1 block rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-xs font-black text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Automatically uploaded: {{ $stagedCurriculumVitae->original_filename }}</span>@endif
                        </x-file-dropzone>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 pt-4">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 sm:w-auto">Save and submit revision</button>
                    </div>
                </form>
            @endif
        </section>

        <section id="version-history-tab" x-show="activeTopicTab === 'history'" x-cloak role="tabpanel" aria-labelledby="version-history-tab-button" class="space-y-5">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4"><h3 class="text-lg font-black text-gray-900">Version comparison</h3><p class="mt-1 text-sm text-gray-600">What changed between the two latest submissions.</p></div>
                @if ($previousVersion && $latestVersion)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
                            <thead class="bg-gray-50 text-xs font-black uppercase tracking-wider text-gray-500"><tr><th class="px-5 py-3">Field</th><th class="px-5 py-3">Version {{ $previousVersion->version_number }}</th><th class="px-5 py-3">Version {{ $latestVersion->version_number }}</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($comparisonRows as $row)
                                    <tr class="{{ $row['changed'] ? 'bg-red-50/60 dark:bg-red-950/20' : '' }}"><th class="px-5 py-3 font-black text-gray-700">{{ $row['label'] }} @if ($row['changed'])<span class="ml-1 text-xs uppercase text-red-700 dark:text-red-400">Changed</span>@endif</th><td class="max-w-xs px-5 py-3 text-gray-500">{{ $row['previous'] }}</td><td class="max-w-xs px-5 py-3 font-semibold text-gray-700">{{ $row['latest'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-100 p-5">
                        <p class="text-sm font-black text-gray-700">Files changed in version {{ $latestVersion->version_number }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($latestVersion->files->where('is_carried_forward', false)->whereNotIn('document_type', [\App\Models\ProposalVersionFile::TYPE_COMMENT_RESPONSE, \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD]) as $file)
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $file->label() }}</span>
                            @empty
                                <span class="text-xs text-gray-400">No package files were replaced.</span>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="p-8 text-center"><p class="text-base font-bold text-gray-700">Initial version</p><p class="mt-1 text-sm text-gray-500">A comparison will appear after the first revision.</p></div>
                @endif
            </section>

            @include('topics.partials.version-history', ['topic' => $topic, 'expanded' => true])

            @if ($topic->status === 'approved' && (Auth::user()->isUsingWorkspace('research_head') || $topic->user_id === Auth::id()))
                @include('topics.partials.project-monitoring')
            @endif
        </section>

        @if ($topic->signed_approval_path)
            <a href="{{ route('topics.approval', $topic) }}" class="flex justify-center rounded-xl bg-gray-950 px-4 py-3 text-sm font-bold text-white hover:bg-black dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Download previous signed approval</a>
        @endif
    </div>
</x-app-layout>
