@foreach (['open' => 'folder-open', 'preview' => 'preview', 'download' => 'download', 'cut' => 'cut', 'copy' => 'copy', 'paste' => 'paste', 'move' => 'move', 'rename' => 'rename', 'remove' => 'remove', 'remove-shortcut' => 'remove', 'new' => 'new', 'refresh' => 'refresh', 'undo' => 'undo'] as $actionId => $iconName)
    <x-report-library-icon :name="$iconName" class="!h-5 !w-5" x-show="(action.icon || action.id) === '{{ $actionId }}'" />
@endforeach
