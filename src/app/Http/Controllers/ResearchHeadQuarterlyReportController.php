<?php

namespace App\Http\Controllers;

use App\Actions\NotifyRmsQuarterlyReportStatus;
use App\Http\Requests\ReturnCollegeQuarterlyReportRequest;
use App\Http\Requests\UpdateCollegeReportLibraryRequest;
use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use App\Services\CollegeReportLibrary;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResearchHeadQuarterlyReportController extends Controller
{
    public function index(Request $request, CollegeReportLibrary $library): View
    {
        abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'college' => ['nullable', 'string', 'max:191'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'folder' => ['nullable', 'string', 'max:200'],
            'sort' => ['sometimes', Rule::in(array_keys(CollegeReportLibrary::SORT_OPTIONS))],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);
        $year = (int) ($validated['year'] ?? now()->year);
        $college = $validated['college'] ?? '';
        $quarter = isset($validated['quarter']) ? (int) $validated['quarter'] : null;
        $browsingYears = ! $request->filled('year') && $college === '';
        $libraryWorkspace = $request->user()->report_library_workspace ?? ['revision' => 0, 'items' => []];
        $librarySources = $library->catalog($year, $libraryWorkspace);
        $libraryNodes = $library->nodes($librarySources, $libraryWorkspace);
        $libraryLocation = $validated['folder'] ?? ($browsingYears ? null : ($college === '' ? 'year:'.$year
            : ($quarter ? 'quarter:'.$year.':'.sha1($college).':'.$quarter : 'college:'.$year.':'.sha1($college))));
        abort_if($libraryLocation !== null && (! $libraryNodes->has($libraryLocation) || $libraryNodes[$libraryLocation]['kind'] !== 'folder'), 404);
        $sortKey = $validated['sort'] ?? $request->session()->get('college_report_library_sort', 'name');
        $sortDirection = $validated['direction'] ?? $request->session()->get('college_report_library_direction', 'asc');
        $request->session()->put(['college_report_library_sort' => $sortKey, 'college_report_library_direction' => $sortDirection]);
        $libraryItems = $library->sort($libraryNodes->where('parent_id', $libraryLocation), $sortKey, $sortDirection);
        $libraryCrumbs = [];
        $parentId = $libraryLocation;
        while ($parentId !== null && $libraryNodes->has($parentId)) {
            $parent = $libraryNodes[$parentId];
            array_unshift($libraryCrumbs, ['label' => $parent['name'], 'url' => $parentId === $libraryLocation ? null : $library->url($parent)]);
            $parentId = $parent['parent_id'];
        }
        $libraryBackUrl = $libraryLocation === null ? null : ($libraryNodes[$libraryLocation]['parent_id'] === null
            ? route('research_head.quarterly-reports.index') : $library->url($libraryNodes[$libraryNodes[$libraryLocation]['parent_id']]));
        $years = CollegeQuarterlyReport::whereHas('submissions')->distinct()->pluck('reporting_year')
            ->push(now()->year)->unique()->sortDesc()->values();
        $collegeReports = CollegeQuarterlyReport::where('reporting_year', $year)->whereHas('submissions')
            ->with(['latestSubmission' => fn (HasOne $relation): HasOne => $relation->select(
                $relation->getRelated()->qualifyColumns(['id', 'report_id', 'returned_at']),
            )])
            ->orderBy('reporting_quarter')->get(['id', 'college', 'reporting_quarter']);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $libraryFiles = $libraryItems->where('kind', 'file')->values();
        $libraryPageFiles = $libraryFiles->forPage($page, 12)->values();
        $reportRecords = CollegeQuarterlyReport::whereKey($libraryPageFiles->pluck('report_id'))->whereHas('submissions')
            ->select(['id', 'college', 'reporting_year', 'reporting_quarter', 'submitted_at'])
            ->with([
                'latestSubmission' => fn (HasOne $relation): HasOne => $relation->select($relation->getRelated()->qualifyColumns([
                    'id', 'report_id', 'version_number', 'submitted_by', 'submitted_at', 'returned_by', 'returned_at', 'return_reason',
                ])),
                'latestSubmission.submittedBy:id,name',
                'latestSubmission.returnedBy:id,name',
                'submissions' => fn ($query) => $query
                    ->select(['id', 'report_id', 'version_number', 'submitted_at', 'returned_at', 'return_reason'])
                    ->orderByDesc('version_number'),
            ])
            ->orderByDesc('reporting_quarter')->orderBy('college')->get();
        $reports = new LengthAwarePaginator($reportRecords, $libraryFiles->count(), 12, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('research_head.quarterly-reports.index', compact('reports', 'year', 'college', 'quarter', 'browsingYears', 'years', 'collegeReports', 'libraryWorkspace', 'librarySources', 'libraryItems', 'libraryPageFiles', 'libraryLocation', 'libraryCrumbs', 'libraryBackUrl', 'library', 'sortKey', 'sortDirection'));
    }

    public function updateWorkspace(UpdateCollegeReportLibraryRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $workspace = $request->validated();
            if ((int) $workspace['revision'] !== (int) ($user->report_library_workspace['revision'] ?? 0)) {
                return response()->json(['message' => 'The report library changed in another tab. Reload before saving again.'], 409);
            }
            $workspace['revision'] = (int) $workspace['revision'] + 1;
            $user->forceFill(['report_library_workspace' => $workspace])->save();

            return response()->json(['workspace' => $workspace]);
        });
    }

    public function returnForCorrection(ReturnCollegeQuarterlyReportRequest $request, CollegeQuarterlyReport $report, NotifyRmsQuarterlyReportStatus $notifyRmsStatus): RedirectResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($request, $report, $validated): void {
            $report = CollegeQuarterlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $submission = $report->latestSubmission;
            if ($report->submitted_at === null || $submission === null || $submission->id !== (int) $validated['submission_id'] || $submission->returned_at !== null) {
                throw ValidationException::withMessages(['submission_id' => 'This submission has changed. Reload the report before returning it for correction.']);
            }
            $submission->update(['returned_by' => $request->user()->id, 'return_reason' => $validated['reason'], 'returned_at' => now()]);
            $report->update([
                'submitted_at' => null,
                'snapshot' => Arr::only($submission->snapshot, ['presentations', 'publications', 'research', 'undated_publications']),
            ]);
        });
        $notifyRmsStatus->handle($report->reporting_year, $report->reporting_quarter);

        return redirect()->route('research_head.quarterly-reports.index', ['year' => $report->reporting_year, 'college' => $report->college, 'quarter' => $report->reporting_quarter])
            ->with('status', 'Quarterly report returned to the coordinator for correction.');
    }
}
