<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <a href="{{ route('faculty.proposal-drafts.index') }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">← Saved drafts</a>
                <h2 class="mt-2 break-words text-2xl font-black tracking-tight text-gray-900">{{ $proposalDraft->project_title }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ $proposalDraft->researchCall->title }} · Last saved {{ $proposalDraft->updated_at->diffForHumans() }}</p>
                <p class="mt-1 text-xs font-bold text-blue-700">{{ $proposalDraft->user_id === auth()->id() ? 'You own this workspace' : 'Shared with you by '.$proposalDraft->owner->name }}</p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <a href="{{ route('faculty.proposal-drafts.history.index', $proposalDraft) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:w-auto">History{{ $historyCount > 0 ? ' ('.$historyCount.')' : '' }}</a>
                <a href="{{ route('faculty.proposal-drafts.review', $proposalDraft) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 sm:w-auto">Review &amp; turn in</a>
            </div>
        </div>
    </x-slot>

    @php
        $completedPaperCount = $checklist->where('complete', true)->count();
        $paperCount = $checklist->count();
    @endphp

    <div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif

        @if (session('warning'))
            <x-proposal-alert type="warning">{{ session('warning') }}</x-proposal-alert>
        @endif

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">Some information still needs attention.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif

        @if (isset($readinessErrors['research_call']))
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                <p class="font-black">Submission is currently unavailable</p>
                <p class="mt-1">{{ $readinessErrors['research_call'] }}</p>
            </div>
        @endif

        <section aria-labelledby="package-progress-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 id="package-progress-heading" class="text-base font-black text-gray-900">Proposal package progress</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ $completedPaperCount }} of {{ $paperCount }} required PDF attachments ready</p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-black {{ $completedPaperCount === $paperCount && $projectDetailsComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $completedPaperCount === $paperCount && $projectDetailsComplete ? 'Ready to turn in' : 'Draft in progress' }}
                </span>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                <div class="h-full rounded-full bg-red-600" style="width: {{ $paperCount === 0 ? 0 : ($completedPaperCount / $paperCount) * 100 }}%"></div>
            </div>
        </section>

        <section aria-labelledby="recent-activity-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 id="recent-activity-heading" class="text-base font-black text-gray-900">Recent activity</h3>
                    <p class="mt-1 text-sm text-gray-500">The latest meaningful paper saves from this workspace.</p>
                </div>
                <a href="{{ route('faculty.proposal-drafts.history.index', $proposalDraft) }}" class="text-xs font-black text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">View full history</a>
            </div>

            <div class="mt-5 divide-y divide-gray-100 border-y border-gray-100">
                @forelse ($recentActivity as $activity)
                    <article class="flex gap-3 py-4">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $activity->action === 'restored' ? 'bg-blue-700' : 'bg-gray-900' }} text-[10px] font-black text-white">v{{ $activity->version_number }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-gray-900">{{ $activity->change_summary ?: 'Saved '.$activity->label() }}</p>
                            @if (filled($activity->change_note))
                                <p class="mt-1 break-words text-xs text-blue-800">“{{ $activity->change_note }}”</p>
                            @endif
                            <p class="mt-1 text-xs text-gray-500">{{ $activity->creator?->name ?? 'ATHENA' }} &middot; {{ $activity->created_at->diffForHumans() }}</p>
                        </div>
                    </article>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500">Paper activity will appear after the first save or upload.</p>
                @endforelse
            </div>
        </section>

        <section id="workspace-members" aria-labelledby="workspace-members-heading" class="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 id="workspace-members-heading" class="text-lg font-black text-gray-900">Proposal collaborators</h3>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500">Invite a teammate using their BatStateU Google email. Existing ATHENA accounts join immediately; new accounts are connected automatically on their first verified sign-in.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-blue-100 px-3 py-1 text-xs font-black text-blue-800">{{ 1 + $proposalDraft->members->count() }} {{ Str::plural('member', 1 + $proposalDraft->members->count()) }}</span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <article class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/40">
                    <div class="flex items-start justify-between gap-3"><p class="font-black text-gray-900">{{ $proposalDraft->owner->name }}</p><span class="rounded-full bg-blue-700 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white">Owner</span></div>
                    <p class="mt-1 break-all text-xs text-gray-600">{{ $proposalDraft->owner->email }}</p>
                    <p class="mt-3 text-[11px] font-semibold text-blue-800">Full workspace, invitation, submission, and deletion control</p>
                </article>
                @foreach ($proposalDraft->members as $member)
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><p class="truncate font-black text-gray-900">{{ $member->user?->name ?? $member->name }}</p><p class="mt-1 break-all text-xs text-gray-600">{{ $member->user?->email ?? $member->email }}</p></div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $member->isLinked() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $member->isLinked() ? 'Joined' : 'Pending sign-in' }}</span>
                        </div>
                        <p class="mt-3 text-[11px] font-semibold {{ $member->isLinked() ? 'text-green-700' : 'text-amber-800' }}">{{ $member->isLinked() ? 'Can open and edit every draft paper.' : 'Waiting for this exact email to sign in to ATHENA.' }}</p>
                        @can('manageMembers', $proposalDraft)
                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-3">
                                <form action="{{ route('faculty.proposal-drafts.members.invitation', [$proposalDraft, $member]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-xs font-bold text-blue-700 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600">Resend invitation</button>
                                </form>
                                <form
                                    action="{{ route('faculty.proposal-drafts.members.destroy', [$proposalDraft, $member]) }}"
                                    method="POST"
                                    data-proposal-confirm
                                    data-confirm-title="Remove collaborator?"
                                    data-confirm-text="This collaborator will immediately lose access to the proposal workspace."
                                    data-confirm-button="Remove collaborator"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-red-700 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600">Remove</button>
                                </form>
                            </div>
                        @endcan
                    </article>
                @endforeach
            </div>

            @can('manageMembers', $proposalDraft)
                <div class="mt-5 rounded-2xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-900 dark:bg-blue-950/30 sm:p-5">
                    <h4 class="text-sm font-black text-blue-950 dark:text-blue-100">Send a workspace invitation</h4>
                    <p class="mt-1 text-xs leading-5 text-blue-800 dark:text-blue-200">ATHENA emails the teammate and links access only when their verified Google sign-in matches the invited address.</p>
                    <form
                        action="{{ route('faculty.proposal-drafts.members.store', $proposalDraft) }}"
                        method="POST"
                        class="mt-4 grid gap-4 lg:grid-cols-[1fr_1fr_auto] lg:items-start"
                        x-data="proposalDraftMembers({ candidates: @js($memberCandidates) })"
                    >
                        @csrf
                        <div>
                            <label for="workspace-member-email" class="block text-[10px] font-black uppercase leading-4 tracking-wider text-gray-700 dark:text-gray-300">BatStateU Google email</label>
                            <input id="workspace-member-email" name="email" type="email" list="workspace-member-accounts" x-model="email" x-on:input="syncAccount" value="{{ old('email') }}" maxlength="255" required placeholder="name@g.batstate-u.edu.ph" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <datalist id="workspace-member-accounts">@foreach ($memberCandidates as $candidate)<option value="{{ $candidate['email'] }}">{{ $candidate['name'] }}</option>@endforeach</datalist>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Choose an ATHENA account or enter the exact institutional email they will use to sign in.</p>
                        </div>
                        <div>
                            <label for="workspace-member-name" class="block text-[10px] font-black uppercase leading-4 tracking-wider text-gray-700 dark:text-gray-300">Teammate name</label>
                            <input id="workspace-member-name" name="name" type="text" x-model="name" value="{{ old('name') }}" maxlength="255" required placeholder="Full name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400" x-text="matchedAccount ? 'Pulled from the linked ATHENA account.' : 'Used until their ATHENA account is linked.'"></p>
                        </div>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2 lg:mt-[1.375rem] lg:w-auto">Send invitation</button>
                    </form>
                </div>
            @endcan
        </section>

        <section aria-labelledby="project-details-heading" class="rounded-2xl border {{ $projectDetailsComplete ? 'border-green-200' : 'border-amber-200' }} bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 id="project-details-heading" class="text-lg font-black text-gray-900">Project Details</h3>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $projectDetailsComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $projectDetailsComplete ? 'Complete' : 'Incomplete' }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-500">Shared information used automatically in Attachment A and the submitted proposal record.</p>
                </div>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">{{ $projectDetailsComplete ? 'Edit Project Details' : 'Complete Project Details' }}</a>
            </div>

            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->duration_months ? $proposalDraft->duration_months.' '.Str::plural('month', $proposalDraft->duration_months) : 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned Start</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_start?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned End</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_end?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 break-words text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="required-papers-heading">
            <div class="mb-4">
                <h3 id="required-papers-heading" class="text-lg font-black text-gray-900">Required PDF attachments</h3>
                <p class="mt-1 text-sm text-gray-500">Complete each paper here. ATHENA prepares generated forms as PDFs when the owner turns in the package.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($checklist as $item)
                    @php
                        $paper = $item['paper'];
                        $template = filled($paper['template_slug']) ? $templates->get($paper['template_slug']) : null;
                        $sampleDefinition = filled($paper['sample_slug']) ? config('proposal_samples.'.$paper['sample_slug']) : null;
                        $sampleAvailable = is_array($sampleDefinition)
                            && isset($sampleDefinition['path'])
                            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
                        $paperRoute = match ($paper['slug']) {
                            'detailed-proposal' => route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft),
                            'work-plan' => route('faculty.proposal-drafts.work-plan.edit', $proposalDraft),
                            'line-item-budget' => route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft),
                            'expense-breakdown' => route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft),
                            'curriculum-vitae' => route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft),
                            'gad-checklist' => route('faculty.proposal-drafts.gad-checklist.edit', $proposalDraft),
                            'initial-screening-form' => route('faculty.proposal-drafts.initial-screening-form.show', $proposalDraft),
                            default => route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']]),
                        };
                        $paperAction = $paper['workspace_button_label'] ?? 'Open '.$paper['label'];
                    @endphp
                    <article class="flex min-h-80 flex-col rounded-2xl border {{ $item['complete'] ? 'border-green-200' : 'border-gray-200' }} bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex rounded-lg bg-gray-100 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600">Paper {{ $paper['order'] }}</span>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $item['complete'] ? 'bg-green-100 text-green-800' : ($item['status'] === 'In progress' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600') }}">{{ $item['status'] }}</span>
                        </div>
                        <h4 class="mt-4 text-base font-black leading-6 text-gray-900">{{ $paper['label'] }}</h4>
                        <p class="mt-2 text-xs leading-5 text-gray-500">{{ $paper['description'] }}</p>

                        <div class="mt-4 min-h-10 text-xs text-gray-600">
                            @if ($paper['mode'] === 'automatic')
                                <p class="font-semibold">PDF prepared automatically from Project Details when the package is turned in.</p>
                            @elseif ($paper['slug'] === 'gad-checklist')
                                <p class="font-semibold">Prepared automatically from Project Details. Review the generated copy and mark it ready.</p>
                            @elseif ($item['documents']->isNotEmpty())
                                @if ($paper['mode'] === 'generated')
                                    <p class="font-semibold">{{ $item['submission_filename'] }}</p>
                                    <p class="mt-1 text-[11px] text-gray-500">PDF ready to generate &middot; Saved {{ $item['documents']->first()->updated_at->diffForHumans() }}</p>
                                @elseif ($paper['multiple'])
                                    <p class="font-semibold">{{ $item['count'] }} {{ Str::plural('file', $item['count']) }} staged</p>
                                @else
                                    <p class="break-all font-semibold">{{ $item['documents']->first()->original_filename }}</p>
                                @endif
                            @else
                                <p>No file or form data saved yet.</p>
                            @endif
                        </div>

                        @if ($template || $sampleAvailable)
                            <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                                @if ($template)<a href="{{ route('proposal-templates.download', $template) }}" class="text-xs font-bold text-red-600 underline decoration-red-200 underline-offset-4 hover:text-red-700">Download template</a>@endif
                                @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="text-xs font-bold text-red-600 underline decoration-red-200 underline-offset-4 hover:text-red-700">View sample</a>@endif
                            </div>
                        @endif

                        <a href="{{ $paperRoute }}" class="mt-auto inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-3 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2" aria-label="{{ $paperAction }}">{{ $paperAction }}</a>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="flex flex-col gap-3 rounded-2xl bg-gray-900 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div><p class="font-black text-white">Ready to turn in the proposal?</p><p class="mt-1 text-xs text-gray-300">Review the seven PDF attachments before sending the immutable package.</p></div>
            <a href="{{ route('faculty.proposal-drafts.review', $proposalDraft) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 sm:w-auto">Review &amp; turn in</a>
        </div>
    </div>
</x-app-layout>
