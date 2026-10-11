@props(['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF'])

<x-modal :name="$name" maxWidth="6xl" focusable class="!z-[140]" data-submitted-report-preview-modal {{ $attributes }}>
    <template x-if="show">
        <x-submitted-report-document :name="$name" :title="$title" :description="$description" :preview-url="$previewUrl" :download-url="$downloadUrl" :viewer-label="$viewerLabel" expanded />
    </template>
</x-modal>
