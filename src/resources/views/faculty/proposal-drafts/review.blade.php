<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Review and Turn In" subtitle="Review the five proposal papers and project team. Two assessment forms are included automatically in the seven-PDF package.">
            <x-slot name="actions">
                <x-back-link fixed href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}">Back to proposal package</x-back-link>
                <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-black {{ $readyToSubmit ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $readyToSubmit ? 'Ready to turn in' : 'Incomplete package' }}</span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-black">This proposal package cannot be turned in yet.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @elseif (! $readyToPrepare)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete the items below before submitting.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($readinessErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <livewire:proposal-draft-review-package :proposal-draft="$proposalDraft" />
    </div>
</x-app-layout>
