<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <x-back-link href="{{ route('topics.show', $topic) }}#proposal-review">Back to submitted proposal</x-back-link>
                <h2 class="mt-3 font-serif text-3xl font-bold tracking-tight text-gray-950">Proposal review workflow</h2>
                <p class="mt-2 max-w-3xl text-base leading-7 text-gray-600">Review the faculty package, record the completed GAD assessment, then add the co-evaluator’s screening form.</p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-gray-300 bg-white px-3.5 py-2 text-sm font-bold text-gray-700 shadow-sm">
                {{ $latestVersion ? 'Version '.$latestVersion->version_number : 'No submitted version' }}
            </span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        <x-research-head-file-workspace :topic="$topic" :workspace="$workspace" />
    </div>
</x-app-layout>
