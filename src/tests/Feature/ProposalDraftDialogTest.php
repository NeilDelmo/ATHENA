<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

test('every logout form uses the shared SweetAlert confirmation', function () {
    foreach ([
        'resources/views/layouts/app.blade.php',
        'resources/views/auth/verify-email.blade.php',
        'resources/views/auth/select-workspace.blade.php',
        'resources/views/auth/select-role.blade.php',
        'resources/views/profile/edit.blade.php',
    ] as $viewPath) {
        expect(file_get_contents(base_path($viewPath)))
            ->toContain('data-proposal-confirm')
            ->toContain('data-confirm-title="Log out of ATHENA?"')
            ->toContain('data-confirm-button="Log out"')
            ->toContain('data-cancel-button="Stay signed in"')
            ->not->toContain("onsubmit=\"return confirm('Are you sure you want to log out?')\"");
    }
});
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
        ->toContain("title: payload.workload_warning ? 'Invitation accepted with workload warning' : 'You are now a collaborator'")
        ->toContain('text: payload.workload_warning || `You can now access the current draft')
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
        ->toContain('const navigationLink = autoSaveNavigationLink(event)')
        ->toContain('backgroundAutoSaveHasPendingChanges()')
        ->toContain('const shouldFinishAutoSave = action.matches')
        ->toContain("window.addEventListener('beforeunload'")
        ->toContain('data-detailed-proposal-invalid')
        ->toContain("title: 'Changes were not saved'")
        ->toContain("confirmButtonText: 'Leave without saving'")
        ->toContain("if (submitterSelector === '[data-paper-save]')")
        ->not->toContain('validateForm({ forExit: true })')
        ->not->toContain("editor?.dataset.detailedProposalAutosave === 'true'")
        ->not->toContain('paperEditorHasUnsavedChanges(editor) || window.confirm');
});

test('turning in a proposal shows a blocking progress screen after confirmation', function () {
    $reviewPackage = file_get_contents(resource_path('views/faculty/proposal-drafts/_review-package.blade.php'));
    $appJavaScript = file_get_contents(resource_path('js/app.js'));
    $modal = file_get_contents(resource_path('views/components/modal.blade.php'));
    $loadingScreen = Blade::render('<x-proposal-submission-loading-screen />');

    expect($reviewPackage)
        ->toContain('data-proposal-package-submit')
        ->toContain('<x-proposal-submission-loading-screen livewire-target="turnIn" />')
        ->and($loadingScreen)
        ->toContain('data-proposal-submission-loading')
        ->toContain('hidden')
        ->toContain('role="status"')
        ->toContain('Turning in prepared package')
        ->toContain('sending the seven PDFs you reviewed')
        ->toContain('Please keep this page open until the submission is confirmed.')
        ->toContain('items-center justify-center overflow-y-auto')
        ->toContain('min-h-full w-full items-center justify-center')
        ->and($modal)
        ->toContain('shadow-xl transition-all')
        ->not->toContain('shadow-xl transform transition-all')
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
        ->toContain('<x-proposal-pdf-preparation-loading-screen livewire-target="prepare" />')
        ->and($loadingScreen)
        ->toContain('data-proposal-pdf-preparation-loading')
        ->toContain('hidden')
        ->toContain('role="status"')
        ->toContain('Generating submission PDFs')
        ->toContain('preparing the seven final PDFs')
        ->toContain('Please keep this page open')
        ->toContain('items-center justify-center overflow-y-auto')
        ->toContain('min-h-full w-full items-center justify-center')
        ->and($appJavaScript)
        ->toContain('showProposalPdfPreparationLoadingScreen(form)')
        ->toContain("form.dataset.proposalPreparing = 'true'")
        ->toContain('loadingScreen.hidden = false')
        ->toContain("matches('[data-proposal-package-prepare]')")
        ->toContain("if (form.dataset.proposalPreparing === 'true')")
        ->toContain('submitButton.textContent');
});

test('proposal package preparation uses Livewire without closing the review modal', function () {
    $reviewPackage = file_get_contents(resource_path('views/faculty/proposal-drafts/_review-package.blade.php'));
    $workspace = file_get_contents(resource_path('views/faculty/proposal-drafts/show.blade.php'));
    $reviewPage = file_get_contents(resource_path('views/faculty/proposal-drafts/review.blade.php'));
    $appJavaScript = file_get_contents(resource_path('js/app.js'));
    $livewireComponent = file_get_contents(app_path('Livewire/ProposalDraftReviewPackage.php'));

    expect($reviewPackage)
        ->toContain('data-proposal-livewire-action="prepare"')
        ->toContain('data-proposal-livewire-action="turnIn"')
        ->toContain('livewire-target="prepare"')
        ->toContain('livewire-target="turnIn"')
        ->and($workspace)
        ->toContain('<livewire:proposal-draft-review-package')
        ->and($reviewPage)
        ->toContain('<livewire:proposal-draft-review-package')
        ->and($appJavaScript)
        ->toContain('submitProposalPackageWithLivewire(form)')
        ->toContain('Livewire.find(componentId)')
        ->toContain('await component.$call(action)')
        ->and($livewireComponent)
        ->toContain('$this->redirectRoute(\'faculty.dashboard\', navigate: true)');
});

