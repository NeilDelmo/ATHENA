<x-proposal-writing-toolbar toolbar-id="detailed-proposal-tools" data-proposal-writing-toolbar class="detailed-proposal-writing-toolbar" aria-label="Detailed proposal writing tools">
    <x-slot:context><p class="proposal-writing-target">Editing: <span data-writing-target>Select a writing field</span></p></x-slot:context>
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Text formatting and insertion">

        <div class="proposal-writing-buttons">
            <div class="proposal-writing-action-group" role="group" aria-label="Text style">
                @foreach (['bold' => 'Bold', 'italic' => 'Italic'] as $command => $label)
                    <button type="button" data-writing-command="{{ $command }}" aria-label="{{ $label }}" title="{{ $label }}" aria-pressed="false" disabled><x-proposal-writing-icon :name="$command" /><span>{{ $label }}</span></button>
                @endforeach
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="History">
                @foreach (['undo' => 'Undo', 'redo' => 'Redo'] as $command => $label)
                    <button type="button" data-writing-command="{{ $command }}" aria-label="{{ $label }}" title="{{ $label }}" disabled><x-proposal-writing-icon :name="$command" /><span>{{ $label }}</span></button>
                @endforeach
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Additional tools">
                @foreach (['lists' => 'Lists', 'insert' => 'Insert', 'more' => 'More'] as $menu => $label)
                    <button type="button" data-writing-menu-toggle="{{ $menu }}" aria-haspopup="menu" aria-expanded="false" aria-controls="proposal-writing-{{ $menu }}-menu" disabled><span>{{ $label }}</span><svg class="proposal-writing-menu-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg></button>
                @endforeach
            </div>
        </div>
        <div class="proposal-writing-buttons proposal-writing-primary-action" role="group" aria-label="Insert">
            <button type="button" data-writing-cite class="proposal-writing-cite" disabled title="Insert an IEEE citation and add its reference automatically"><x-proposal-writing-icon name="source" /><span>Insert citation</span></button>
        </div>
    </div>
    <div id="proposal-writing-lists-menu" data-writing-menu="lists" hidden class="proposal-writing-menu" role="menu" aria-label="Lists">
        <button type="button" role="menuitem" tabindex="-1" data-writing-command="insertUnorderedList" disabled><x-proposal-writing-icon name="bullets" /><span>Bullets</span></button>
        <button type="button" role="menuitem" tabindex="-1" data-writing-command="insertOrderedList" disabled><x-proposal-writing-icon name="numbered-list" /><span>Numbered list</span></button>
    </div>
    <div id="proposal-writing-insert-menu" data-writing-menu="insert" hidden class="proposal-writing-menu" role="menu" aria-label="Insert">
        <button type="button" role="menuitem" tabindex="-1" data-writing-image disabled title="Select a narrative section that supports figures"><x-proposal-writing-icon name="image" /><span>Image</span></button>
        <button type="button" role="menuitem" tabindex="-1" data-writing-table aria-expanded="false" aria-controls="proposal-table-picker" disabled><x-proposal-writing-icon name="table" /><span>Table</span></button>
    </div>
    <div id="proposal-writing-more-menu" data-writing-menu="more" hidden class="proposal-writing-menu" role="menu" aria-label="More formatting">
        <button type="button" role="menuitem" tabindex="-1" data-writing-command="underline" disabled><x-proposal-writing-icon name="underline" /><span>Underline</span></button>
        <button type="button" role="menuitem" tabindex="-1" data-writing-command="removeFormat" disabled><span>Clear formatting</span></button>
    </div>
    <div data-writing-table-tools hidden class="proposal-writing-buttons" role="toolbar" aria-label="Selected table tools">
        @foreach (['add-row' => 'Add row', 'add-column' => 'Add column', 'remove-row' => 'Remove row', 'remove-column' => 'Remove column', 'remove-table' => 'Remove table'] as $action => $label)
            <button type="button" data-writing-table-action="{{ $action }}"><x-proposal-writing-icon :name="$action" /><span>{{ $label }}</span></button>
        @endforeach
    </div>
    <div id="proposal-table-picker" data-writing-table-picker hidden class="proposal-table-picker" role="group" aria-label="Insert a table">
        <label>Rows <input type="number" min="1" max="100" step="1" value="3" data-table-rows></label>
        <label>Columns <input type="number" min="1" max="12" step="1" value="2" data-table-columns></label>
        <label class="proposal-table-header-option"><input type="checkbox" checked data-table-header> Header row</label>
        <button type="button" data-writing-insert-table><x-proposal-writing-icon name="table" /><span>Insert table</span></button>
    </div>
    <span data-writing-status class="sr-only" role="status" aria-live="polite"></span>
</x-proposal-writing-toolbar>
