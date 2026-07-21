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
        $completedDocuments = $packageChecklist->where('status', 'complete')->count();
        $packageComplete = $completedDocuments === $packageChecklist->count();
        $canDecide = Auth::user()->isUsingWorkspace('research_head') && in_array($topic->status, ['pending', 'resubmitted', 'for_final_decision'], true);
        $canAskAthenaAboutProposal = $topic->user_id === Auth::id() && Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']);
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <a href="{{ $backRoute }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 transition hover:text-red-600">&larr; Back to dashboard</a>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $topic->title }}</h2>
                    <p class="mt-1 text-xs text-gray-500">Proposal #{{ $topic->id }} · {{ $topic->user->name }} · {{ $topic->researchCall->title }}</p>
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

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><p class="font-bold">The action could not be completed.</p><p class="mt-1 text-xs">{{ $errors->first() }}</p></div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <section id="submitted-files" aria-labelledby="submitted-files-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm scroll-mt-6">
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
                        <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wider {{ $availableSubmittedFileIds->count() === $submittedFiles->count() && $submittedFiles->isNotEmpty() ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-800' }}">
                            {{ $availableSubmittedFileIds->count() }}/{{ $submittedFiles->count() }} PDFs available
                        </span>
                    </div>

                    <div class="divide-y divide-gray-100">
                        @forelse ($submittedFiles as $file)
                            @php
                                $fileAvailable = $availableSubmittedFileIds->contains($file->id);
                                $fileViewable = $viewableSubmittedFileIds->contains($file->id);
                                $isHeadUpload = $file->document_type === \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD;
                            @endphp
                            <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 {{ $isHeadUpload ? 'bg-cyan-50/40' : '' }}">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $isHeadUpload ? 'bg-cyan-100 text-cyan-800' : ($fileAvailable ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-400') }} text-[10px] font-black">{{ $isHeadUpload ? 'SIGN' : 'PDF' }}</span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-black text-gray-900">{{ $file->label() }}</h4>
                                            @if ($isHeadUpload)
                                                <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-cyan-800">Signed by {{ $file->uploadedBy?->name ?? 'Research Head' }}</span>
                                            @elseif (! $fileAvailable)
                                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-red-700">File unavailable</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 break-all text-xs font-semibold text-gray-600">{{ $file->original_filename }}</p>
                                        <p class="mt-1 text-[11px] text-gray-400">{{ $file->file_size ? \Illuminate\Support\Number::fileSize($file->file_size) : 'Size unavailable' }}@if ($file->is_carried_forward) &middot; Carried forward from an earlier version @endif @if ($isHeadUpload && ($file->source_data['note'] ?? null)) &middot; {{ $file->source_data['note'] }} @endif</p>
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

                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h3 class="text-sm font-black text-gray-900">Proposal package checklist</h3><p class="mt-1 text-xs text-gray-500">Latest version: {{ $latestVersion ? 'Version '.$latestVersion->version_number : 'No version available' }}</p></div>
                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase {{ $packageComplete ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $completedDocuments }}/{{ $packageChecklist->count() }} complete</span>
                    </div>

                    @unless ($packageComplete)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800">This package is incomplete or references a missing stored file. It should not receive final approval until every item is available.</div>
                    @endunless

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($packageChecklist as $item)
                            <div class="flex items-center gap-3 rounded-xl border border-gray-200 p-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $item['status'] === 'complete' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $item['status'] === 'complete' ? '✓' : '!' }}
                                </span>
                                <div class="min-w-0"><p class="text-xs font-black text-gray-800">{{ $item['label'] }}</p><p class="mt-0.5 text-[11px] text-gray-400">{{ $item['status'] === 'complete' ? $item['count'].' file(s) available' : ($item['status'] === 'missing' ? 'Not included' : 'Stored file is unavailable') }}</p></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4"><h3 class="text-sm font-black text-gray-900">Version comparison</h3><p class="mt-1 text-xs text-gray-500">Metadata and file changes in the latest revision.</p></div>
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
                                @forelse ($latestVersion->files->where('is_carried_forward', false) as $file)
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

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4"><h3 class="text-sm font-black text-gray-900">Review and decision timeline</h3><p class="mt-1 text-xs text-gray-500">Research Head decisions and assigned co-evaluator recommendations.</p></div>
                    <div class="space-y-5 p-6">
                        @forelse ($topic->reviews as $review)
                            <div class="border-l-2 border-red-200 pl-4">
                                <div class="flex flex-wrap justify-between gap-2"><p class="text-xs font-black uppercase text-gray-700">{{ str_replace('_', ' ', $review->decision) }}</p><time class="text-[11px] text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</time></div>
                                <p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $review->reviewer?->name ?? 'Former Research Head' }}</p>
                                @if ($review->comment)<p class="mt-2 whitespace-pre-line rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600">{{ $review->comment }}</p>@endif
                                @if ($review->fileRevisions->isNotEmpty())
                                    <div class="mt-2 space-y-2">
                                        @foreach ($review->fileRevisions as $fileRevision)
                                            <div class="rounded-xl border px-3 py-2 text-xs {{ $fileRevision->resolved_at ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
                                                <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-black">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}</span><span class="text-[9px] font-black uppercase">{{ $fileRevision->resolved_at ? 'Resolved' : 'Revision required' }}</span></div>
                                                <p class="mt-0.5 text-[10px] opacity-75">{{ $fileRevision->original_filename }}</p>
                                                @if ($fileRevision->revision_note)<p class="mt-1 leading-5">{{ $fileRevision->revision_note }}</p>@endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-center text-xs text-gray-400">No Research Head decision has been recorded.</p>
                        @endforelse

                        @foreach ($topic->expertAssignments as $assignment)
                            <div class="border-l-2 border-purple-200 pl-4"><div class="flex flex-wrap justify-between gap-2"><p class="text-xs font-black uppercase text-purple-700">Co-evaluation · {{ str_replace('_', ' ', $assignment->status) }}</p><time class="text-[11px] text-gray-400">{{ ($assignment->reviewed_at ?: $assignment->created_at)->format('M d, Y h:i A') }}</time></div><p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $assignment->expert->name }}</p>@if ($assignment->recommendation)<p class="mt-2 text-[11px] font-black uppercase text-purple-700">{{ str_replace('_', ' ', $assignment->recommendation) }}</p><p class="mt-1 whitespace-pre-line rounded-xl bg-purple-50/50 p-3 text-xs leading-5 text-gray-600">{{ $assignment->comment }}</p>@endif</div>
                        @endforeach
                    </div>
                </section>

                @if ($topic->status === 'approved' && (Auth::user()->isUsingWorkspace('research_head') || $topic->user_id === Auth::id()))
                    @include('topics.partials.project-monitoring')
                @endif
            </div>

            <aside class="space-y-5">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Research details</h3>
                    <dl class="mt-4 space-y-4"><div><dt class="text-[11px] font-bold uppercase text-gray-400">Total project cost</dt><dd class="mt-1 text-lg font-black text-gray-900">PHP {{ number_format((float) $topic->estimated_budget, 2) }}</dd></div><div class="border-t border-gray-100 pt-3"><dt class="text-[11px] font-bold uppercase text-gray-400">Duration</dt><dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->estimated_duration_months }} months</dd></div>@if ($topic->category)<div class="border-t border-gray-100 pt-3"><dt class="text-[11px] font-bold uppercase text-gray-400">Category</dt><dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->category->name }}</dd></div>@endif</dl>
                    <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-xs leading-5 text-gray-500">{{ $topic->description ?: 'No proposal summary provided.' }}</p>
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
                        <div><label class="text-[11px] font-bold text-gray-500">Signed approval PDF</label><input type="file" name="signed_approval" accept=".pdf" class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs"></div>
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

                @if ($topic->status === 'revision_requested' && $topic->user_id === Auth::id())
                    <form action="{{ route('faculty.topics.resubmit', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                        @csrf @method('PATCH')
                        <input type="hidden" name="redirect_to" value="topic">
                        <h3 class="text-sm font-black text-gray-900">Submit revision</h3>
                        <p class="text-xs leading-5 text-gray-500">Update the metadata and upload only changed files. Unchanged files carry forward.</p>
                        <div class="rounded-xl border border-purple-200 bg-purple-50 p-3 text-xs leading-5 text-purple-800">
                            <p class="font-black">Auto-filled Comment-Response Form</p>
                            <p class="mt-1">ATHENA fills the proposal title, Project Leader, known researcher details, and footer. Complete the evaluation type/date, comments, actions and responses, page-and-paragraph remarks, and signatures in Word.</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a href="{{ route('faculty.topics.comment-response-form.preview', $topic) }}" target="_blank" rel="noopener" class="rounded-lg border border-purple-300 bg-white px-3 py-2 text-[11px] font-bold text-purple-800">Preview auto-filled form</a>
                                <a href="{{ route('faculty.topics.comment-response-form.download', $topic) }}" class="rounded-lg bg-purple-700 px-3 py-2 text-[11px] font-bold text-white">Download auto-filled Word file</a>
                            </div>
                        </div>
                        <label class="block text-[11px] font-bold text-gray-500">Completed comment-response form <span class="text-red-600">Required</span><input name="comment_response" type="file" accept=".doc,.docx,.pdf" required class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs"></label>
                        @if ($pendingFileRevisions->isNotEmpty())
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                                <p class="text-[11px] font-black uppercase tracking-wider text-amber-800">Required replacements</p>
                                <div class="mt-2 space-y-2">
                                    @foreach ($pendingFileRevisions as $fileRevision)
                                        <div class="text-xs text-amber-900"><span class="font-black">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}:</span> {{ $fileRevision->original_filename }}@if ($fileRevision->revision_note)<p class="mt-0.5 pl-2 text-[11px] text-amber-700">{{ $fileRevision->revision_note }}</p>@endif</div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @php($requiredRevisionTypes = $pendingFileRevisions->pluck('document_type')->unique())
                        <input name="title" value="{{ old('title', $topic->title) }}" required class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Project title">
                        <textarea name="description" rows="3" class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Description">{{ old('description', $topic->description) }}</textarea>
                        <input name="estimated_budget" type="number" min="0" max="{{ $topic->researchCall->budgetCeiling() }}" step="0.01" value="{{ old('estimated_budget', $topic->estimated_budget) }}" required class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Total project cost (maximum PHP {{ number_format($topic->researchCall->budgetCeiling(), 2) }})">
                        <input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Duration in months">
                        <textarea name="change_summary" rows="2" maxlength="2000" class="block w-full rounded-xl border-gray-200 text-xs" placeholder="What changed in this version?"></textarea>
                        @foreach ([['detailed_proposal', 'Detailed proposal', '.doc,.docx,.pdf'], ['work_plan', 'Work plan', '.doc,.docx,.pdf'], ['line_item_budget', 'Line-item budget', '.doc,.docx,.pdf'], ['expense_breakdown', 'Expense breakdown', '.xls,.xlsx'], ['gad_checklist', 'GAD checklist', '.doc,.docx,.pdf']] as [$name, $label, $accept])
                            <label class="block text-[11px] font-bold text-gray-500">{{ $label }} @if ($requiredRevisionTypes->contains($name))<span class="text-red-600">Required</span>@endif<input name="{{ $name }}" type="file" accept="{{ $accept }}" @required($requiredRevisionTypes->contains($name)) class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs"></label>
                        @endforeach
                        <label class="block text-[11px] font-bold text-gray-500">Curriculum vitae files @if ($requiredRevisionTypes->contains('curriculum_vitae'))<span class="text-red-600">Required</span>@endif<input name="curricula_vitae[]" type="file" accept=".doc,.docx,.pdf" multiple @required($requiredRevisionTypes->contains('curriculum_vitae')) class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs"></label>
                        <button class="w-full rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white">Submit new version</button>
                    </form>
                @endif

                @if ($topic->signed_approval_path)
                    <a href="{{ route('topics.approval', $topic) }}" class="flex justify-center rounded-xl bg-green-700 px-4 py-3 text-xs font-bold text-white">Download signed approval</a>
                @endif

                @if (Auth::user()->isUsingWorkspace('research_head') && $latestVersion)
                    <form action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-cyan-200 bg-white p-5 shadow-sm">
                        @csrf
                        <h3 class="text-sm font-black text-gray-900">Attach signed copy</h3>
                        <p class="text-xs leading-5 text-gray-500">Upload a signed or countersigned copy of one of the seven required papers. It joins the existing faculty copy so the package keeps a full audit trail. Signed copies can be added at any time, including after approval.</p>
                        @if (session('headUpload_success'))
                            <div class="rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-700">{{ session('headUpload_success') }}</div>
                        @endif
                        @if ($errors->headUpload->any())
                            <div class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                                <p class="font-bold">The signed copy could not be attached.</p>
                                <ul class="mt-1 list-disc space-y-0.5 pl-4">@foreach ($errors->headUpload->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        @endif
                        <label class="block text-[11px] font-bold text-gray-500">Signed copy of <span class="text-red-600">Required</span>
                            <select name="target_document_type" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs font-semibold">
                                <option value="">Choose a paper…</option>
                                @foreach ($headUploadTypes as $type => $label)
                                    <option value="{{ $type }}" @selected(old('target_document_type') === $type)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-[11px] font-bold text-gray-500">Signed file (PDF or Word) <span class="text-red-600">Required</span>
                            <input name="signed_file" type="file" accept=".pdf,.doc,.docx" required class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs">
                        </label>
                        <label class="block text-[11px] font-bold text-gray-500">Note (optional)<textarea name="note" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="e.g. Signed by external co-evaluator on 2026-07-21">{{ old('note') }}</textarea></label>
                        <button class="w-full rounded-xl bg-cyan-700 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-700 focus:ring-offset-2">Attach signed copy</button>
                    </form>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