test('generated paper editors support partial drafts and gate download controls', function () {
    foreach ([
        'resources/views/faculty/proposal-drafts/detailed-proposal/edit.blade.php' => [
            'data-detailed-proposal-autosave="true"',
            'data-detailed-proposal-autosave-form',
            'data-detailed-proposal-validation-group="sdgs"',
            'data-detailed-proposal-validation-group="expected-outputs"',
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

test('the detailed proposal exit saves partial changes while document actions still validate required fields', function () {
    $view = file_get_contents(resource_path('views/faculty/proposal-drafts/detailed-proposal/edit.blade.php'));
    $appJavaScript = file_get_contents(resource_path('js/app.js'));
    $appCss = file_get_contents(resource_path('css/app.css'));

    expect($view)
        ->toContain('data-detailed-proposal-validation-group="sdgs"')
        ->toContain('data-detailed-proposal-validation-group="expected-outputs"')
        ->and($appJavaScript)
        ->toContain('highlightDetailedProposalField(field)')
        ->toContain('highlightDetailedProposalValidationGroup')
        ->toContain('this.$el.dataset.paperDirty = \'true\';')
        ->toContain('this.$nextTick(() => this.scheduleDetailedProposalAutoSave());')
        ->not->toContain("editor?.dataset.detailedProposalAutosave === 'true'")
        ->and($appCss)
        ->toContain("[data-detailed-proposal-invalid='true']");
});

test('generated paper exits save partial data without running completion validation', function () {
    $appJavaScript = file_get_contents(resource_path('js/app.js'));

    expect($appJavaScript)
        ->toContain("const shouldFinishAutoSave = action.matches('[data-paper-cancel-exit]')")
        ->toContain('await finishPaperEditorAutoSave(editor)')
        ->toContain('autoSaveHasPendingChanges(state, form, configuration)')
        ->not->toContain('validateForm({ forExit: true })')
        ->not->toContain("editor?.dataset.workPlanAutosave === 'true'")
        ->not->toContain("editor?.dataset.lineItemBudgetAutosave === 'true'")
        ->not->toContain("editor?.dataset.expenseBreakdownAutosave === 'true'")
        ->not->toContain("editor?.dataset.curriculumVitaeAutosave === 'true'");
});

test('background autosave forms share navigation and unload protection', function () {
    foreach ([
        'resources/views/components/monitoring-tool-form.blade.php' => [
            'data-monitoring-tool-autosave="true"',
            'data-monitoring-tool-autosave-form',
        ],
        'resources/views/components/progress-report-form.blade.php' => [
            'data-narrative-progress-autosave="true"',
            'data-narrative-progress-autosave-form',
        ],
        'resources/views/topics/partials/notice-to-proceed.blade.php' => [
            'data-notice-to-proceed-autosave="true"',
            'data-notice-to-proceed-autosave-form',
        ],
    ] as $viewPath => [$rootMarker, $formMarker]) {
        expect(file_get_contents(base_path($viewPath)))
            ->toContain($rootMarker)
            ->toContain($formMarker);
    }

    $appJavaScript = file_get_contents(resource_path('js/app.js'));

    expect($appJavaScript)
        ->toContain('function finishBackgroundAutoSaves()')
        ->toContain('backgroundAutoSaveHasPendingChanges()')
        ->toContain('await finishBackgroundAutoSaves()')
        ->toContain("document.addEventListener('livewire:navigate'")
        ->toContain('const submitsCurrentBackgroundForm = form.matches')
        ->toContain("window.addEventListener('beforeunload'")
        ->toContain('suppressBackgroundAutoSaveWarnings()');
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
        ->toContain('revisionUploadUrl')
        ->toContain("'X-Revision-PDF': isEmbeddedRevisionEditor() ? '1' : '0'")
        ->toContain('detailed-research-proposal.pdf')
        ->toContain('attachment-a-work-plan.pdf')
        ->toContain('attachment-c-curriculum-vitae.pdf');
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

test('opening notifications marks them read without requiring a completed review', function () {
    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)
        ->toContain('await this.markNotificationRead(item);')
        ->not->toContain('this.requiresCompletedReview(item)')
        ->toContain('if (!response.ok || payload.read === false) return false;')
        ->toContain('const preservedIds = new Set(payload.preserved_ids || []);')
        ->toContain('this.unreadCount = payload.unread_count ?? 0;');
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

test('proposal review screens consistently inherit the application font', function () {
    foreach ([
        'resources/views/components/proposal-workflow.blade.php',
        'resources/views/components/proposal-revision-form.blade.php',
        'resources/views/components/research-head-file-workspace.blade.php',
        'resources/views/topics/show.blade.php',
    ] as $viewPath) {
        expect(file_get_contents(base_path($viewPath)))
            ->not->toContain('font-serif')
            ->not->toContain('font-mono');
    }
});
