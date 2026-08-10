<?php

namespace App\Http\Controllers;

use App\Actions\ProcessResearchCallOpeningNotifications;
use App\Exceptions\ResearchCallImageExtractionException;
use App\Http\Requests\ExtractResearchCallImageRequest;
use App\Http\Requests\StoreResearchCallRequest;
use App\Http\Requests\UpdateResearchCallRequest;
use App\Models\ResearchCall;
use App\Models\User;
use App\Notifications\ResearchCallUpdatedNotification;
use App\Services\ResearchCallImageParser;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ResearchCallController extends Controller
{
    public function __construct(
        private ResearchCallImageParser $imageParser,
        private ProcessResearchCallOpeningNotifications $openingNotifications,
    ) {}

    public function index(Request $request)
    {
        $calls = ResearchCall::with('creator')
            ->withCount('topics')
            ->orderByDesc('opens_at')
            ->when(
                ! $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD),
                fn ($query) => $query->visibleToFaculty(),
            )
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
            ...collect($validated)->except('reference_image')->all(),
            'maximum_budget' => ResearchCall::MAXIMUM_BUDGET,
            'reference_image_path' => $imagePath,
            'created_by' => $request->user()->id,
        ]);

        if ($call->isAcceptingSubmissions()) {
            $this->openingNotifications->process($call, includeReminder: false);
        }

        return redirect()->route('research-calls.index')->with('success', 'Research call created successfully.');
    }

    public function update(UpdateResearchCallRequest $request, ResearchCall $researchCall): RedirectResponse
    {
        $validated = $request->validated();
        $oldImagePath = $researchCall->reference_image_path;
        $newImagePath = $request->file('reference_image')?->store('research-calls', 'local');
        $attributes = collect($validated)->except('reference_image')->all();
        $attributes['maximum_budget'] = ResearchCall::MAXIMUM_BUDGET;
        $attributes['reference_image_path'] = $newImagePath ?? $oldImagePath;

        $researchCall->fill($attributes);

        try {
            if ($researchCall->isDirty('opens_at')) {
                $researchCall->fill([
                    'opening_reminder_sent_at' => null,
                    'faculty_open_notification_sent_at' => null,
                ]);
            }

            $researchCall->save();
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('local')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('local')->delete($oldImagePath);
        }

        $hasChanges = $researchCall->wasChanged();

        if ($researchCall->isAcceptingSubmissions()) {
            $this->openingNotifications->process($researchCall, includeReminder: false);
        }

        if ($hasChanges && $researchCall->status !== 'draft') {
            Notification::sendNow(
                User::role(User::WORKSPACE_FACULTY)->get(),
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
            $extraction = $this->imageParser->extractWithEvidence($request->file('reference_image'));
            $fields = $extraction['fields'];
            $detectedBudget = $fields['maximum_budget'];

            $warnings = [];

            if ($detectedBudget !== null && abs($detectedBudget - ResearchCall::MAXIMUM_BUDGET) > 0.005) {
                $warnings[] = 'The poster states a budget of PHP '.number_format($detectedBudget, 2).'. ATHENA will keep the institutional maximum of PHP '.number_format(ResearchCall::MAXIMUM_BUDGET, 2).'.';
            }

            if ($fields['opens_at'] === null || $fields['closes_at'] === null) {
                $warnings[] = 'The full submission window could not be confidently detected. Review the opening and closing dates before saving.';
            }

            if (blank($fields['description'])) {
                $warnings[] = 'The poster requirements could not be confidently detected. Review the description before saving.';
            }

            if (collect([
                $fields['initial_evaluation_start_date'],
                $fields['paper_revisions_start_date'],
                $fields['lrec_start_date'],
                $fields['implementation_start_date'],
            ])->filter()->isEmpty()) {
                $warnings[] = 'No workflow milestones were confidently detected. Add them manually if the poster includes important dates.';
            }

            if (blank($extraction['transcription'])) {
                $warnings[] = 'The reader could not provide a reviewable poster transcription. Verify every suggested field against the image.';
            }

            $fieldSummary = $this->imageExtractionFieldSummary($fields);
            $submissionWindowHasEnded = $this->submissionWindowHasEnded($fields['closes_at']);

            return response()->json([
                'fields' => $fields,
                'detected_fields' => $fieldSummary['detected'],
                'missing_fields' => $fieldSummary['missing'],
                'transcription' => $extraction['transcription'],
                'warnings' => $warnings,
                'schedule_is_expired' => $submissionWindowHasEnded,
                'expired_schedule_warning' => $submissionWindowHasEnded
                    ? 'This poster’s submission window has already ended. Update the schedule before publishing.'
                    : null,
            ]);
        } catch (ResearchCallImageExtractionException $exception) {
            Log::warning('Research call image extraction failed.', [
                'exception' => $exception::class,
                'message' => $exception->getPrevious()?->getMessage() ?? $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{detected: list<string>, missing: list<string>}
     */
    private function imageExtractionFieldSummary(array $fields): array
    {
        $fieldGroups = [
            ['label' => 'Call name', 'fields' => ['title']],
            ['label' => 'Academic year', 'fields' => ['academic_year']],
            ['label' => 'Term / semester (optional)', 'fields' => ['term']],
            ['label' => 'Submission window', 'fields' => ['opens_at', 'closes_at']],
            ['label' => 'Initial evaluation (optional)', 'fields' => ['initial_evaluation_start_date']],
            ['label' => 'Paper revisions (optional)', 'fields' => ['paper_revisions_start_date']],
            ['label' => 'Tentative LREC (optional)', 'fields' => ['lrec_start_date']],
            ['label' => 'Implementation (optional)', 'fields' => ['implementation_start_date']],
            ['label' => 'Description / guidelines', 'fields' => ['description']],
        ];

        $detected = [];
        $missing = [];

        foreach ($fieldGroups as $fieldGroup) {
            $hasEveryField = collect($fieldGroup['fields'])
                ->every(fn (string $field): bool => filled($fields[$field] ?? null));

            if ($hasEveryField) {
                $detected[] = $fieldGroup['label'];
            } else {
                $missing[] = $fieldGroup['label'];
            }
        }

        if (filled($fields['maximum_budget'] ?? null)) {
            $detected[] = 'Poster budget';
        }

        return [
            'detected' => $detected,
            'missing' => $missing,
        ];
    }

    private function submissionWindowHasEnded(?string $closesAt): bool
    {
        if (blank($closesAt)) {
            return false;
        }

        return Carbon::parse($closesAt, 'Asia/Manila')->lessThanOrEqualTo(now('Asia/Manila'));
    }

    public function sourceImage(Request $request, ResearchCall $researchCall): StreamedResponse
    {
        abort_unless(
            $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
                || $researchCall->status !== 'draft',
            404,
        );
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
        $wasPublished = $researchCall->status === 'open';

        if ($validated['status'] === 'open' && $researchCall->closes_at->lessThanOrEqualTo(now('Asia/Manila'))) {
            return back()->withErrors([
                'status' => 'This call cannot be reopened because its submission end date has passed.',
            ]);
        }

        $attributes = ['status' => $validated['status']];

        if ($validated['status'] === 'open' && ! $wasPublished) {
            $attributes['opening_reminder_sent_at'] = null;
            $attributes['faculty_open_notification_sent_at'] = null;
        }

        $researchCall->update($attributes);

        if ($researchCall->isAcceptingSubmissions()) {
            $this->openingNotifications->process($researchCall, includeReminder: false);
        }

        $message = match ($validated['status']) {
            'open' => 'Research call published. It will accept submissions only during its configured date range.',
            'closed' => 'Research call closed. New proposal submissions are no longer accepted.',
            default => 'Research call moved to draft.',
        };

        return back()->with('success', $message);
    }
}
