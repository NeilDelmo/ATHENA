@props(['section', 'compact' => false])
<div data-proposal-figure-section="{{ $section }}" @if ($compact) x-show="methodologyImagesFor('{{ $section }}').length > 0" x-cloak @endif class="{{ $compact ? 'proposal-section-figures mt-3' : 'mt-4 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-3 dark:border-slate-700 dark:bg-slate-950' }}" x-on:dragover.prevent x-on:drop.prevent.stop="handleMethodologyDrop($event, '{{ $section }}')">
    @unless ($compact)
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold text-gray-600 dark:text-slate-300">Drop images here for {{ config('detailed_proposal.image_sections.'.$section) }}.</p>
        <button type="button" x-on:click="openMethodologyImagePicker('{{ $section }}')" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Choose images</button>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">PNG, JPG, GIF, or BMP · up to 10 MB each · 20 figures per proposal. Figures appear before this section’s text.</p>
    @endunless
    <div class="mt-3 space-y-3">
        <template x-for="image in methodologyImagesFor('{{ $section }}')" :key="image.clientId">
            <article class="rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-col gap-4 sm:flex-row">
                    <a :href="image.previewUrl" target="_blank" rel="noopener" class="flex w-full items-center justify-center rounded-lg bg-gray-50 p-2 sm:w-48 dark:bg-slate-950" title="Open full-size preview"><img :src="image.previewUrl" :alt="image.caption || 'Proposal figure'" class="max-h-48 w-full object-contain"></a>
                    <div class="min-w-0 flex-1 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="truncate text-xs font-bold text-gray-800 dark:text-slate-200" x-text="image.originalFilename"></p>
                            <button type="button" draggable="true" x-on:dragstart="startMethodologyImageDrag(image.clientId); $event.dataTransfer.setData('text/plain', image.clientId)" x-on:dragend="draggedMethodologyImage = ''" class="cursor-grab rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-600 dark:border-slate-600 dark:text-slate-300">Drag to another section</button>
                        </div>
                        <label class="block text-xs font-bold text-gray-600 dark:text-slate-300" :for="`methodology-image-caption-${image.clientId}`"><span x-text="`Figure ${methodologyImageFigureNumber(image)} title (required)`"></span>
                            <input :id="`methodology-image-caption-${image.clientId}`" :name="`methodology_images[${methodologyImageIndex(image)}][caption]`" type="text" required maxlength="500" x-model="image.caption" placeholder="Describe this figure" class="mt-1 block w-full rounded-lg border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                        </label>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="block text-xs font-bold text-gray-600 dark:text-slate-300">Section<select :value="image.section" x-on:change="moveMethodologyImageToSection(image, $event.target.value)" class="mt-1 block w-full rounded-lg border-gray-300 text-xs dark:border-slate-600 dark:bg-slate-950 dark:text-white">@foreach (config('detailed_proposal.image_sections') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                            <label class="block text-xs font-bold text-gray-600 dark:text-slate-300">Alignment<select x-model="image.alignment" class="mt-1 block w-full rounded-lg border-gray-300 text-xs dark:border-slate-600 dark:bg-slate-950 dark:text-white"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></label>
                            <label class="block text-xs font-bold text-gray-600 dark:text-slate-300">Size<select x-model="image.size" class="mt-1 block w-full rounded-lg border-gray-300 text-xs dark:border-slate-600 dark:bg-slate-950 dark:text-white"><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option></select></label>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <label :for="`methodology-image-file-${image.clientId}`" class="cursor-pointer rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 dark:border-slate-600 dark:text-slate-200">Replace image</label>
                            <button type="button" x-on:click="moveMethodologyImage(image, -1)" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 dark:border-slate-600 dark:text-slate-200">Move up</button>
                            <button type="button" x-on:click="moveMethodologyImage(image, 1)" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 dark:border-slate-600 dark:text-slate-200">Move down</button>
                            <button type="button" x-on:click="removeMethodologyImage(image)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-700 dark:text-red-300">Remove</button>
                        </div>
                    </div>
                </div>
                <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][id]`" :value="image.id">
                <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][client_id]`" :value="image.clientId">
                <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][section]`" :value="image.section">
                <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][alignment]`" :value="image.alignment">
                <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][size]`" :value="image.size">
                <input :id="`methodology-image-file-${image.clientId}`" :name="`methodology_images[${methodologyImageIndex(image)}][image]`" type="file" accept="image/jpeg,image/png,image/gif,image/bmp" class="sr-only" x-on:change="replaceMethodologyImage(image, $event.target.files)">
            </article>
        </template>
    </div>
</div>
