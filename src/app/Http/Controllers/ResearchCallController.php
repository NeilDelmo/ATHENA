<?php

namespace App\Http\Controllers;

use App\Exceptions\ResearchCallImageExtractionException;
use App\Http\Requests\ExtractResearchCallImageRequest;
use App\Http\Requests\StoreResearchCallRequest;
use App\Http\Requests\UpdateResearchCallRequest;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\User;
use App\Notifications\ResearchCallPublishedNotification;
use App\Notifications\ResearchCallUpdatedNotification;
use App\Services\ResearchCallImageParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            'maximum_budget' => ResearchCall::MAXIMUM_BUDGET,
            'reference_image_path' => $imagePath,
            'created_by' => $request->user()->id,
        ]);

        $call->categories()->sync($this->categoryIds($validated['categories']));

        if ($call->isAcceptingSubmissions()) {
            $this->notifyFacultyOfPublishedCall($call);
        }

        return redirect()->route('research-calls.index')->with('success', 'Research call created successfully.');
    }

    public function update(UpdateResearchCallRequest $request, ResearchCall $researchCall): RedirectResponse
    {
        $validated = $request->validated();
        $oldImagePath = $researchCall->reference_image_path;
        $newImagePath = $request->file('reference_image')?->store('research-calls', 'local');
        $attributes = collect($validated)->except(['categories', 'reference_image'])->all();
        $attributes['maximum_budget'] = ResearchCall::MAXIMUM_BUDGET;
        $attributes['reference_image_path'] = $newImagePath ?? $oldImagePath;
        $categoryChanges = [];

        $researchCall->fill($attributes);

        try {
            DB::transaction(function () use ($researchCall, $validated, &$categoryChanges): void {
                $researchCall->save();
                $categoryChanges = $researchCall->categories()->sync($this->categoryIds($validated['categories']));
            });
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('local')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('local')->delete($oldImagePath);
        }

        $hasChanges = $researchCall->wasChanged()
            || collect($categoryChanges)->contains(fn (array $changes): bool => $changes !== []);

        if ($hasChanges) {
            Notification::sendNow(
                User::query()->get(),
                new ResearchCallUpdatedNotification(
                    $researchCall->id,
                    $researchCall->title,
                    route('research-calls.index'),
                ),
            );
        }

        return back()->with('success', 'Research call updated successfully.');
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
        $wasAcceptingSubmissions = $researchCall->isAcceptingSubmissions();

        if ($validated['status'] === 'open' && $researchCall->closes_at->isPast()) {
            return back()->withErrors([
                'status' => 'This call cannot be reopened because its submission end date has passed.',
            ]);
        }

        $researchCall->update(['status' => $validated['status']]);

        if (! $wasAcceptingSubmissions && $researchCall->isAcceptingSubmissions()) {
            $this->notifyFacultyOfPublishedCall($researchCall);
        }

        $message = match ($validated['status']) {
            'open' => 'Research call published. It will accept submissions only during its configured date range.',
            'closed' => 'Research call closed. New proposal submissions are no longer accepted.',
            default => 'Research call moved to draft.',
        };

        return back()->with('success', $message);
    }

    private function notifyFacultyOfPublishedCall(ResearchCall $researchCall): void
    {
        Notification::sendNow(
            User::role(['faculty', 'faculty_researcher'])->get(),
            new ResearchCallPublishedNotification(
                $researchCall->id,
                $researchCall->title,
                route('faculty.dashboard'),
            ),
        );
    }

    /** @return list<int> */
    private function categoryIds(string $categories): array
    {
        return collect(explode(',', $categories))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => strtolower($name))
            ->map(fn (string $name): int => ResearchCategory::firstOrCreate(['name' => $name])->id)
            ->values()
            ->all();
    }
}
