<?php

namespace App\Http\Controllers;

use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResearchCallController extends Controller
{
    public function index(Request $request)
    {
        $calls = ResearchCall::with(['categories', 'creator'])
            ->withCount('topics')
            ->orderByDesc('opens_at')
            ->get();

        return view('research_calls.index', [
            'activeCalls' => $calls->where('status', 'open'),
            'upcomingCalls' => $calls->where('status', 'draft'),
            'previousCalls' => $calls->where('status', 'closed'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:30'],
            'term' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'max_proposals_per_faculty' => ['required', 'integer', 'min:1', 'max:20'],
            'maximum_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'categories' => ['required', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'open'])],
        ]);

        $call = ResearchCall::create([
            ...collect($validated)->except('categories')->all(),
            'created_by' => $request->user()->id,
        ]);

        $categoryIds = collect(explode(',', $validated['categories']))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => strtolower($name))
            ->map(fn (string $name) => ResearchCategory::firstOrCreate(['name' => $name])->id);

        $call->categories()->sync($categoryIds);

        return redirect()->route('research-calls.index')->with('success', 'Research call created successfully.');
    }

    public function updateStatus(Request $request, ResearchCall $researchCall)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'open', 'closed'])],
        ]);

        $researchCall->update(['status' => $validated['status']]);

        return back()->with('success', 'Research call status updated.');
    }
}
