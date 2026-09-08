<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProposalSignatoryRequest;
use App\Http\Requests\SelectProposalSignatoriesRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProposalSignatoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isUsingWorkspace('research_head'), 403);

        return view('research_head.signatories', ['signatories' => ProposalSignatory::orderBy('role_key')->orderBy('name')->get(), 'roles' => ProposalSignatory::roles()]);
    }

    public function store(SaveProposalSignatoryRequest $request): RedirectResponse
    {
        ProposalSignatory::create($request->validated());

        return back()->with('success', 'Signatory added. Faculty can now select this name.');
    }

    public function update(SaveProposalSignatoryRequest $request, ProposalSignatory $signatory): RedirectResponse
    {
        $signatory->update($request->validated());

        return back()->with('success', 'Directory updated. Previously selected names remain unchanged.');
    }

    public function edit(ProposalDraft $proposalDraft): View
    {
        Gate::authorize('update', $proposalDraft);

        return view('faculty.proposal-drafts.signatories', ['proposalDraft' => $proposalDraft, 'groups' => ProposalSignatory::FIELDS, 'options' => ProposalSignatory::where('active', true)->orderBy('name')->get()->groupBy('role_key')]);
    }

    public function select(SelectProposalSignatoriesRequest $request, ProposalDraft $proposalDraft): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($proposalDraft, $data): void {
            $draft = ProposalDraft::whereKey($proposalDraft->id)->lockForUpdate()->firstOrFail();
            abort_unless($draft->status === 'draft' && $draft->lock_version === (int) $data['lock_version'], 409, 'The proposal changed. Reload before choosing signatories.');
            $selected = $draft->signatory_selections ?? [];
            foreach ($data['signatories'] as $key => $id) {
                if (! $id) {
                    continue;
                }
                if ((int) ($selected[$key]['id'] ?? 0) === (int) $id) {
                    continue;
                }
                $person = ProposalSignatory::whereKey($id)->where('role_key', $key)->where('active', true)->firstOrFail();
                $selected[$key] = ['id' => $person->id, 'name' => $person->name, 'position' => $person->position];
            }
            if ($selected !== ($draft->signatory_selections ?? [])) {
                $draft->documents()->whereIn('document_type', array_keys(ProposalSignatory::FIELDS))
                    ->update(['file_path' => null, 'lock_version' => DB::raw('lock_version + 1')]);
                $draft->update(['signatory_selections' => $selected, 'lock_version' => $draft->lock_version + 1]);
            }
        });

        return back()->with('success', 'Signatories saved. Preview your papers and prepare the PDFs again before submitting.');
    }
}
