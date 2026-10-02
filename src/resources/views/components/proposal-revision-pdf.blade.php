@props(['configuration', 'loadingLabel' => 'Loading submitted PDF…', 'viewerLabel' => 'Submitted document'])

<div x-data="pdfAnnotationWorkspace" data-pdf-annotation-config='@json(array_merge($configuration, ["fitWidth" => true]))' {{ $attributes->merge(['class' => 'revision-pdf-viewer']) }}>
    <p x-show="loading" role="status" class="absolute inset-x-0 top-5 z-10 text-center text-sm text-gray-700">{{ $loadingLabel }}</p>
    <div x-show="loadError" x-cloak role="alert" class="m-4 rounded-lg bg-red-50 p-4 text-base text-red-800">
        <p x-text="loadError"></p>
        <button x-show="viewerRefreshRequired" type="button" @click="window.location.reload()" class="mt-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Reload page</button>
        <button x-show="!viewerRefreshRequired" type="button" @click="loadPdf()" class="mt-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Try again</button>
    </div>
    <div x-ref="viewer" tabindex="0" aria-label="{{ $viewerLabel }}" class="pdf-annotation-viewer revision-pdf-pages"></div>
</div>
