@props(['period'])

@php
    $fields = ['title' => 'Title of IP asset', 'type' => 'Type of IP asset', 'inventors' => 'Inventors / authors', 'date' => 'Date Submitted', 'remarks' => 'Remarks (optional)', 'evidence_url' => 'Evidence link (optional)'];
    $inputClass = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
@endphp

<section class="dashboard-panel overflow-hidden" aria-labelledby="ip-assets-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5 dark:border-slate-800">
        <div>
            <h2 id="ip-assets-heading" class="text-lg font-bold text-gray-950 dark:text-white">Intellectual Property Assets</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">Add IP assets submitted during this quarter for the Research Head’s monitoring report. Leave empty if none.</p>
        </div>
        <button type="button" class="dashboard-action" @click="addActivity('ip_assets')" :disabled="ip_assets.length >= 100">Add IP asset</button>
    </div>
    <p x-show="!ip_assets.length" class="p-5 text-sm text-slate-500 dark:text-slate-400">No IP assets added.</p>
    <template x-for="(asset, index) in ip_assets" :key="asset.id">
        <fieldset class="space-y-4 border-b border-gray-100 p-5 dark:border-slate-800" data-quarterly-ip-asset>
            <legend class="sr-only" x-text="`IP asset ${index + 1}`"></legend>
            <input type="hidden" :name="`ip_assets[${index}][id]`" :value="asset.id">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300" x-text="`IP asset ${index + 1}`"></p>
                <button type="button" @click="ip_assets.splice(index, 1)" class="dashboard-action" :aria-label="`Remove IP asset ${index + 1}`">Remove IP asset</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($fields as $field => $label)
                    <div @class(['sm:col-span-2' => in_array($field, ['title', 'remarks', 'evidence_url'], true)])>
                        <label :for="`ip-{{ $field }}-${asset.id}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ $label }}</label>
                        @if ($field === 'type')
                            <select :id="`ip-{{ $field }}-${asset.id}`" :name="`ip_assets[${index}][{{ $field }}]`" x-model="asset.{{ $field }}" required class="{{ $inputClass }}">
                                <option value="">Select IP type</option>
                                @foreach (\App\Models\CollegeQuarterlyReport::IP_ASSET_TYPES as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        @elseif (in_array($field, ['inventors', 'remarks'], true))
                            <textarea :id="`ip-{{ $field }}-${asset.id}`" :name="`ip_assets[${index}][{{ $field }}]`" x-model="asset.{{ $field }}" rows="2" maxlength="2000" @required($field === 'inventors') class="{{ $inputClass }}"></textarea>
                        @else
                            <input :id="`ip-{{ $field }}-${asset.id}`" :name="`ip_assets[${index}][{{ $field }}]`" x-model="asset.{{ $field }}"
                                @if ($field === 'date') type="date" min="{{ $period['start']->toDateString() }}" max="{{ $period['end']->toDateString() }}"
                                @elseif ($field === 'evidence_url') type="url" maxlength="2048" placeholder="https://..."
                                @else maxlength="1000" @endif
                                @required($field !== 'evidence_url') class="{{ $inputClass }}">
                        @endif
                    </div>
                @endforeach
            </div>
        </fieldset>
    </template>
</section>
