<section id="notice-to-proceed" class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
    <div class="relative overflow-hidden bg-gray-950 px-5 py-6 text-white sm:px-7">
        <div class="absolute inset-y-0 right-0 w-48 bg-gradient-to-l from-red-700/40 to-transparent"></div>
        <div class="absolute -right-8 -top-14 h-40 w-40 rounded-full border-[24px] border-red-700/20"></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-red-700 text-lg font-black shadow-lg shadow-red-950/30">NTP</div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.16em] {{ $topic->hasIssuedNoticeToProceed() ? 'bg-white text-green-800' : 'bg-red-700 text-white' }}">
                            {{ $topic->hasIssuedNoticeToProceed() ? 'Issued' : 'Ready to generate' }}
                        </span>
                        <span class="text-xs font-bold uppercase tracking-widest text-gray-400">Research Office</span>
                    </div>
                    <h3 class="mt-3 text-2xl font-black tracking-tight">Notice to Proceed</h3>

                    @if ($topic->hasIssuedNoticeToProceed())
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-300">
                            Generated and issued {{ $topic->notice_to_proceed_issued_at->format('M j, Y g:i A') }}
                            @if ($topic->noticeIssuer)
                                by {{ $topic->noticeIssuer->name }}
                            @endif.
                            {{ $topic->isCompletedProject() ? 'This notice remains part of the completed project archive.' : 'Project monitoring is now open.' }}
                        </p>
                    @else
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-300">
                            ATHENA prepares the official PDF from the approved proposal. Review the prefilled researchers, schedule, budget, and resolution before issuing it.
                        </p>
                    @endif
                </div>
            </div>

            @if ($topic->hasIssuedNoticeToProceed())
                <div class="relative flex shrink-0 flex-col gap-2 sm:flex-row">
                    <a href="{{ route('topics.notice-to-proceed.download', $topic) }}" class="inline-flex items-center justify-center rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-white">
                        Download generated PDF
                    </a>
                    @if (! $isResearchHead && $topic->user_id === Auth::id())
                        <a href="{{ route('workspace.select') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-600 bg-white/10 px-4 py-3 text-sm font-black text-white transition hover:bg-white/20">Open researcher workspace</a>
                    @endif
                </div>
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

    @if ($isResearchHead && $noticeToProceedForm && ! $topic->isCompletedProject())
        <div class="p-5 sm:p-7">
            @if ($topic->hasIssuedNoticeToProceed())
                <details @if ($noticeToProceedErrors) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-black text-gray-900 transition hover:border-red-300 hover:bg-red-50">
                        <span>Review details and regenerate notice</span>
                        <span class="text-red-700">Edit</span>
                    </summary>
            @endif

            <form method="POST" action="{{ route('research_head.topics.notice-to-proceed.store', $topic) }}" class="{{ $topic->hasIssuedNoticeToProceed() ? 'mt-5' : '' }} space-y-6">
                @csrf

                @error('notice_to_proceed')
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800">{{ $message }}</div>
                @enderror

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(280px,0.75fr)]">
                    <div class="rounded-2xl border border-gray-200 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-red-700">01 · Project identity</p>
                                <h4 class="mt-1 text-lg font-black text-gray-950">Researchers and approved title</h4>
                            </div>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-bold text-gray-600">Prefilled from proposal</span>
                        </div>

                        <div class="mt-5 space-y-4" x-data="{ researchers: @js(old('researcher_names', $noticeToProceedForm['researcher_names'])) }">
                            <div>
                                <div class="flex items-center justify-between gap-3">
                                    <label class="text-sm font-bold text-gray-800">Researcher names</label>
                                    <button type="button" class="text-xs font-black text-red-700 hover:text-red-900" @click="researchers.push('')">+ Add researcher</button>
                                </div>
                                <div class="mt-2 space-y-2">
                                    <template x-for="(researcher, index) in researchers" :key="index">
                                        <div class="flex gap-2">
                                            <input type="text" :name="`researcher_names[${index}]`" x-model="researchers[index]" required maxlength="255" class="block w-full rounded-xl border-gray-300 text-sm font-semibold shadow-sm focus:border-red-600 focus:ring-red-600" :aria-label="`Researcher ${index + 1}`">
                                            <button type="button" class="rounded-xl border border-gray-200 px-3 text-sm font-black text-gray-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700" x-show="researchers.length > 1" @click="researchers.splice(index, 1)" aria-label="Remove researcher">×</button>
                                        </div>
                                    </template>
                                </div>
                                @error('researcher_names')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                                @error('researcher_names.*')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                                <p class="mt-2 text-xs leading-5 text-gray-500">Names come from the project leader and staff saved in the proposal papers. You can add or correct a researcher here.</p>
                            </div>

                            <label class="block text-sm font-bold text-gray-800">
                                Institution / campus line
                                <input name="campus_line" type="text" value="{{ old('campus_line', $noticeToProceedForm['campus_line']) }}" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                @error('campus_line')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                            </label>

                            <label class="block text-sm font-bold text-gray-800">
                                Approved project title
                                <textarea name="project_title" rows="3" required maxlength="500" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">{{ old('project_title', $noticeToProceedForm['project_title']) }}</textarea>
                                @error('project_title')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                            </label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 p-5">
                        <p class="text-xs font-black uppercase tracking-[0.14em] text-red-700">02 · Approval record</p>
                        <h4 class="mt-1 text-lg font-black text-gray-950">Notice and resolution</h4>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                            <label class="block text-sm font-bold text-gray-800">
                                Notice date
                                <input name="notice_date" type="date" value="{{ old('notice_date', $noticeToProceedForm['notice_date']) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                @error('notice_date')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                            </label>

                            <div class="grid grid-cols-[minmax(0,1fr)_110px] gap-3">
                                <label class="block text-sm font-bold text-gray-800">
                                    LREC Resolution No.
                                    <input name="resolution_number" type="text" value="{{ old('resolution_number', $noticeToProceedForm['resolution_number']) }}" required maxlength="50" placeholder="01" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                </label>
                                <label class="block text-sm font-bold text-gray-800">
                                    Series
                                    <input name="resolution_year" type="number" min="2000" max="2100" value="{{ old('resolution_year', $noticeToProceedForm['resolution_year']) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                </label>
                            </div>
                            @error('resolution_number')<p class="-mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                            @error('resolution_year')<p class="-mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.14em] text-red-700">03 · Approved implementation</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950">Schedule, duration, and Line-Item Budget</h4>
                        </div>
                        <p class="max-w-xl text-xs leading-5 text-gray-500">These begin with the proposed schedule and computed Line-Item Budget. Edit them if the approving body changed the final values.</p>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block text-sm font-bold text-gray-800">
                            Approved start date
                            <input name="approved_start_date" type="date" value="{{ old('approved_start_date', $noticeToProceedForm['approved_start_date']) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                            @error('approved_start_date')<span class="mt-2 block text-sm font-semibold text-red-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="block text-sm font-bold text-gray-800">
                            Approved end date
                            <input name="approved_end_date" type="date" value="{{ old('approved_end_date', $noticeToProceedForm['approved_end_date']) }}" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
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

                <details class="rounded-2xl border border-gray-200 bg-gray-50" @if ($errors->hasAny(['issuing_officer_name', 'issuing_officer_title', 'issuing_officer_committee_role', 'verifying_officer_name', 'verifying_officer_title', 'verifying_officer_committee_role'])) open @endif>
                    <summary class="cursor-pointer px-5 py-4 text-sm font-black text-gray-900">Authorized signatories <span class="ml-1 font-medium text-gray-500">— editable when appointments change</span></summary>
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

                <div class="flex flex-col gap-4 rounded-2xl border border-red-200 bg-red-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-black text-gray-950">The PDF will be generated automatically.</p>
                        <p class="mt-1 text-xs leading-5 text-gray-600">Issuing it grants Faculty Researcher access and opens project monitoring. No PDF upload is needed.</p>
                    </div>
                    <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">
                        {{ $topic->hasIssuedNoticeToProceed() ? 'Regenerate and reissue PDF' : 'Generate notice and open monitoring' }}
                    </button>
                </div>
            </form>

            @if ($topic->hasIssuedNoticeToProceed())
                </details>
            @endif
        </div>
    @elseif (! $topic->hasIssuedNoticeToProceed())
        <div class="px-5 py-5 text-sm text-gray-600 sm:px-7">
            The approved proposal is waiting for the Research Head to generate and issue its Notice to Proceed.
        </div>
    @endif
</section>
