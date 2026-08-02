<x-app-layout>
    <x-slot name="header">
        <div>
            <x-back-link href="{{ route('faculty.proposal-drafts.index') }}">Back to proposal workspace</x-back-link>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">New Proposal</h2>
            <p class="mt-1 text-xs text-gray-500">Choose the research call and give this project a working title.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            @if ($errors->any())
                <x-proposal-alert type="error" class="mb-6">
                    <p class="font-bold">Please correct the following:</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-proposal-alert>
            @endif

            @if ($researchCalls->isEmpty())
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-amber-900">
                    <h3 class="font-black">No research call is open</h3>
                    <p class="mt-2 text-sm leading-6">You can resume existing drafts, but a new draft cannot be created until a call accepts submissions.</p>
                    <a href="{{ route('faculty.proposal-drafts.index') }}" class="mt-5 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-sm font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Return to saved drafts</a>
                </div>
            @else
                @php($currentResearchCallId = (string) old('research_call_id', $selectedResearchCallId))
                <form
                    action="{{ route('faculty.proposal-drafts.store') }}"
                    method="POST"
                    x-data="{ submitting: false, researchCallPickerOpen: @js($currentResearchCallId === ''), selectedResearchCallId: @js($currentResearchCallId) }"
                    x-init="if (researchCallPickerOpen) { $nextTick(() => $refs.closeResearchCallPicker.focus()) }"
                    @submit="submitting = true"
                    :aria-busy="submitting"
                    class="space-y-6"
                >
                    @csrf
                    <fieldset>
                        <legend class="text-xs font-black uppercase tracking-wider text-gray-600">Research Call <span class="text-red-600">Required</span></legend>
                        <p id="research-call-help" class="mt-1 text-sm text-gray-500">Choose the call that this proposal will answer.</p>

                        <div class="mt-3">
                            @foreach ($researchCalls as $researchCall)
                                <article x-show="selectedResearchCallId === '{{ $researchCall->id }}'" x-cloak class="overflow-hidden rounded-2xl border border-red-200 bg-red-50/50">
                                    <div class="flex flex-col sm:flex-row sm:items-center">
                                        <div class="flex h-28 items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 sm:h-24 sm:w-28 sm:shrink-0">
                                            @if ($researchCall->reference_image_path)
                                                <img src="{{ route('research-calls.reference-image', $researchCall) }}" alt="Poster for {{ $researchCall->title }}" class="h-full w-full object-contain p-1.5">
                                            @else
                                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-label="No poster uploaded">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="flex min-w-0 flex-1 items-center justify-between gap-4 p-4">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-wider">
                                                    <span class="rounded-full bg-green-100 px-2 py-1 text-green-700">Open</span>
                                                    <span class="text-gray-400">Closes {{ $researchCall->closes_at->format('M j, Y') }}</span>
                                                </div>
                                                <h3 class="mt-2 truncate text-sm font-black text-gray-900">{{ $researchCall->title }}</h3>
                                                <p class="mt-1 text-xs font-semibold text-gray-500">{{ $researchCall->academic_year }}{{ $researchCall->term ? ' · '.$researchCall->term : '' }} · Up to ₱{{ number_format($researchCall->budgetCeiling()) }}</p>
                                            </div>
                                            <button type="button" @click="researchCallPickerOpen = true; $nextTick(() => $refs.closeResearchCallPicker.focus())" class="shrink-0 rounded-xl border border-red-200 bg-white px-3 py-2 text-xs font-black text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Change</button>
                                        </div>
                                    </div>
                                </article>
                            @endforeach

                            <button
                                type="button"
                                x-show="! selectedResearchCallId"
                                x-cloak
                                @click="researchCallPickerOpen = true; $nextTick(() => $refs.closeResearchCallPicker.focus())"
                                class="flex w-full items-center justify-between gap-4 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 px-4 py-5 text-left transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                            >
                                <span>
                                    <span class="block text-sm font-black text-gray-900">Choose a research call</span>
                                    <span class="mt-1 block text-xs text-gray-500">View posters, deadlines, and available budgets.</span>
                                </span>
                                <svg class="h-5 w-5 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </button>
                        </div>

                        <div
                            x-show="researchCallPickerOpen"
                            x-cloak
                            x-transition.opacity
                            @keydown.escape.window="researchCallPickerOpen = false"
                            class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm sm:p-6"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="research-call-picker-title"
                        >
                            <button type="button" @click="researchCallPickerOpen = false" class="absolute inset-0 cursor-default" aria-label="Close research call picker"></button>

                            <div class="relative z-10 flex max-h-[88vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl" data-research-call-picker>
                                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-6">
                                    <div>
                                        <h2 id="research-call-picker-title" class="text-lg font-black text-gray-900">Choose a research call</h2>
                                        <p class="mt-1 text-xs text-gray-500">Select one call for this proposal. The picker will close after your choice.</p>
                                    </div>
                                    <button x-ref="closeResearchCallPicker" type="button" @click="researchCallPickerOpen = false" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600" aria-label="Close research call picker">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <div class="overflow-y-auto p-4 sm:p-6">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        @foreach ($researchCalls as $researchCall)
                                            <label for="research_call_id_{{ $researchCall->id }}" class="group relative min-w-0 cursor-pointer">
                                                <input
                                                    id="research_call_id_{{ $researchCall->id }}"
                                                    name="research_call_id"
                                                    type="radio"
                                                    value="{{ $researchCall->id }}"
                                                    class="peer sr-only"
                                                    required
                                                    x-model="selectedResearchCallId"
                                                    @change="researchCallPickerOpen = false; $nextTick(() => $refs.projectTitle.focus())"
                                                    @invalid="researchCallPickerOpen = true"
                                                    aria-describedby="research-call-help"
                                                    @checked($currentResearchCallId === (string) $researchCall->id)
                                                >

                                                <span class="flex h-full overflow-hidden rounded-2xl border-2 border-gray-200 bg-white shadow-sm transition duration-200 group-hover:border-red-300 group-hover:shadow-md peer-checked:border-red-600 peer-checked:ring-2 peer-checked:ring-red-100 peer-focus-visible:ring-2 peer-focus-visible:ring-red-600 peer-focus-visible:ring-offset-2">
                                                    <span class="flex w-28 shrink-0 items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 sm:w-32">
                                                        @if ($researchCall->reference_image_path)
                                                            <img src="{{ route('research-calls.reference-image', $researchCall) }}" alt="Poster for {{ $researchCall->title }}" class="h-36 w-full object-contain p-1.5" loading="lazy">
                                                        @else
                                                            <span class="flex flex-col items-center gap-1.5 px-2 text-center text-gray-400">
                                                                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z" /></svg>
                                                                <span class="text-[9px] font-black uppercase tracking-wider">No poster</span>
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <span class="flex min-w-0 flex-1 flex-col p-4">
                                                        <span class="text-[10px] font-black uppercase tracking-wider text-green-700">Open · {{ $researchCall->academic_year }}</span>
                                                        <span class="mt-2 text-sm font-black leading-5 text-gray-900">{{ $researchCall->title }}</span>
                                                        <span class="mt-auto pt-4 text-[11px] font-semibold leading-5 text-gray-500">
                                                            Closes {{ $researchCall->closes_at->format('M j, Y') }}<br>
                                                            Up to ₱{{ number_format($researchCall->budgetCeiling()) }}
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="pointer-events-none absolute right-2.5 top-2.5 rounded-full bg-red-600 p-1 text-white opacity-0 shadow-sm transition peer-checked:opacity-100">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-label="Selected"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        @error('research_call_id')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </fieldset>

                    <div>
                        <label for="project_title" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Title <span class="text-red-600">Required</span></label>
                        <input id="project_title" x-ref="projectTitle" name="project_title" type="text" value="{{ old('project_title') }}" maxlength="255" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Enter the complete research project title">
                        @error('project_title')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end">
                        <a href="{{ route('faculty.proposal-drafts.index') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Cancel</a>
                        <button type="submit" :disabled="submitting" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-red-300 sm:w-auto">
                            <span x-show="! submitting">Create draft and continue</span>
                            <span x-show="submitting" x-cloak>Creating draft...</span>
                        </button>
                    </div>
                </form>
            @endif
        </section>
    </div>
</x-app-layout>
