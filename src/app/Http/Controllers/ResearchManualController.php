<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResearchManualRequest;
use App\Models\ResearchManual;
use App\Models\User;
use App\Services\ResearchManualLibrary;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()->route('research-manuals.show', $manual)->with('success', 'Manual uploaded. Review the extracted passages before activation.');
    }

    public function show(Request $request, ResearchManual $researchManual): View|RedirectResponse
    {
        $this->authorizeManager($request);

        try {
            $index = $this->library->index($researchManual);
        } catch (RuntimeException $exception) {
            return redirect()->route('research-manuals.index')->withErrors(['manual' => $exception->getMessage()]);
        }

        return view('research-manuals.show', [
            'manual' => $researchManual->load('uploadedBy'),
            'index' => $index,
            'isActive' => $this->library->active()?->is($researchManual) ?? false,
            'manualReader' => $this->library->readerConfig($index, $researchManual, true),
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
