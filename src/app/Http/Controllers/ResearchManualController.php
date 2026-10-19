<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResearchManualRequest;
use App\Models\ResearchManual;
use App\Models\User;
use App\Services\ResearchManualChecks;
use App\Services\ResearchManualComparison;
use App\Services\ResearchManualLibrary;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResearchManualController extends Controller
{
    public function __construct(private ResearchManualLibrary $library) {}

    public function index(Request $request): View
    {
        $this->authorizeManager($request);
        $current = $this->library->active();
        $legacy = null;
        $manualError = null;
        $manualReader = null;

        try {
            if ($current === null) {
                $legacy = $this->library->activeIndex();
            }

            $version = $current?->version ?? $legacy['version'] ?? null;
            $resolved = $version !== null ? $this->library->resolveVersion($version) : null;
            $manualReader = $resolved !== null ? $this->library->readerConfig($resolved['index'], $resolved['manual'], true) : null;
        } catch (RuntimeException $exception) {
            $manualError = $exception->getMessage();
        }

        return view('research-manuals.index', [
            'manuals' => ResearchManual::with('uploadedBy')->latest()->paginate(15),
            'currentManual' => $current,
            'legacyManual' => $legacy,
            'manualError' => $manualError,
            'manualReader' => $manualReader,
        ]);
    }

    public function store(StoreResearchManualRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        try {
            $manual = $this->library->upload($request->file('manual')->getRealPath(), $validated['title'], $validated['edition'], $validated['effective_date'], $request->user());
        } catch (QueryException $exception) {
            report($exception);

            throw ValidationException::withMessages(['manual' => 'The manual could not be saved. Try again or contact the administrator. The active manual has not changed.']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['manual' => $exception->getMessage()]);
        }

        return redirect()->route('research-manuals.show', $manual)->with('success', 'Manual uploaded. Automated guidance checks have been queued. Review the results and extracted passages before activation.');
    }

    public function show(Request $request, ResearchManual $researchManual, ResearchManualComparison $comparison, ResearchManualChecks $checks): View|RedirectResponse|JsonResponse
    {
        $this->authorizeManager($request);

        if ($researchManual->activated_at === null) {
            $checks->queue($researchManual);
        }
        if ($request->expectsJson()) {
            return response()->json([
                'status' => $researchManual->guidance_check_status,
                'html' => view('components.research-manual-checks', ['manual' => $researchManual])->render(),
            ])->header('Cache-Control', 'private, no-store');
        }

        try {
            $index = $this->library->index($researchManual);
        } catch (RuntimeException $exception) {
            return redirect()->route('research-manuals.index')->withErrors(['manual' => $exception->getMessage()]);
        }

        $currentReader = null;
        $comparisonError = null;
        $compared = null;
        $isActive = false;
        try {
            $current = $this->library->activeIndex();
            $isActive = ($current['version'] ?? null) === $researchManual->version;
            if ($current !== null && ! $isActive) {
                $resolved = $this->library->resolveVersion($current['version']);
                if ($resolved === null) {
                    throw new RuntimeException('The current manual could not be loaded for comparison.');
                }
                $currentReader = [...$this->library->readerConfig($resolved['index'], $resolved['manual']), 'read_hash' => false];
                $compared = $comparison->compare($resolved['index'], $index);
            }
        } catch (RuntimeException $exception) {
            $comparisonError = $exception->getMessage();
        }
        $changes = $compared['changes'] ?? [];
        $page = max(1, min((int) $request->query('comparison_page', 1), max(1, (int) ceil(count($changes) / 8))));
        $reviewSections = array_values(array_unique(array_column(array_filter($changes, fn (array $change): bool => $change['after'] !== null), 'section')));

        return view('research-manuals.show', [
            'manual' => $researchManual->load('uploadedBy'),
            'index' => $index,
            'isActive' => $isActive,
            'manualReader' => $this->library->readerConfig($index, $researchManual, true, $reviewSections),
            'currentReader' => $currentReader,
            'comparisonError' => $comparisonError,
            'comparison' => $compared,
            'comparisonCounts' => array_count_values(array_column($changes, 'type')),
            'comparisonChanges' => new LengthAwarePaginator(array_slice($changes, ($page - 1) * 8, 8), count($changes), 8, $page, [
                'path' => $request->url(), 'pageName' => 'comparison_page', 'fragment' => 'manual-comparison',
            ]),
        ]);
    }

    public function activate(Request $request, ResearchManual $researchManual): RedirectResponse
    {
        $this->authorizeManager($request);
        $request->validate([
            'review_confirmation' => ['required', 'accepted'],
            'acknowledge_unindexed_pages' => [Rule::when($researchManual->unindexed_pages !== [], ['required', 'accepted'])],
        ]);
        try {
            $this->library->activate($researchManual);
        } catch (QueryException $exception) {
            report($exception);

            throw ValidationException::withMessages(['manual' => 'Activation could not be saved. Try again or contact the administrator. The previous active manual has been retained.']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['manual' => $exception->getMessage()]);
        }

        return redirect()->route('research-manuals.show', $researchManual)->with('success', 'This edition is now the active research manual. Existing proposals retain their assigned edition.');
    }

    public function download(Request $request, ResearchManual $researchManual): BinaryFileResponse
    {
        $this->authorizeManager($request);
        abort_unless(is_file($researchManual->pdfPath()), 404);

        return response()->download($researchManual->pdfPath(), 'research-manual-'.$researchManual->getKey().'.pdf', [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->hasRole('research_head')
            && $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
    }
}
