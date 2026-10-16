<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnCollegeQuarterlyReportRequest;
use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResearchHeadQuarterlyReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);
        $year = (int) ($validated['year'] ?? now()->year);
        $reports = CollegeQuarterlyReport::where('reporting_year', $year)->whereHas('submissions')
            ->with(['latestSubmission.submittedBy', 'latestSubmission.returnedBy', 'submissions' => fn ($query) => $query->orderByDesc('version_number')])
            ->orderByDesc('reporting_quarter')->orderBy('college')->paginate(12)->withQueryString();

        return view('research_head.quarterly-reports.index', compact('reports', 'year'));
    }

    public function returnForCorrection(ReturnCollegeQuarterlyReportRequest $request, CollegeQuarterlyReport $report): RedirectResponse
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

        return redirect()->route('research_head.quarterly-reports.index', ['year' => $report->reporting_year])
            ->with('status', 'Quarterly report returned to the coordinator for correction.');
    }
}
