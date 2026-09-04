<?php

namespace App\Livewire;

use App\Actions\SubmitProposalDraft;
use App\Models\ProposalDraft;
use App\Models\User;
use App\Support\ProposalBudgetConsistency;
use App\Support\ProposalDraftReadiness;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

class ProposalDraftReviewPackage extends Component
{
    public ProposalDraft $proposalDraft;

    public bool $inModal = false;

    public string $statusMessage = '';

    public function mount(ProposalDraft $proposalDraft, bool $inModal = false): void
    {
        $this->proposalDraft = $proposalDraft;
        $this->inModal = $inModal;
    }

    public function prepare(SubmitProposalDraft $submitProposalDraft): void
    {
        Gate::authorize('submit', $this->proposalDraft);
        $this->resetErrorBag();
        $this->statusMessage = '';

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        try {
            $submitProposalDraft->prepare($this->proposalDraft, $user);
        } catch (ValidationException $exception) {
            $this->addValidationErrors($exception);

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError(
                'preparation',
                'The submission PDFs could not be prepared. Your proposal data was kept, so you can try again.',
            );

            return;
        }

        $this->proposalDraft->refresh();
        $this->statusMessage = 'Seven PDF attachments prepared. Review or replace them before turning in.';
    }

    public function turnIn(SubmitProposalDraft $submitProposalDraft): void
    {
        Gate::authorize('submit', $this->proposalDraft);
        $this->resetErrorBag();
        $this->statusMessage = '';

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        try {
            $submitProposalDraft->handle($this->proposalDraft, $user);
        } catch (ValidationException $exception) {
            $this->addValidationErrors($exception);

            return;
        } catch (ModelNotFoundException) {
            session()->flash('warning', 'This proposal draft has already been submitted or deleted.');
            $this->redirectRoute('faculty.proposal-drafts.index', navigate: true);

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError(
                'submission',
                'The proposal package could not be finalized. Your draft and staged papers were kept so you can try again.',
            );

            return;
        }

        session()->flash('success', 'Proposal turned in successfully as a seven-PDF package and sent to the Research Head.');
        $this->redirectRoute('faculty.dashboard', navigate: true);
    }

    public function render(
        ProposalDraftReadiness $readiness,
        ProposalBudgetConsistency $proposalBudgetConsistency,
    ): View {
        $this->proposalDraft->load([
            'researchCall',
            'documents',
            'owner:id,name,email',
            'members.user:id,name,email',
        ]);
        $checklist = $readiness->checklist($this->proposalDraft);
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($this->proposalDraft);
        $readinessErrors = $readiness->errors($this->proposalDraft);
        $submissionFilesPrepared = $readiness->submissionFilesArePrepared($this->proposalDraft);
        $readyToPrepare = $readinessErrors === [];
        $readyToSubmit = $readyToPrepare && $submissionFilesPrepared;
        $budgetConsistency = $proposalBudgetConsistency->compare($this->proposalDraft);

        return view('livewire.proposal-draft-review-package', compact(
            'checklist',
            'projectDetailsComplete',
            'readinessErrors',
            'readyToPrepare',
            'submissionFilesPrepared',
            'readyToSubmit',
            'budgetConsistency',
        ));
    }

    private function addValidationErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError($key, $message);
            }
        }
    }
}
