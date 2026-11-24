@props(['period', 'returned' => false])

<x-proposal-writing-toolbar toolbar-id="quarterly-report-tools" data-quarterly-report-writing-toolbar aria-label="Quarterly report tools">
    <x-slot:context>
        <p class="proposal-writing-target">Editing: <span>Q{{ $period['quarter'] }} {{ $period['year'] }} accomplishment report</span></p>
    </x-slot:context>

    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Quarterly report actions">
        <div class="proposal-writing-buttons">
            <button type="submit" form="quarterly-report-form" name="intent" value="save">Save draft</button>
            <button type="submit" form="quarterly-report-form" name="intent" value="preview">Save and preview</button>
            <button type="submit" form="quarterly-report-form" name="intent" value="download">Save and download Word</button>
            <button type="submit" form="quarterly-report-form" name="intent" value="submit" @disabled(now()->lessThanOrEqualTo($period['end'])) class="!border-red-700 !bg-red-700 !text-white hover:!bg-red-800">{{ $returned ? 'Resubmit quarterly report' : 'Submit quarterly report' }}</button>
        </div>
    </div>
    <x-document-formatting-tools />
    <p class="proposal-writing-hint">Select text in Other Accomplishments to format it. Preview and Word download save your current entries first.</p>
</x-proposal-writing-toolbar>
