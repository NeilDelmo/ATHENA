<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProposalSignatoryRequest;
use App\Http\Requests\SelectProposalSignatoriesRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProposalSignatoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isUsingWorkspace(['research_head', 'research_secretary']), 403);

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
            'defaultSignatories' => ProposalSignatory::managedDefaultSelections(),
            'editingSignatoryId' => $editingSignatoryId,
            'roles' => $roles,
            'search' => $search,
            'selectedRole' => $selectedRole,
            'signatories' => $signatories,
            'summary' => [
                'active' => $allSignatories->where('active', true)->count(),
                'roles' => $allSignatories->pluck('role_key')->unique()->count(),
                'total' => $allSignatories->count(),
            ],
        ]);
    }

    public function store(SaveProposalSignatoryRequest $request): RedirectResponse
    {
        $this->saveDirectoryEntry($request->validated());

        return back()->with('success', 'Signatory saved. Default names are applied automatically to editable proposals.');
    }

    public function update(SaveProposalSignatoryRequest $request, ProposalSignatory $signatory): RedirectResponse
    {
        $this->saveDirectoryEntry($request->validated(), $signatory);

        return redirect()
            ->to(route('signatories.index').'#signatory-'.$signatory->getKey())
            ->with('success', 'Signatory updated. Editable proposals use the current defaults; submitted documents stay unchanged.');
    }

    public function destroy(Request $request, ProposalSignatory $signatory): RedirectResponse
    {
        abort_unless($request->user()->isUsingWorkspace(['research_head', 'research_secretary']), 403);

        $signatoryName = $signatory->name;
        DB::transaction(function () use ($signatory): void {
            ProposalSignatory::query()->lockForUpdate()->get();
            $previous = ProposalSignatory::managedDefaultSelections();
            $signatory->delete();
            $this->invalidatePreparedDrafts($previous);
        });

        return redirect()
            ->route('signatories.index')
            ->with('success', "{$signatoryName} was removed from the directory. Existing proposal signature blocks remain unchanged.");
    }

    public function edit(Request $request, ProposalDraft $proposalDraft): View
    {
        abort(403, 'Signatory defaults are managed by the Research Head or Research Office Secretary.');
    }

    public function select(SelectProposalSignatoriesRequest $request, ProposalDraft $proposalDraft): RedirectResponse
    {
        abort(403, 'Signatory defaults are managed by the Research Head or Research Office Secretary.');
    }

    /** @param array<string, mixed> $attributes */
    private function saveDirectoryEntry(array $attributes, ?ProposalSignatory $signatory = null): void
    {
        DB::transaction(function () use ($attributes, $signatory): void {
            ProposalSignatory::query()->lockForUpdate()->get();
            $previous = ProposalSignatory::managedDefaultSelections();
            $attributes['is_default'] = (bool) ($attributes['is_default'] ?? $signatory?->is_default ?? false)
                && (bool) $attributes['active'];

            if ($attributes['is_default']) {
                ProposalSignatory::query()->where('role_key', $attributes['role_key'])->update(['is_default' => false]);
                $signatory?->refresh();
            }

            $signatory ? $signatory->update($attributes) : ProposalSignatory::create($attributes);
            $this->invalidatePreparedDrafts($previous);
        });
    }

    /** @param array<string, array{id: int|null, name: string, position: string}> $previous */
    private function invalidatePreparedDrafts(array $previous): void
    {
        $current = ProposalSignatory::managedDefaultSelections();
        $papers = collect(ProposalSignatory::FIELDS)->filter(
            fn (array $fields): bool => array_intersect_key($previous, $fields) !== array_intersect_key($current, $fields),
        )->keys()->all();

        if ($papers === []) {
            return;
        }

        ProposalDraft::query()->where('status', ProposalDraft::STATUS_DRAFT)->orderBy('id')->lockForUpdate()->get()
            ->filter(fn (ProposalDraft $draft): bool => $draft->isEditable())
            ->each(function (ProposalDraft $draft) use ($papers): void {
                $draft->documents()->whereIn('document_type', $papers)->update([
                    'file_path' => null, 'original_filename' => null, 'mime_type' => null,
                    'file_size' => null, 'checksum' => null,
                    'lock_version' => DB::raw('lock_version + 1'),
                ]);
                $draft->update(['lock_version' => $draft->lock_version + 1]);
            });
    }
}
