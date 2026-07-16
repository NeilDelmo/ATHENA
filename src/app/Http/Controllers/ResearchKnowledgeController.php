<?php

namespace App\Http\Controllers;

use App\Models\ResearchKnowledgeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResearchKnowledgeController extends Controller
{
    public function index(): View
    {
        $entries = ResearchKnowledgeEntry::query()
            ->with('creator')
            ->latest('updated_at')
            ->get();

        return view('research_head.assistant_knowledge.index', [
            'entries' => $entries,
            'categoryOptions' => ResearchKnowledgeEntry::categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ResearchKnowledgeEntry::create([
            ...$this->validateEntry($request),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Knowledge entry added to Athena.');
    }

    public function update(Request $request, ResearchKnowledgeEntry $researchKnowledgeEntry): RedirectResponse
    {
        $researchKnowledgeEntry->update($this->validateEntry($request));

        return back()->with('success', 'Knowledge entry updated.');
    }

    public function updateStatus(Request $request, ResearchKnowledgeEntry $researchKnowledgeEntry): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $researchKnowledgeEntry->update($validated);

        return back()->with('success', $researchKnowledgeEntry->is_active
            ? 'Knowledge entry restored.'
            : 'Knowledge entry archived.');
    }

    /** @return array<string, mixed> */
    private function validateEntry(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(ResearchKnowledgeEntry::categoryOptions()))],
            'content' => ['required', 'string', 'min:20', 'max:20000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
    }
}
