@props(['section', 'title', 'period', 'projects' => []])

@php
    $fields = ['event' => $section === 'publications' ? 'Journal Name/Conference' : 'Event/Activity'];
    if ($section !== 'seminars') {
        $fields['research_title'] = 'Research Title';
    }
    $fields['date'] = 'Date';
    if ($section !== 'publications') {
        $fields['place'] = 'Place';
    }
    $fields['personnel'] = 'Personnel(s) involved';
    $inputClass = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
@endphp

<section {{ $attributes->class(['dashboard-panel overflow-hidden']) }} aria-labelledby="{{ $section }}-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5 dark:border-slate-800">
        <div>
            <h3 id="{{ $section }}-heading" class="font-bold text-gray-950 dark:text-white">{{ $title }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Enter this quarter’s activities and attach proof to each row. Leave empty if none.</p>
        </div>
        <button type="button" class="dashboard-action" @click="addActivity('{{ $section }}')" :disabled="{{ $section }}.length >= 100">Add {{ $section === 'seminars' ? 'seminar or training' : str($section)->singular() }}</button>
    </div>
    <p x-show="!{{ $section }}.length" class="p-5 text-sm text-slate-500 dark:text-slate-400">No activities added.</p>
    <template x-for="(activity, index) in {{ $section }}" :key="activity.id">
        <fieldset class="space-y-4 border-b border-gray-100 p-5 dark:border-slate-800" data-quarterly-activity="{{ $section }}">
            <legend class="sr-only" x-text="`{{ $title }} row ${index + 1}`"></legend>
            <input type="hidden" :name="`{{ $section }}[${index}][id]`" :value="activity.id">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300" x-text="`Row ${index + 1}`"></p>
                <button type="button" @click="{{ $section }}.splice(index, 1)" class="rounded text-sm font-semibold text-red-700 focus-visible:outline focus-visible:outline-2 dark:text-red-300" :aria-label="`Remove {{ $title }} row ${index + 1}`">Remove activity</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($fields as $field => $label)
                    <div @class(['sm:col-span-2' => $field === 'research_title'])>
                        <label :for="`{{ $section }}-{{ $field }}-${activity.id}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ $label }}</label>
                        @if ($field === 'personnel')
                            <textarea :id="`{{ $section }}-{{ $field }}-${activity.id}`" :name="`{{ $section }}[${index}][{{ $field }}]`" x-model="activity.{{ $field }}" maxlength="2000" rows="2" required class="{{ $inputClass }}"></textarea>
                        @else
                            <input :id="`{{ $section }}-{{ $field }}-${activity.id}`" :name="`{{ $section }}[${index}][{{ $field }}]`" x-model="activity.{{ $field }}"
                                @if ($field === 'date') type="date" min="{{ $period['start']->toDateString() }}" max="{{ $period['end']->toDateString() }}" @else maxlength="1000" @endif
                                required class="{{ $inputClass }}">
                        @endif
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <label :for="`{{ $section }}-project-${activity.id}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Project folder (optional)</label>
                    <select :id="`{{ $section }}-project-${activity.id}`" :name="`{{ $section }}[${index}][project_id]`" x-model="activity.project_id" class="{{ $inputClass }}">
                        <option value="">Create evidence folder using the research or activity title</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->title }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Linked evidence goes into the project’s files. Other evidence stays in this report’s title folder, organised by year and quarter.</p>
                </div>
            </div>
            <div class="space-y-3 border-t border-gray-100 pt-4 dark:border-slate-800">
                <label :for="`{{ $section }}-proof-${activity.id}`" class="block text-sm font-semibold text-gray-700 dark:text-slate-300">Proof for this row</label>
                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="`Evidence folder: ${activity.research_title || activity.event || 'Research or activity title'} / {{ $period['year'] }} / Q{{ $period['quarter'] }}`"></p>
                <p :id="`{{ $section }}-proof-help-${activity.id}`" class="text-xs text-slate-500 dark:text-slate-400">JPEG or PNG, up to 5 MB each; up to 5 images per row. At least one is required when submitting. Files must be selected again if validation fails.</p>
                <input :id="`{{ $section }}-proof-${activity.id}`" :name="`row_evidence[{{ $section }}][${activity.id}][]`" type="file" accept="image/jpeg,image/png" multiple :aria-describedby="`{{ $section }}-proof-help-${activity.id}`" class="block max-w-full text-sm text-gray-700 dark:text-slate-300">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="proof in rowProofs.filter(image => image.section === '{{ $section }}' && image.row_id === activity.id)" :key="proof.token">
                        <figure class="space-y-2">
                            <a :href="proof.url" target="_blank" rel="noopener" class="block rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><img :src="proof.url" :alt="proof.filename" loading="lazy" class="h-36 w-full rounded-lg object-contain"></a>
                            <figcaption class="break-words text-xs text-slate-500 dark:text-slate-400" x-text="proof.filename"></figcaption>
                            <label class="inline-flex items-center gap-2 text-sm text-red-700 dark:text-red-300"><input type="checkbox" name="remove_evidence[]" :value="proof.token" x-model="removedProofs" class="rounded border-gray-300 text-brand focus:ring-brand"> Remove proof when saving</label>
                        </figure>
                    </template>
                </div>
            </div>
        </fieldset>
    </template>
</section>
