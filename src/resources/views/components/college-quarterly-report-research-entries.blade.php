@php
    $fields = ['title' => 'Title', 'duration' => 'Duration', 'personnel' => 'Personnel(s) involved', 'status' => 'Status'];
    $inputClass = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
@endphp

<section {{ $attributes->class(['dashboard-panel overflow-hidden']) }} aria-labelledby="manual-research-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5 dark:border-slate-800">
        <div>
            <h3 id="manual-research-heading" class="font-bold text-gray-950 dark:text-white">Additional faculty research</h3>
            <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">Include research outside Athena’s monitoring, such as externally funded or unfunded projects. These entries appear in Section II when you preview, print, or download the report.</p>
        </div>
        <button type="button" class="dashboard-action" @click="addActivity('manual_research')" :disabled="manual_research.length >= 100">Add research project</button>
    </div>
    <p x-show="!manual_research.length" class="p-5 text-sm text-slate-500 dark:text-slate-400">No additional faculty research added.</p>
    <template x-for="(research, index) in manual_research" :key="research.id">
        <fieldset class="space-y-4 border-b border-gray-100 p-5 dark:border-slate-800" data-quarterly-manual-research>
            <legend class="sr-only" x-text="`Additional faculty research ${index + 1}`"></legend>
            <input type="hidden" :name="`manual_research[${index}][id]`" :value="research.id">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300" x-text="`Research project ${index + 1}`"></p>
                <button type="button" @click="manual_research.splice(index, 1)" class="dashboard-action" :aria-label="`Remove additional faculty research ${index + 1}`">Remove project</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($fields as $field => $label)
                    <div @class(['sm:col-span-2' => $field === 'title'])>
                        <label :for="`manual-research-{{ $field }}-${research.id}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ $label }}</label>
                        @if ($field === 'personnel')
                            <textarea :id="`manual-research-{{ $field }}-${research.id}`" :name="`manual_research[${index}][{{ $field }}]`" x-model="research.{{ $field }}" maxlength="2000" rows="2" required class="{{ $inputClass }}"></textarea>
                        @else
                            <input :id="`manual-research-{{ $field }}-${research.id}`" :name="`manual_research[${index}][{{ $field }}]`" x-model="research.{{ $field }}" maxlength="{{ $field === 'title' ? 1000 : 255 }}" @if ($field === 'duration') placeholder="e.g. 12 months" @endif required class="{{ $inputClass }}">
                        @endif
                    </div>
                @endforeach
            </div>
        </fieldset>
    </template>
</section>
