<x-app-layout>
    <x-slot name="header">
        <div>
            <x-back-link fixed href="{{ route('faculty.proposal-drafts.index') }}">Back to proposal workspace</x-back-link>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">New Proposal</h2>
            <p class="mt-1 text-xs text-gray-500">Start with a project title. Complete your papers and submit whenever you are ready.</p>
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

                <form
                    action="{{ route('faculty.proposal-drafts.store') }}"
                    method="POST"
                    x-data="{ submitting: false }"
                    @submit="submitting = true"
                    :aria-busy="submitting"
                    class="space-y-6"
                >
                    @csrf

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
        </section>
    </div>
</x-app-layout>
