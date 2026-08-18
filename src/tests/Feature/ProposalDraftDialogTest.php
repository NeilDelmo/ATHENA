<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

test('proposal draft action forms use SweetAlert2 confirmations', function () {
    $viewPaths = [
        'resources/views/faculty/proposal-drafts/index.blade.php' => 'Delete draft',
        'resources/views/faculty/proposal-drafts/show.blade.php' => 'Remove collaborator',
        'resources/views/faculty/proposal-drafts/_review-package.blade.php' => 'Turn in proposal',
        'resources/views/faculty/proposal-drafts/history.blade.php' => 'Restore recovery point',
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
        ->toContain('acceptProposalInvitation')
        ->toContain("title: 'Join proposal workspace?'")
        ->toContain("confirmButtonText: 'Accept invitation'")
        ->toContain("title: 'You are now a collaborator'")
        ->toContain('text: `You can now access the current draft')
        ->toContain("confirmButtonText: 'Open draft'")
        ->toContain("form?.matches('[data-proposal-confirm]')")
        ->toContain("document.querySelectorAll('[data-proposal-alert]')")
        ->toContain('html: alert.innerHTML.trim()')
        ->toContain("title: 'Discard unsaved changes?'")
        ->toContain("title: complete ? 'Save completed attachment?' : 'Save attachment as draft?'")
        ->toContain("confirmButtonText: complete ? 'Save and exit' : 'Save draft'")
        ->toContain('data-paper-save-mode')
        ->toContain("action.closest('[data-paper-editor]') ?? currentPaperEditor()")
        ->toContain('function finishPaperEditorAutoSave(editor)')
        ->toContain("if (action.matches('[data-paper-cancel-exit]') && autoSaveMethodForPaperEditor(editor))")
        ->toContain("title: 'Changes were not saved'")
        ->toContain("confirmButtonText: 'Leave without saving'")
        ->toContain("if (submitterSelector === '[data-paper-save]')")
        ->not->toContain('paperEditorHasUnsavedChanges(editor) || window.confirm');
});

test('turning in a proposal shows a blocking progress screen after confirmation', function () {
    $reviewPackage = file_get_contents(resource_path('views/faculty/proposal-drafts/_review-package.blade.php'));
    $appJavaScript = file_get_contents(resource_path('js/app.js'));
    $loadingScreen = Blade::render('<x-proposal-submission-loading-screen />');

    expect($reviewPackage)
        ->toContain('data-proposal-package-submit')
        ->toContain('<x-proposal-submission-loading-screen />')
        ->and($loadingScreen)
        ->toContain('data-proposal-submission-loading')
        ->toContain('hidden')
        ->toContain('role="status"')
        ->toContain('Turning in prepared package')
        ->toContain('sending the seven PDFs you reviewed')
        ->toContain('Please keep this page open until the submission is confirmed.')
        ->and($appJavaScript)
        ->toContain('showProposalSubmissionLoadingScreen(form)')
        ->toContain("form.dataset.proposalSubmitting = 'true'")
        ->toContain("form.setAttribute('aria-busy', 'true')")
        ->toContain('loadingScreen.hidden = false')
        ->toContain("if (form.dataset.proposalSubmitting === 'true')")
        ->toContain("form.matches('[data-proposal-package-submit]')")
        ->toContain('window.requestAnimationFrame(() =>')
        ->toContain('HTMLFormElement.prototype.submit.call(form)');
});

test('preparing submission PDFs shows a blocking progress screen', function () {
    $reviewPackage = file_get_contents(resource_path('views/faculty/proposal-drafts/_review-package.blade.php'));
    $appJavaScript = file_get_contents(resource_path('js/app.js'));
    $loadingScreen = Blade::render('<x-proposal-pdf-preparation-loading-screen />');

    expect($reviewPackage)
        ->toContain('data-proposal-package-prepare')
        ->toContain('<x-proposal-pdf-preparation-loading-screen />')
        ->and($loadingScreen)
        ->toContain('data-proposal-pdf-preparation-loading')
        ->toContain('hidden')
        ->toContain('role="status"')
        ->toContain('Generating submission PDFs')
        ->toContain('preparing the seven final PDFs')
        ->toContain('Please keep this page open')
        ->and($appJavaScript)
        ->toContain('showProposalPdfPreparationLoadingScreen(form)')
        ->toContain("form.dataset.proposalPreparing = 'true'")
        ->toContain('loadingScreen.hidden = false')
        ->toContain("matches('[data-proposal-package-prepare]')")
        ->toContain("if (form.dataset.proposalPreparing === 'true')")
        ->toContain('submitButton.textContent');
});

test('generated paper editors support partial drafts and gate download controls', function () {
    foreach ([
        'resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php' => [
            'data-detailed-proposal-autosave="true"',
            'data-detailed-proposal-autosave-form',
        ],
        'resources/views/faculty/proposal-drafts/work-plan/edit.blade.php' => [
            'data-work-plan-autosave="true"',
            'data-work-plan-autosave-form',
        ],
        'resources/views/faculty/proposal-drafts/line-item-budget/edit.blade.php' => [
            'data-line-item-budget-autosave="true"',
            'data-line-item-budget-autosave-form',
        ],
        'resources/views/faculty/proposal-drafts/expense-breakdown/edit.blade.php' => [
            'data-expense-breakdown-autosave="true"',
            'data-expense-breakdown-autosave-form',
        ],
        'resources/views/faculty/proposal-drafts/curriculum-vitae/edit.blade.php' => [
            'data-curriculum-vitae-autosave="true"',
            'data-curriculum-vitae-autosave-form',
        ],
    ] as $editorView => [$autoSaveMarker, $autoSaveFormMarker]) {
        $view = file_get_contents(base_path($editorView));

        expect($view)
            ->toContain('data-paper-draft-save="true"')
            ->toContain('data-paper-save-mode')
            ->toContain($autoSaveMarker)
            ->toContain($autoSaveFormMarker)
            ->toContain('novalidate')
            ->toContain('x-bind:disabled="previewLoading"')
            ->toContain(in_array($editorView, [
                'resources/views/faculty/proposal-drafts/line-item-budget/edit.blade.php',
                'resources/views/faculty/proposal-drafts/expense-breakdown/edit.blade.php',
            ], true)
                ? 'x-bind:disabled="!isComplete() || isOverBudget()"'
                : 'x-bind:disabled="!isComplete()"')
            ->toContain('<x-proposal-autosave-status />')
            ->not->toContain('data-paper-save-exit');
    }
});

test('revision-linked generated paper downloads can be staged in the matching revision attachment', function () {
    $editorViews = [
        'resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php',
        'resources/views/faculty/proposal-drafts/work-plan/edit.blade.php',
        'resources/views/faculty/proposal-drafts/line-item-budget/edit.blade.php',
        'resources/views/faculty/proposal-drafts/expense-breakdown/edit.blade.php',
        'resources/views/faculty/proposal-drafts/curriculum-vitae/edit.blade.php',
    ];
    $appJavaScript = file_get_contents(resource_path('js/app.js'));

    foreach ($editorViews as $editorView) {
        expect(file_get_contents(base_path($editorView)))
            ->toContain('revisionUploadUrl')
            ->toContain('revisionDocumentType')
            ->toContain('revisionAttachmentLabel');
    }

    expect($appJavaScript)
        ->toContain('offerRevisionUpload')
        ->toContain('Automatically upload this file to the revision?')
        ->toContain('Revision workspace')
        ->toContain('revisionUploadUrl');
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

test('proposal editors use a protected header exit and save actions that match their editor type', function () {
    foreach ([
        'resources/views/faculty/proposal-drafts/details/edit.blade.php' => 'data-project-details-autosave="true"',
        'resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php' => 'data-detailed-proposal-autosave="true"',
        'resources/views/faculty/proposal-drafts/work-plan/edit.blade.php' => 'data-work-plan-autosave="true"',
        'resources/views/faculty/proposal-drafts/line-item-budget/edit.blade.php' => 'data-line-item-budget-autosave="true"',
        'resources/views/faculty/proposal-drafts/expense-breakdown/edit.blade.php' => 'data-expense-breakdown-autosave="true"',
        'resources/views/faculty/proposal-drafts/curriculum-vitae/edit.blade.php' => 'data-curriculum-vitae-autosave="true"',
    ] as $editorView => $autoSaveMarker) {
        $view = file_get_contents(base_path($editorView));
        $headerEndPosition = strpos($view, '</x-slot>');
        $exitPosition = strpos($view, 'data-paper-cancel-exit');

        expect($headerEndPosition)->toBeInt()
            ->and($exitPosition)->toBeInt()
            ->and($exitPosition)->toBeLessThan($headerEndPosition)
            ->and($view)
            ->toContain('Exit editor')
            ->toContain($autoSaveMarker)
            ->toContain('<x-proposal-autosave-status />')
            ->not->toContain('data-paper-save-exit')
            ->not->toContain('data-paper-discard')
            ->not->toContain('Cancel and exit');
    }
});

test('repeatable paper editors collapse earlier entries and focus the newly added row', function () {
    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)
        ->toContain('function focusNewFormEntry(form, selector)')
        ->toContain('block: \'nearest\'')
        ->toContain('field.focus({ preventScroll: true })')
        ->toContain('expandedEntryId: null')
        ->toContain('expandedItemId: null')
        ->toContain('this.expandedEntryId = entry.id')
        ->toContain('this.expandedItemId = item.id')
        ->toContain('expandEntryForField(invalidField)')
        ->toContain('expandItemForField(invalidField)')
        ->toContain('this.$el.dataset.paperDirty = \'true\';')
        ->toContain('void this.saveExpenseBreakdownNow();')
        ->toContain('expenseBreakdownFormData()')
        ->toContain('itemFieldNames.forEach((name) => formData.delete(name));')
        ->toContain('body: this.expenseBreakdownFormData()')
        ->toContain('[data-work-plan-objective-input="${entry.id}"]')
        ->toContain('[data-expense-item-primary="${item.id}"]')
        ->toContain('[data-line-item-budget-custom-input="${item.id}"]');
});

test('the collaboration monitor keeps a persistent save confirmation', function () {
    session()->flash('success', 'Attachment A: Work Plan saved.');

    $html = Blade::render(
        '<x-proposal-collaboration-monitor :loaded-version="3" state-url="/state" reload-url="/edit" label="Work Plan" />',
    );

    expect($html)
        ->toContain('data-proposal-save-confirmation')
        ->toContain('Attachment A: Work Plan saved.')
        ->toContain('Saved just now.')
        ->toContain('You can continue editing.')
        ->toContain('data-proposal-monitor-status');

    session()->flash('success', 'Attachment A: Work Plan file removed. Earlier recovery points remain available.');

    $removedHtml = Blade::render(
        '<x-proposal-collaboration-monitor :loaded-version="0" state-url="/state" reload-url="/edit" label="Work Plan" />',
    );

    expect($removedHtml)->not->toContain('data-proposal-save-confirmation');
});
