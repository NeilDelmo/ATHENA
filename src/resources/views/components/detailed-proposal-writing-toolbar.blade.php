<x-proposal-writing-toolbar toolbar-id="detailed-proposal-tools" data-proposal-writing-toolbar class="detailed-proposal-writing-toolbar" aria-label="Detailed proposal writing tools">
    <x-slot:context><p class="proposal-writing-target">Editing: <span data-writing-target>Select a writing field</span></p></x-slot:context>
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Text formatting and insertion">

        <div class="proposal-writing-buttons">
            <div class="proposal-writing-action-group" role="group" aria-label="Text style">
                @foreach (['bold' => 'Bold', 'italic' => 'Italic', 'underline' => 'Underline'] as $command => $label)
                    <button type="button" data-writing-command="{{ $command }}" aria-pressed="false" disabled><x-proposal-writing-icon :name="$command" /><span>{{ $label }}</span></button>
                @endforeach
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Lists">
                <button type="button" data-writing-command="insertUnorderedList" aria-pressed="false" disabled><x-proposal-writing-icon name="bullets" /><span>Bullets</span></button>
                <button type="button" data-writing-command="insertOrderedList" aria-pressed="false" disabled><x-proposal-writing-icon name="numbered-list" /><span>Numbered list</span></button>
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="History">
                @foreach (['undo' => 'Undo', 'redo' => 'Redo'] as $command => $label)
                    <button type="button" data-writing-command="{{ $command }}" disabled><x-proposal-writing-icon :name="$command" /><span>{{ $label }}</span></button>
                @endforeach
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Insert">
                <button type="button" data-writing-image disabled title="Select a narrative section that supports figures"><x-proposal-writing-icon name="image" /><span>Image</span></button>
                <button type="button" data-writing-table aria-expanded="false" aria-controls="proposal-table-picker" disabled><x-proposal-writing-icon name="table" /><span>Table</span></button>
                <button type="button" data-writing-cite disabled title="Place the cursor or select text in a narrative section"><x-proposal-writing-icon name="source" /><span>Insert citation</span></button>
                <button type="button" data-writing-sources title="Search, import, and manage sources for this paper"><x-proposal-writing-icon name="source" /><span>Sources</span></button>
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Field view">
                <button type="button" data-writing-expand aria-pressed="false" disabled><x-proposal-writing-icon name="expand" class="proposal-writing-expand-icon" /><x-proposal-writing-icon name="shrink" class="proposal-writing-shrink-icon" /><span data-writing-expand-label>Expand field</span></button>
            </div>
        </div>
    </div>
    <div data-writing-table-tools hidden class="proposal-writing-buttons" role="toolbar" aria-label="Selected table tools">
        @foreach (['add-row' => 'Add row', 'add-column' => 'Add column', 'remove-row' => 'Remove row', 'remove-column' => 'Remove column', 'remove-table' => 'Remove table'] as $action => $label)
            <button type="button" data-writing-table-action="{{ $action }}"><x-proposal-writing-icon :name="$action" /><span>{{ $label }}</span></button>
        @endforeach
    </div>
    <p class="proposal-writing-hint">Place the cursor or select text in a narrative field to insert a citation. Open Sources to find or import a paper. Add images with Image or drop them into a supported section.</p>
    <div id="proposal-table-picker" data-writing-table-picker hidden class="proposal-table-picker" role="group" aria-label="Insert a table">
        <label>Rows <input type="number" min="1" max="100" step="1" value="3" data-table-rows></label>
        <label>Columns <input type="number" min="1" max="12" step="1" value="2" data-table-columns></label>
        <label class="proposal-table-header-option"><input type="checkbox" checked data-table-header> Header row</label>
        <button type="button" data-writing-insert-table><x-proposal-writing-icon name="table" /><span>Insert table</span></button>
    </div>
    <span data-writing-status class="sr-only" role="status" aria-live="polite"></span>
</x-proposal-writing-toolbar>
