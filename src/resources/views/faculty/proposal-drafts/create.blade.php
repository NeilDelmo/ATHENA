<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.index') }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">← Proposal Workspace</a>
            <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">New Proposal</h2>
            <p class="mt-1 text-xs text-gray-500">Choose the research call and give this project a working title.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-8">
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-bold">Please correct the following:</p>
                    <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($researchCalls->isEmpty())
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-center text-amber-900">
                    <h3 class="font-black">No research call is open</h3>
                    <p class="mt-2 text-sm leading-6">You can resume existing drafts, but a new draft cannot be created until a call accepts submissions.</p>
                    <a href="{{ route('faculty.proposal-drafts.index') }}" class="mt-5 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-sm font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Return to saved drafts</a>
                </div>
            @else
                <form action="{{ route('faculty.proposal-drafts.store') }}" method="POST" class="space-y-6">
                    @csrf
                    <div>
                        <label for="research_call_id" class="block text-xs font-black uppercase tracking-wider text-gray-600">Research Call <span class="text-red-600">Required</span></label>
                        <select id="research_call_id" name="research_call_id" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                            <option value="">Select an open research call</option>
                            @foreach ($researchCalls as $researchCall)
                                <option value="{{ $researchCall->id }}" @selected((string) old('research_call_id') === (string) $researchCall->id)>{{ $researchCall->title }} — closes {{ $researchCall->closes_at->format('M j, Y') }}</option>
                            @endforeach
                        </select>
                        @error('research_call_id')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="project_title" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Title <span class="text-red-600">Required</span></label>
                        <input id="project_title" name="project_title" type="text" value="{{ old('project_title') }}" maxlength="255" required autofocus class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Enter the complete research project title">
                        @error('project_title')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-6 sm:flex-row sm:justify-end">
                        <a href="{{ route('faculty.proposal-drafts.index') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Cancel</a>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Create draft and continue</button>
                    </div>
                </form>
            @endif
        </section>
    </div>
</x-app-layout>
