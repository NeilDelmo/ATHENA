<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Project Details" subtitle="Enter shared information once; Attachment A will use it automatically.">
            <x-slot name="actions">
                <x-back-link fixed data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}">Exit editor</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $minimumProjectDate = now()->toDateString();
        $initialDuration = old('duration_months', $proposalDraft->duration_months);
        $initialPlannedStart = old('planned_start', $proposalDraft->planned_start?->toDateString());
        $initialPlannedEnd = old('planned_end', $proposalDraft->planned_end?->toDateString());
    @endphp

    <div
        class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}"
        data-project-details-autosave="true"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}"
        x-data="proposalDraftProjectDetails({
            initialDuration: @js($initialDuration),
            initialStart: @js($initialPlannedStart),
            initialEnd: @js($initialPlannedEnd),
            autoSave: true,
        })"
    >
        @if (session('success'))
            <x-proposal-alert class="mb-6">{{ session('success') }}</x-proposal-alert>
        @endif

        <x-paper-editor-submit-status />
        <x-proposal-autosave-status />
        <x-proposal-collaboration-monitor
            :loaded-version="(int) old('draft_version', $proposalDraft->lock_version)"
            :state-url="route('faculty.proposal-drafts.edit-state', [$proposalDraft, 'details', 0])"
            :reload-url="route('faculty.proposal-drafts.details.edit', $proposalDraft)"
            label="project details"
        />

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">

            @if ($errors->any())
                <x-proposal-alert type="error" class="mb-6">
                    <p class="font-bold">Please correct the following:</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </x-proposal-alert>
            @endif

            <form data-paper-form data-project-details-autosave-form action="{{ route('faculty.proposal-drafts.details.update', $proposalDraft) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')
                <input type="hidden" name="draft_version" value="{{ old('draft_version', $proposalDraft->lock_version) }}">

                <div>
                    <label for="project_title" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Title <span class="text-red-600">Required</span></label>
                    <input id="project_title" name="project_title" type="text" value="{{ old('project_title', $proposalDraft->project_title) }}" maxlength="255" required autofocus class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                    @error('project_title')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <label for="duration_months" class="block text-xs font-black uppercase tracking-wider text-gray-600">Total Duration <span class="text-red-600">Required</span></label>
                        <div class="relative mt-2"><input id="duration_months" name="duration_months" type="number" min="1" max="{{ config('work_plan.max_duration_months') }}" x-model.number="durationMonths" required class="block w-full rounded-xl border-gray-300 pr-20 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-gray-500">months</span></div>
                        <p class="mt-2 text-[11px] text-gray-500">Attachment A adds one M1–M12 sheet for each 12-month project period.</p>
                        @error('duration_months')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <x-proposal-planned-date field="start" :research-call="$proposalDraft->researchCall" :minimum-date="$minimumProjectDate" />
                    <x-proposal-planned-date field="end" :research-call="$proposalDraft->researchCall" :minimum-date="$minimumProjectDate" />
                </div>

                <div>
                    <label for="project_leader" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Leader <span class="text-red-600">Required</span></label>
                    <input id="project_leader" name="project_leader" type="text" list="proposal-workspace-people" value="{{ old('project_leader', $proposalDraft->project_leader ?: $proposalDraft->owner->name) }}" maxlength="120" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                    <datalist id="proposal-workspace-people">@foreach ($workspacePeople as $workspacePerson)<option value="{{ $workspacePerson['name'] }}">{{ $workspacePerson['email'] }}</option>@endforeach</datalist>
                    @error('project_leader')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>

                <noscript>
                    <div class="flex justify-end border-t border-gray-100 pt-6">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Save project details</button>
                    </div>
                </noscript>
            </form>
        </section>
    </div>
</x-app-layout>
