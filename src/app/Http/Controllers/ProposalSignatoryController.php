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
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProposalSignatoryController extends Controller
{
    private const PAPER_EDIT_ROUTES = [
        'detailed_proposal' => 'faculty.proposal-drafts.detailed-proposal.edit',
        'work_plan' => 'faculty.proposal-drafts.work-plan.edit',
        'line_item_budget' => 'faculty.proposal-drafts.line-item-budget.edit',
        'gad_checklist' => 'faculty.proposal-drafts.gad-checklist.show',
        'initial_screening_form' => 'faculty.proposal-drafts.initial-screening-form.show',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->isUsingWorkspace('research_head'), 403);

        $roles = ProposalSignatory::roles();
        $selectedRole = $request->string('role')->toString();

        if (! array_key_exists($selectedRole, $roles)) {
            $selectedRole = '';
        }

        $search = Str::of($request->string('search')->toString())
            ->squish()
            ->limit(100)
            ->toString();
        $normalizedSearch = Str::lower($search);
        $allSignatories = ProposalSignatory::query()
            ->orderBy('role_key')
            ->orderBy('name')
            ->get();
        $signatories = $allSignatories
            ->when($selectedRole !== '', fn ($items) => $items->where('role_key', $selectedRole))
            ->when($search !== '', fn ($items) => $items->filter(
                fn (ProposalSignatory $signatory): bool => Str::contains(
                    Str::lower($signatory->name.' '.$signatory->position),
                    $normalizedSearch,
                ),
            ));
        $editingSignatoryId = $signatories
            ->firstWhere('id', $request->integer('edit'))
            ?->getKey();

        return view('research_head.signatories', [
            'editingSignatoryId' => $editingSignatoryId,
            'roles' => $roles,
            'search' => $search,
            'selectedRole' => $selectedRole,
            'signatoryGroups' => $signatories->groupBy('role_key'),
            'summary' => [
                'active' => $allSignatories->where('active', true)->count(),
                'roles' => $allSignatories->pluck('role_key')->unique()->count(),
                'total' => $allSignatories->count(),
            ],
        ]);
    }

    public function store(SaveProposalSignatoryRequest $request): RedirectResponse
    {
        ProposalSignatory::create($request->validated());

        return back()->with('success', 'Signatory added. Faculty can now select this name.');
    }

    public function update(SaveProposalSignatoryRequest $request, ProposalSignatory $signatory): RedirectResponse
    {
        $signatory->update($request->validated());

        return redirect()
            ->to(route('signatories.index').'#signatory-'.$signatory->getKey())
            ->with('success', 'Directory updated. Previously selected names remain unchanged.');
    }

    public function destroy(Request $request, ProposalSignatory $signatory): RedirectResponse
    {
        abort_unless($request->user()->isUsingWorkspace('research_head'), 403);

        $signatoryName = $signatory->name;
        $signatory->delete();

        return redirect()
            ->route('signatories.index')
            ->with('success', "{$signatoryName} was removed from the directory. Existing proposal signature blocks remain unchanged.");
    }

    public function edit(Request $request, ProposalDraft $proposalDraft): View
    {
        Gate::authorize('update', $proposalDraft);

        $returnPaper = $request->string('paper')->toString();

        if (! array_key_exists($returnPaper, self::PAPER_EDIT_ROUTES)) {
            $returnPaper = '';
        }

        return view('faculty.proposal-drafts.signatories', [
            'proposalDraft' => $proposalDraft,
            'groups' => ProposalSignatory::FIELDS,
            'options' => ProposalSignatory::where('active', true)->orderBy('name')->get()->groupBy('role_key'),
            'returnPaper' => $returnPaper,
            'returnUrl' => route(self::PAPER_EDIT_ROUTES[$returnPaper] ?? 'faculty.proposal-drafts.show', $proposalDraft),
        ]);
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
                $person = ProposalSignatory::whereKey($id)->where('role_key', $key)->where('active', true)->firstOrFail();
                $selected[$key] = ['id' => $person->id, 'name' => $person->name, 'position' => $person->position];
            }
            if ($selected !== ($draft->signatory_selections ?? [])) {
                $draft->documents()->whereIn('document_type', array_keys(ProposalSignatory::FIELDS))
                    ->update(['file_path' => null, 'lock_version' => DB::raw('lock_version + 1')]);
                $draft->update(['signatory_selections' => $selected, 'lock_version' => $draft->lock_version + 1]);
            }
        });

        $returnRoute = self::PAPER_EDIT_ROUTES[$data['return_paper'] ?? ''] ?? 'faculty.proposal-drafts.show';

        return redirect()
            ->route($returnRoute, $proposalDraft)
            ->with('success', 'Signatories saved. Preview your papers and prepare the PDFs again before submitting.');
    }
}
