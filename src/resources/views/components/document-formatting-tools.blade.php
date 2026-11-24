<div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Text formatting" data-document-formatting-toolbar>
    <div class="proposal-writing-buttons">
        @foreach (['bold' => ['Bold', 'bold'], 'italic' => ['Italic', 'italic'], 'underline' => ['Underline', 'underline'], 'insertUnorderedList' => ['Bullets', 'bullets'], 'insertOrderedList' => ['Numbered list', 'numbered-list'], 'undo' => ['Undo', 'undo'], 'redo' => ['Redo', 'redo'], 'removeFormat' => ['Clear formatting', 'removeFormat']] as $command => [$label, $icon])
            <button type="button" data-document-writing-command="{{ $command }}" @mousedown.prevent @click="formatWriting('{{ $command }}')" :disabled="!activeWritingField" aria-label="{{ $label }}" title="{{ $label }}" class="inline-flex items-center gap-1.5"><x-proposal-writing-icon :name="$icon" /><span>{{ $label }}</span></button>
        @endforeach
    </div>
</div>
