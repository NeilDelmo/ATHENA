@props(['proposalDraft', 'paper', 'projectDetailsComplete'])

@php
    $formRoutes = 'faculty.proposal-drafts.'.$paper['slug'];
    $formPreview = [
        'label' => $paper['label'],
        'previewUrl' => route($formRoutes.'.preview', $proposalDraft),
        'downloadUrl' => route($formRoutes.'.download', $proposalDraft),
    ];
    $panelId = $paper['slug'].'-preview-panel';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$paper['label']" :subtitle="$proposalDraft->project_title">
            <x-slot name="actions">
                <x-back-link fixed href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Back to project</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div data-automatic-assessment-preview x-data="proposalAssessmentPreview(@js(['initialForm' => $formPreview]))" @resize.window.debounce.150ms="resizeProposalPaperPreview()" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div :inert="previewFullscreen" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-gray-600 dark:text-slate-300">{{ $projectDetailsComplete ? 'Added automatically to your submission. Assessment is completed during review.' : 'Complete Project Details to prepare this form.' }}</p>
            <button type="button" data-assessment-preview-trigger @click="openAssessmentPreview(@js($formPreview))" aria-haspopup="dialog" aria-controls="{{ $panelId }}" class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Preview form</button>
        </div>
        <x-proposal-paper-preview :panel-id="$panelId" :preview-label="$paper['label'].' preview'" :frame-title="$paper['label'].' preview'" />
    </div>
</x-app-layout>
