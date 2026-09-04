@props(['configuration'])

<div x-data="pdfAnnotationWorkspace" data-pdf-annotation-config='@json(array_merge($configuration, ["fitWidth" => true]))' class="revision-pdf-viewer">
    <p x-show="loading" role="status" class="absolute inset-x-0 top-5 z-10 text-center text-sm text-gray-700">Loading submitted PDF…</p>
    <p x-show="loadError" x-cloak role="alert" class="m-4 rounded-lg bg-red-50 p-4 text-sm text-red-800" x-text="loadError"></p>
    <div x-ref="viewer" class="pdf-annotation-viewer revision-pdf-pages"></div>
</div>
