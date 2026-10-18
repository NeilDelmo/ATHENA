@props(['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF', 'searchable' => false, 'initialPageExpression' => null, 'loadingLabel' => 'Loading submitted report…'])

<x-modal :name="$name" maxWidth="4xl" focusable class="!z-[140] sm:!py-[5vh]" data-submitted-report-preview-modal {{ $attributes }}>
    <template x-if="show">
        <x-submitted-report-document :name="$name" :title="$title" :description="$description" :preview-url="$previewUrl" :download-url="$downloadUrl" :viewer-label="$viewerLabel" :searchable="$searchable" :initial-page-expression="$initialPageExpression" :loading-label="$loadingLabel" expanded />
    </template>
</x-modal>
