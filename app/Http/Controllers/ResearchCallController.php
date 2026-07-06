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
            'activeCalls' => $calls->filter(fn (ResearchCall $call) => $call->lifecycleStatus() === 'open'),
            'upcomingCalls' => $calls->filter(fn (ResearchCall $call) => in_array($call->lifecycleStatus(), ['draft', 'scheduled'], true)),
            'previousCalls' => $calls->filter(fn (ResearchCall $call) => in_array($call->lifecycleStatus(), ['closed', 'ended'], true)),
            'institutionalBudgetCeiling' => ResearchCall::MAXIMUM_BUDGET,
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
            'max_active_research_per_faculty' => ['required', 'integer', 'min:1', 'max:20'],
            'maximum_budget' => ['required', 'numeric', 'min:0', 'max:'.ResearchCall::MAXIMUM_BUDGET],
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

        if ($validated['status'] === 'open' && $researchCall->closes_at->isPast()) {
            return back()->withErrors([
                'status' => 'This call cannot be reopened because its submission end date has passed.',
            ]);
        }

        $researchCall->update(['status' => $validated['status']]);

        $message = match ($validated['status']) {
            'open' => 'Research call published. It will accept submissions only during its configured date range.',
            'closed' => 'Research call closed. New proposal submissions are no longer accepted.',
            default => 'Research call moved to draft.',
        };

        return back()->with('success', $message);
    }
}
