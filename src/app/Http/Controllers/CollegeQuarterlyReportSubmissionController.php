<?php

namespace App\Http\Controllers;

use App\Models\CollegeQuarterlyReportSubmission;
use App\Models\User;
use App\Services\CollegeQuarterlyReportDocument;
use App\Services\MonitoringQuarterService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CollegeQuarterlyReportSubmissionController extends Controller
{
    public function print(Request $request, CollegeQuarterlyReportSubmission $submission, MonitoringQuarterService $quarters): View
    {
        $this->authorizeReader($request, $submission);
        $report = $submission->asReport();
        $year = $report->reporting_year;
        $quarter = $report->reporting_quarter;

        return view('research_coordinator.report-print', [
            'report' => $report, 'submission' => $submission, 'year' => $year, 'quarter' => $quarter,
            'coordinator' => $submission->submittedBy, 'period' => $quarters->forYearAndQuarter($year, $quarter), 'reportData' => $submission->snapshot,
            'backUrl' => $request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
                ? route('research_head.quarterly-reports.index', ['year' => $year, 'college' => $report->college, 'quarter' => $quarter])
                : route('research_coordinator.reports.index', ['year' => $year, 'quarter' => $quarter]),
        ]);
    }

    public function download(Request $request, CollegeQuarterlyReportSubmission $submission, CollegeQuarterlyReportDocument $document, MonitoringQuarterService $quarters): Response
    {
        $this->authorizeReader($request, $submission);
        $report = $submission->asReport();
        $report->evidence = collect($report->evidence ?? [])->map(fn (array $file, int $index): array => [
            ...$file, 'url' => route('college-quarterly-submissions.evidence', ['submission' => $submission, 'index' => $index]),
        ])->all();

        return response($document->generate($submission->submittedBy, $quarters->forYearAndQuarter($report->reporting_year, $report->reporting_quarter), $submission->snapshot, $report), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="College-Quarterly-Accomplishment-Report-'.$report->reporting_year.'-Q'.$report->reporting_quarter.'-v'.$submission->version_number.'.docx"',
        ]);
    }

    public function evidence(Request $request, CollegeQuarterlyReportSubmission $submission, int $index): BinaryFileResponse
    {
        $this->authorizeReader($request, $submission);
        $image = $submission->snapshot['evidence'][$index] ?? null;
        abort_unless(is_array($image) && Storage::disk('local')->exists($image['path']), 404);

        $headers = [
            'Content-Type' => $image['mime_type'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ];

        return in_array($image['mime_type'], ['image/jpeg', 'image/png'], true)
            ? response()->file(Storage::disk('local')->path($image['path']), $headers)
            : response()->download(Storage::disk('local')->path($image['path']), $image['original_filename'], $headers);
    }

    private function authorizeReader(Request $request, CollegeQuarterlyReportSubmission $submission): void
    {
        $user = $request->user();
        abort_unless($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
            || ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE) && $user->hasRole('research_coordinator')
                && filled($user->college) && $user->college === $submission->report->college), 403);
    }
}
