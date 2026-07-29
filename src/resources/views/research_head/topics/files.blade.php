<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <x-back-link href="{{ route('topics.show', $topic) }}#proposal-review">Back to submitted proposal</x-back-link>
                <h2 class="mt-3 text-2xl font-black tracking-tight text-gray-900">Review and Upload Files</h2>
                <p class="mt-1 text-sm leading-6 text-gray-600">Manage revision copies, required signed PDFs, and supplemental records for this proposal.</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-cyan-100 px-3 py-1.5 text-sm font-black text-cyan-800">
                {{ $latestVersion ? 'Version '.$latestVersion->version_number.' · '.$headUploadedFiles->count().' uploaded' : 'No submitted version' }}
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
