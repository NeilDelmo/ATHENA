<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

test('proposal draft action forms use SweetAlert2 confirmations', function () {
    $viewPaths = [
        'resources/views/faculty/proposal-drafts/index.blade.php' => 'Delete draft',
        'resources/views/faculty/proposal-drafts/show.blade.php' => 'Remove collaborator',
        'resources/views/faculty/proposal-drafts/review.blade.php' => 'Turn in proposal',
        'resources/views/faculty/proposal-drafts/history.blade.php' => 'Restore version',
        'resources/views/faculty/proposal-drafts/papers/edit.blade.php' => 'Remove file',
    ];

    foreach ($viewPaths as $viewPath => $confirmButton) {
        $view = file_get_contents(base_path($viewPath));

        expect($view)
            ->toContain('data-proposal-confirm')
            ->toContain('data-confirm-title=')
            ->toContain('data-confirm-text=')
            ->toContain('data-confirm-button="'.$confirmButton.'"')
            ->not->toContain('onsubmit="return confirm(');
    }
});

test('proposal draft dialogs are provided by the installed SweetAlert2 client', function () {
    $appJavaScript = file_get_contents(resource_path('js/app.js'));
    $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($package['dependencies']['sweetalert2'] ?? null)->not->toBeNull()
        ->and($appJavaScript)
        ->toContain("import Swal from 'sweetalert2';")
        ->toContain('Swal.fire({')
        ->toContain("form?.matches('[data-proposal-confirm]')")
        ->toContain("document.querySelectorAll('[data-proposal-alert]')")
        ->toContain('html: alert.innerHTML.trim()')
        ->toContain("title: 'Discard unsaved changes?'")
        ->toContain("action.closest('[data-paper-editor]') ?? currentPaperEditor()")
        ->toContain("if (submitterSelector === '[data-paper-save]')")
        ->not->toContain('paperEditorHasUnsavedChanges(editor) || window.confirm');
});

test('proposal flash feedback is marked for SweetAlert2 across the workspace', function () {
    foreach (File::allFiles(resource_path('views/faculty/proposal-drafts')) as $viewFile) {
        $view = preg_replace('/\s+/', ' ', $viewFile->getContents());

        if (str_contains($view, "session('success')")) {
            expect($view)->toContain("@if (session('success')) <x-proposal-alert");
        }

        if (str_contains($view, "session('warning')")) {
            expect($view)->toContain("@if (session('warning')) <x-proposal-alert type=\"warning\"");
        }

        if (str_contains($view, '$errors->any()')) {
            expect($view)->toContain('@if ($errors->any()) <x-proposal-alert type="error"');
        }
    }
});

test('the proposal alert component keeps accessible fallback markup', function (string $type, string $icon, string $role) {
    $html = Blade::render(
        '<x-proposal-alert :type="$type">Proposal draft created. Complete the shared project details next.</x-proposal-alert>',
        compact('type'),
    );

    expect($html)
        ->toContain('data-proposal-alert')
        ->toContain('data-alert-icon="'.$icon.'"')
        ->toContain('role="'.$role.'"')
        ->toContain('Proposal draft created. Complete the shared project details next.');
})->with([
    'success' => ['success', 'success', 'status'],
    'warning' => ['warning', 'warning', 'status'],
    'error' => ['error', 'error', 'alert'],
]);

test('proposal editors use a protected header exit and one visible save and exit action', function () {
    $editorViews = [
        'resources/views/faculty/proposal-drafts/details/edit.blade.php',
        'resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php',
        'resources/views/faculty/proposal-drafts/work-plan/edit.blade.php',
        'resources/views/faculty/proposal-drafts/line-item-budget/edit.blade.php',
        'resources/views/faculty/proposal-drafts/expense-breakdown/edit.blade.php',
        'resources/views/faculty/proposal-drafts/curriculum-vitae/edit.blade.php',
    ];

    foreach ($editorViews as $editorView) {
        $view = file_get_contents(base_path($editorView));
        $headerEndPosition = strpos($view, '</x-slot>');
        $exitPosition = strpos($view, 'data-paper-cancel-exit');

        expect($headerEndPosition)->toBeInt()
            ->and($exitPosition)->toBeInt()
            ->and($exitPosition)->toBeLessThan($headerEndPosition)
            ->and(substr_count($view, 'data-paper-save-exit'))->toBe(1)
            ->and($view)
            ->toContain('Exit editor')
            ->toContain('Save and exit')
            ->toContain('data-paper-save-exit type="submit"')
            ->not->toContain('<button data-paper-save type="submit"')
            ->not->toContain('data-paper-discard')
            ->not->toContain('Cancel and exit');
    }
});

test('the collaboration monitor keeps a persistent save confirmation', function () {
    session()->flash('success', 'Attachment A: Work Plan saved.');

    $html = Blade::render(
        '<x-proposal-collaboration-monitor :loaded-version="3" state-url="/state" reload-url="/edit" label="Work Plan" />',
    );

    expect($html)
        ->toContain('data-proposal-save-confirmation')
        ->toContain('Attachment A: Work Plan saved.')
        ->toContain('Saved as version 3.')
        ->toContain('You stayed on this page and can continue editing.')
        ->toContain('data-proposal-monitor-status');

    session()->flash('success', 'Attachment A: Work Plan file removed. Previous versions remain available in history.');

    $removedHtml = Blade::render(
        '<x-proposal-collaboration-monitor :loaded-version="0" state-url="/state" reload-url="/edit" label="Work Plan" />',
    );

    expect($removedHtml)->not->toContain('data-proposal-save-confirmation');
});
