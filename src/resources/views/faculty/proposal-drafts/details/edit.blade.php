<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">← Proposal package</a>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Project Details</h2>
            <p class="mt-1 text-xs text-gray-500">Enter shared information once; Attachment A will use it automatically.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <p class="font-black">Research call</p>
                <p class="mt-1">{{ $proposalDraft->researchCall->title }}</p>
            </div>

            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-bold">Please correct the following:</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="{{ route('faculty.proposal-drafts.details.update', $proposalDraft) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="project_title" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Title <span class="text-red-600">Required</span></label>
                    <input id="project_title" name="project_title" type="text" value="{{ old('project_title', $proposalDraft->project_title) }}" maxlength="255" required autofocus class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                    @error('project_title')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <div>
                        <label for="duration_months" class="block text-xs font-black uppercase tracking-wider text-gray-600">Total Duration <span class="text-red-600">Required</span></label>
                        <div class="relative mt-2"><input id="duration_months" name="duration_months" type="number" min="1" max="12" value="{{ old('duration_months', $proposalDraft->duration_months) }}" required class="block w-full rounded-xl border-gray-300 pr-20 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-gray-500">months</span></div>
                        <p class="mt-2 text-[11px] text-gray-500">Attachment A supports Year 1, M1–M12.</p>
                        @error('duration_months')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="planned_start" class="block text-xs font-black uppercase tracking-wider text-gray-600">Planned Start <span class="text-red-600">Required</span></label>
                        <x-date-picker id="planned_start" name="planned_start" :value="$proposalDraft->planned_start?->toDateString()" required class="mt-2" />
                        @error('planned_start')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="planned_end" class="block text-xs font-black uppercase tracking-wider text-gray-600">Planned End <span class="text-red-600">Required</span></label>
                        <x-date-picker id="planned_end" name="planned_end" :value="$proposalDraft->planned_end?->toDateString()" required class="mt-2" />
                        @error('planned_end')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="project_leader" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Leader <span class="text-red-600">Required</span></label>
                    <input id="project_leader" name="project_leader" type="text" value="{{ old('project_leader', $proposalDraft->project_leader ?: auth()->user()->name) }}" maxlength="120" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                    <p class="mt-2 text-[11px] text-gray-500">This name appears under “Prepared by” in the official Work Plan.</p>
                    @error('project_leader')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end">
                    <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Cancel</a>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save and return</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
