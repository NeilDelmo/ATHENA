<?php

namespace App\Http\Controllers;

use App\Exceptions\ResearchCallImageExtractionException;
use App\Http\Requests\ExtractResearchCallImageRequest;
use App\Http\Requests\StoreResearchCallRequest;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Services\ResearchCallImageParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResearchCallController extends Controller
{
    public function __construct(private ResearchCallImageParser $imageParser) {}

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

    public function store(StoreResearchCallRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $imagePath = $request->file('reference_image')?->store('research-calls', 'local');

        $call = ResearchCall::create([
            ...collect($validated)->except(['categories', 'reference_image'])->all(),
            'reference_image_path' => $imagePath,
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

    public function extractImage(ExtractResearchCallImageRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'fields' => $this->imageParser->extract($request->file('reference_image')),
            ]);
        } catch (ResearchCallImageExtractionException $exception) {
            Log::warning('Research call image extraction failed.', [
                'exception' => $exception::class,
                'message' => $exception->getPrevious()?->getMessage() ?? $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    public function sourceImage(ResearchCall $researchCall): StreamedResponse
    {
        abort_unless($researchCall->reference_image_path, 404);
        abort_unless(Storage::disk('local')->exists($researchCall->reference_image_path), 404);

        return Storage::disk('local')->response(
            $researchCall->reference_image_path,
            basename($researchCall->reference_image_path),
            [
                'Content-Type' => Storage::disk('local')->mimeType($researchCall->reference_image_path),
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function updateStatus(Request $request, ResearchCall $researchCall): RedirectResponse
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
