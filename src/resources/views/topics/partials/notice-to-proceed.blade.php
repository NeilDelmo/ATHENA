@php($noticePreparedForSigning = $topic->hasPreparedNoticeToProceed())

<section id="notice-to-proceed" class="ntp-workspace scroll-mt-32 rounded-2xl border border-slate-200">
    <div class="rounded-t-2xl border-b border-t-4 border-slate-200 border-t-brand bg-slate-50 px-5 py-6 dark:border-b-slate-700 dark:bg-slate-900 sm:px-7">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 tabindex="-1" class="text-2xl font-semibold tracking-tight text-gray-950 focus:outline-none">Notice to Proceed</h3>

                @if ($topic->hasIssuedNoticeToProceed())
                    <p class="mt-2 max-w-3xl text-base leading-6 text-gray-600">
                        Signed copy issued {{ $topic->notice_to_proceed_issued_at->format('M j, Y g:i A') }}
                        @if ($topic->noticeIssuer)
                            by {{ $topic->noticeIssuer->name }}
                        @endif.
                        {{ $topic->isCompletedProject() ? 'This notice remains part of the completed project archive.' : 'Project monitoring is now open.' }}
                    </p>
                @elseif ($noticePreparedForSigning)
                    <p class="mt-2 max-w-3xl text-base leading-6 text-gray-600">Download the unsigned PDF, have it signed, then upload the signed copy to release it.</p>
                @else
                    <p class="mt-2 max-w-3xl text-base leading-6 text-gray-600">Review the details and prepare the unsigned PDF for signature.</p>
                @endif
            </div>

            @if ($topic->hasIssuedNoticeToProceed())
                <div class="relative flex shrink-0 flex-col gap-2 sm:flex-row">
                    <a href="{{ route('topics.notice-to-proceed.download', $topic) }}" class="rh-button">
                        Download signed PDF
                    </a>
                    @if (! $isResearchHead && $topic->user_id === Auth::id())
                        <a href="{{ route('workspace.select') }}" class="rh-button-secondary">Open researcher workspace</a>
                    @endif
                </div>
            @elseif ($noticePreparedForSigning && $isResearchHead)
                <a href="{{ route('research_head.topics.notice-to-proceed.download-unsigned', $topic) }}" class="rh-button-secondary shrink-0">
                    Download unsigned PDF
                </a>
            @endif
        </div>
    </div>

    @if ($topic->hasIssuedNoticeToProceed())
        <div class="grid gap-px border-b border-gray-200 bg-gray-200 sm:grid-cols-3">
            <div class="bg-white px-5 py-4">
                <p class="text-[11px] font-black uppercase tracking-wider text-gray-500">Document</p>
                <p class="mt-1 truncate text-sm font-bold text-gray-900">{{ $topic->notice_to_proceed_original_filename }}</p>
            </div>
            <div class="bg-white px-5 py-4">
                <p class="text-[11px] font-black uppercase tracking-wider text-gray-500">Approved period</p>
                <p class="mt-1 text-sm font-bold text-gray-900">
                    {{ data_get($topic->notice_to_proceed_data, 'approved_start_date', '—') }} to {{ data_get($topic->notice_to_proceed_data, 'approved_end_date', '—') }}
                </p>
            </div>
            <div class="bg-white px-5 py-4">
                <p class="text-[11px] font-black uppercase tracking-wider text-gray-500">Approved budget</p>
                <p class="mt-1 text-sm font-bold text-gray-900">Php {{ number_format((float) data_get($topic->notice_to_proceed_data, 'approved_budget', 0), 2) }}</p>
            </div>
        </div>
    @endif

    @if ($isResearchHead && $noticeToProceedForm && ! $topic->isCompletedProject() && ! $topic->hasIssuedNoticeToProceed())
        <div
            class="p-5 sm:p-7"
            x-data="noticeToProceedForm({
                previewUrl: @js(route('research_head.topics.notice-to-proceed.preview', $topic)),
                csrfToken: @js(csrf_token()),
            })"
            data-notice-to-proceed-autosave="true"
        >
            @if ($noticePreparedForSigning)
                <section class="rounded-2xl border border-gray-200 bg-white">
                    <form method="POST" action="{{ route('research_head.topics.notice-to-proceed.upload-signed', $topic) }}" enctype="multipart/form-data" class="p-5">
                        @csrf
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div class="min-w-0 flex-1">
                                <x-file-dropzone
                                    id="signed-notice-to-proceed"
                                    name="signed_notice_to_proceed"
                                    label="Signed Notice to Proceed PDF"
                                    accept=".pdf"
                                    :required="true"
                                    :max-bytes="25 * 1024 * 1024"
                                />
                                @error('signed_notice_to_proceed')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Release papers and Notice to Proceed</button>
                        </div>
                    </form>
                </section>

                <details class="mt-6 rounded-2xl border border-gray-200 bg-gray-50" @if ($noticeToProceedErrors) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-sm font-black text-gray-900 transition hover:bg-white">
                        <span>Need to correct the unsigned notice before signing?</span>
                        <span class="text-red-700">Edit details</span>
                    </summary>
                    <div class="border-t border-gray-200 bg-white p-5">
            @endif

            <form x-ref="form" data-notice-to-proceed-autosave-form method="POST" action="{{ route('research_head.topics.notice-to-proceed.store', $topic) }}" class="space-y-6" @submit="submitNoticeDetails">
                @csrf

                @error('notice_to_proceed')
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $message }}</div>
                @enderror

                <div class="space-y-6">
                    <div class="ntp-form-section">
                        <h4 class="text-lg font-semibold text-gray-950">Project details</h4>

                        <div class="mt-5 grid gap-6 md:grid-cols-2" x-data="{ projectStaff: @js(old('researcher_names', $noticeToProceedForm['researcher_names'])) }">
                            <div>
                                <span class="block text-sm font-semibold leading-6 text-slate-800 dark:text-slate-200">Project staff</span>
                                <div class="mt-2 space-y-2">
                                    <template x-for="(staffMember, index) in projectStaff" :key="index">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <input data-project-staff-input type="text" :name="`researcher_names[${index}]`" x-model="projectStaff[index]" required maxlength="255" placeholder="Full name" class="block min-w-0 w-full" :aria-label="`Project staff member ${index + 1}`">
                                            <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-red-950/40 dark:hover:text-red-300" x-show="projectStaff.length > 1" @click="projectStaff.splice(index, 1)" :aria-label="`Remove project staff member ${index + 1}`"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" stroke-linecap="round" /></svg></button>
                                        </div>
                                    </template>
                                </div>
                                @error('researcher_names')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                                @error('researcher_names.*')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                                <div class="mt-3 space-y-3">
                                    <button data-add-project-staff type="button" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-brand/30 bg-brand-wash/60 px-4 py-2.5 text-sm font-semibold text-brand hover:border-brand/60 hover:bg-brand-wash focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-200 dark:hover:bg-rose-950/60" @click="projectStaff.push(''); $nextTick(() => $el.closest('[x-data]').querySelectorAll('[data-project-staff-input]').item(projectStaff.length - 1).focus())">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                                        <span>Add project staff</span>
                                    </button>
                                    <p class="text-sm leading-5 text-slate-500 dark:text-slate-400">Names from the proposal. Add or update project staff as needed.</p>
                                </div>
                            </div>

                            <label class="block text-sm font-bold text-gray-800">
                                <span class="block">Institution / campus</span>
                                <input name="campus_line" type="text" value="{{ old('campus_line', $noticeToProceedForm['campus_line']) }}" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                @error('campus_line')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                            </label>

                            <label class="block text-sm font-bold text-gray-800 md:col-span-2">
                                Approved project title
                                <textarea name="project_title" rows="2" required maxlength="500" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">{{ old('project_title', $noticeToProceedForm['project_title']) }}</textarea>
                                @error('project_title')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                            </label>
                        </div>
                    </div>

                    <div class="ntp-form-section">
                        <h4 class="text-lg font-semibold text-gray-950">Approval record</h4>

                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            <label class="block text-sm font-bold text-gray-800">
                                Notice date
                                <x-date-picker id="notice-date" name="notice_date" :value="old('notice_date', $noticeToProceedForm['notice_date'])" required class="mt-2" />
                                @error('notice_date')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                            </label>

                            <div class="grid grid-cols-[minmax(0,1fr)_110px] gap-4">
                                <label class="block text-sm font-bold text-gray-800">
                                    LREC Resolution No.
                                    <input name="resolution_number" type="text" value="{{ old('resolution_number', $noticeToProceedForm['resolution_number']) }}" required maxlength="50" placeholder="01" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    @error('resolution_number')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                                </label>
                                <label class="block text-sm font-bold text-gray-800">
                                    Series
                                    <input name="resolution_year" type="number" min="2000" max="2100" value="{{ old('resolution_year', $noticeToProceedForm['resolution_year']) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    @error('resolution_year')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ntp-form-section">
                    <h4 class="text-lg font-semibold text-gray-950">Schedule and budget</h4>
                    <p class="mt-1 text-sm leading-5 text-gray-500">Update the proposed values if the final approval changed them.</p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block text-sm font-bold text-gray-800">
                            Approved start date
                                <x-date-picker id="approved-start-date" name="approved_start_date" :value="old('approved_start_date', $noticeToProceedForm['approved_start_date'])" required class="mt-2" />
                            @error('approved_start_date')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold text-gray-800">
                            Approved end date
                                <x-date-picker id="approved-end-date" name="approved_end_date" :value="old('approved_end_date', $noticeToProceedForm['approved_end_date'])" required class="mt-2" />
                            @error('approved_end_date')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold text-gray-800">
                            Duration (months)
                            <input name="approved_duration_months" type="number" min="1" max="120" value="{{ old('approved_duration_months', $noticeToProceedForm['approved_duration_months']) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                            @error('approved_duration_months')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold text-gray-800">
                            Approved budget (Php)
                            <input name="approved_budget" type="number" min="0" max="999999999.99" step="0.01" value="{{ old('approved_budget', $noticeToProceedForm['approved_budget']) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                            @error('approved_budget')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </div>

                <details data-signatories-disclosure class="group/signatories rounded-2xl border border-gray-200 bg-gray-50" @if ($errors->hasAny(['issuing_officer_name', 'issuing_officer_title', 'issuing_officer_committee_role', 'verifying_officer_name', 'verifying_officer_title', 'verifying_officer_committee_role'])) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-2xl px-5 py-4 text-sm text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] [&::-webkit-details-marker]:hidden">
                        <span><span class="block font-semibold">Authorized signatories</span><span class="mt-1 block text-xs text-gray-500">Update names and positions when appointments change.</span></span>
                        <span class="inline-flex min-h-10 shrink-0 items-center rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-[#7A0019]">
                            <span class="group-open/signatories:hidden">Show fields</span>
                            <span class="hidden group-open/signatories:inline">Hide fields</span>
                        </span>
                    </summary>
                    <div class="grid gap-5 border-t border-gray-200 bg-white p-5 lg:grid-cols-2">
                        <div class="space-y-4">
                            <p class="text-xs font-black uppercase tracking-wider text-red-700">Issuing officer</p>
                            @foreach ([
                                'issuing_officer_name' => 'Name',
                                'issuing_officer_title' => 'Position',
                                'issuing_officer_committee_role' => 'Committee role',
                            ] as $field => $label)
                                <label class="block text-sm font-bold text-gray-800">
                                    {{ $label }}
                                    <input name="{{ $field }}" type="text" value="{{ old($field, $noticeToProceedForm[$field]) }}" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    @error($field)<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                                </label>
                            @endforeach
                        </div>
                        <div class="space-y-4">
                            <p class="text-xs font-black uppercase tracking-wider text-red-700">Checking and verifying officer</p>
                            @foreach ([
                                'verifying_officer_name' => 'Name',
                                'verifying_officer_title' => 'Position',
                                'verifying_officer_committee_role' => 'Committee role',
                            ] as $field => $label)
                                <label class="block text-sm font-bold text-gray-800">
                                    {{ $label }}
                                    <input name="{{ $field }}" type="text" value="{{ old($field, $noticeToProceedForm[$field]) }}" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    @error($field)<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>

                <div class="flex flex-col gap-4 border-t border-slate-300 pt-6 dark:border-slate-700 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <p class="text-sm font-black text-gray-950">Preview the unsigned PDF before preparing it for signatures.</p>
                        <p class="mt-1 text-xs leading-5 text-gray-600">Previewing does not release anything. Faculty access and project monitoring remain locked until the signed PDF is uploaded.</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button type="button" data-notice-to-proceed-preview-button @click="showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="notice-to-proceed-preview-panel-{{ $topic->id }}" aria-haspopup="dialog" :disabled="previewLoading || submitting" class="rh-button-secondary disabled:cursor-wait disabled:opacity-60">
                            <span x-show="!previewLoading">Preview notice</span>
                            <span x-show="previewLoading" x-cloak>Generating preview...</span>
                        </button>
                        <button type="submit" :disabled="submitting || previewLoading" class="rh-button disabled:cursor-wait disabled:opacity-60">
                            <span x-show="!submitting">{{ $noticePreparedForSigning ? 'Save corrected details' : 'Save notice details' }}</span>
                            <span x-show="submitting" x-cloak>Saving details...</span>
                        </button>
                    </div>
                </div>

                <p x-show="previewError" x-cloak x-text="previewError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"></p>

                <x-proposal-document-preview
                    panel-id="notice-to-proceed-preview-panel-{{ $topic->id }}"
                    title="Notice to Proceed preview"
                    description="Unsigned notice generated from the current form details."
                    frame-title="Notice to Proceed document preview"
                />
            </form>

            @if ($noticePreparedForSigning)
                    </div>
                </details>
            @endif
        </div>
    @elseif (! $topic->hasIssuedNoticeToProceed())
        <div class="px-5 py-5 text-sm text-gray-600 sm:px-7">
            The final signing package is waiting for the Research Head to prepare, sign, and release its official Notice to Proceed.
        </div>
    @endif
</section>
