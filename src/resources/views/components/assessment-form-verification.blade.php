@props(['formName', 'manualReviewRequired' => false, 'projectTitleConfirmedByUploader' => false, 'showGuidance' => true])

<div data-assessment-form-verification x-data="{
    previewUrl: null, previewed: false, confirmed: false,
    init() { this.$watch('files', () => { if (this.previewUrl) URL.revokeObjectURL(this.previewUrl); this.previewUrl = null; this.previewed = false; this.confirmed = false; }); },
    preview() { if (!this.files[0]) return; if (this.previewUrl) URL.revokeObjectURL(this.previewUrl); this.previewUrl = URL.createObjectURL(this.files[0]); this.previewed = /\.pdf$/i.test(this.files[0].name); },
    destroy() { if (this.previewUrl) URL.revokeObjectURL(this.previewUrl); }
}" class="mt-3 space-y-3">
    @if ($showGuidance)
        <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">@if ($projectTitleConfirmedByUploader)We identify the {{ $formName }} and read its score. An exact project title match is not required. Check that the checklist belongs to this project and includes the verifier’s signature.@else We check the official {{ $formName }} and project title before saving. Scanned PDFs use the configured AI reader on the first two pages. Signature checks remain your responsibility.@endif</p>
    @endif
    <button x-show="files.length" x-cloak type="button" @click="preview()" class="rh-button-secondary">Preview selected form</button>
    <template x-if="previewUrl && /\.pdf$/i.test(files[0]?.name ?? '')">
        <div x-data="pdfAnnotationWorkspace({ pdfUrl: previewUrl, annotations: [], canAnnotate: false, fitWidth: true })" class="revision-pdf-viewer !h-96">
            <p x-show="loading" role="status" class="absolute inset-x-0 top-3 text-center text-sm text-gray-600 dark:text-gray-300">Loading selected form…</p>
            <p x-show="loadError" x-cloak role="alert" class="m-3 text-sm text-red-700 dark:text-red-300" x-text="loadError"></p>
            <div x-ref="viewer" tabindex="0" aria-label="Selected {{ $formName }} preview" class="pdf-annotation-viewer revision-pdf-pages"></div>
        </div>
    </template>
    <a x-show="previewUrl && /\.docx$/i.test(files[0]?.name ?? '')" x-cloak :href="previewUrl" :download="files[0]?.name" @click="previewed = true" class="inline-flex text-sm font-bold text-red-700 underline dark:text-red-300">Open selected DOCX to review all pages</a>
    @if ($manualReviewRequired)
        <label data-assessment-manual-confirmation class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:border-amber-800 dark:bg-amber-950/20 dark:text-amber-100">
            <input type="checkbox" name="assessment_form_manually_confirmed" value="1" x-model="confirmed" :disabled="!previewed" class="mt-1 rounded border-gray-300 text-red-700 focus:ring-red-700 disabled:opacity-40">
            <span>Automatic verification was inconclusive. After previewing every page, I confirm this is the official {{ $formName }} for this project and that the required signatures are present. This manual check will be recorded with my name.</span>
        </label>
    @endif
</div>
