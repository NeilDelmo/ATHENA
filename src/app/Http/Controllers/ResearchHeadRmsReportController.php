<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRmsQuarterlyReportRequest;
use App\Models\CollegeQuarterlyReport;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use App\Services\MonitoringQuarterService;
use App\Services\RmsQuarterlyReportData;
use App\Services\RmsQuarterlyReportDocument;
use App\Services\RmsQuarterlyReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResearchHeadRmsReportController extends Controller
{
    public function index(Request $request, RmsQuarterlyReportData $data, MonitoringQuarterService $quarters, RmsQuarterlyReportStatus $status): View
    {
        [$year, $quarter] = $this->reportingPeriod($request);
        if (! $request->filled('quarter')) {
            $submittedReports = CollegeQuarterlyReport::query()->whereNotNull('submitted_at')
                ->whereDoesntHave('latestSubmission', fn (Builder $query) => $query->whereNotNull('returned_at'));
            $years = (clone $submittedReports)->distinct()->pluck('reporting_year')
                ->merge(RmsQuarterlyReport::distinct()->pluck('reporting_year'))->push(now()->year)->push($year)->unique()->sortDesc()->values();
            $selectedYear = $request->filled('year') ? $year : null;
            $reportYears = $selectedYear !== null ? collect([$selectedYear]) : $years;
            $drafts = RmsQuarterlyReport::whereIn('reporting_year', $reportYears)
                ->get(['reporting_year', 'reporting_quarter', 'updated_at', 'campus', 'signatories', 'row_details', 'downloaded_at', 'downloaded_fingerprint'])
                ->keyBy(fn (RmsQuarterlyReport $report): string => $report->reporting_year.'-'.$report->reporting_quarter);
            $statuses = $status->forYears($reportYears->all(), $drafts);

            return view('research_head.rms-quarterly-reports.library', compact('years', 'selectedYear', 'reportYears', 'drafts', 'statuses'));
        }
        $report = RmsQuarterlyReport::where('reporting_year', $year)->where('reporting_quarter', $quarter)->first();
        $reportData = $data->build($year, $quarter, $report?->row_details ?? []);
        $period = $quarters->forYearAndQuarter($year, $quarter);
        $reportStatus = $status->forPeriod($year, $quarter, $report);

        return view('research_head.rms-quarterly-reports.index', compact('year', 'quarter', 'report', 'reportData', 'period', 'reportStatus'));
    }

    public function store(SaveRmsQuarterlyReportRequest $request, RmsQuarterlyReportData $data, RmsQuarterlyReportStatus $status): RedirectResponse
    {
        $validated = $request->validated();
        $year = (int) $validated['reporting_year'];
        $quarter = (int) $validated['reporting_quarter'];
        $report = DB::transaction(function () use ($request, $validated, $year, $quarter, $data): RmsQuarterlyReport {
            CollegeQuarterlyReport::where('reporting_year', $year)->where('reporting_quarter', $quarter)
                ->orderBy('id')->lockForUpdate()->get(['id']);
            $report = RmsQuarterlyReport::firstOrCreate(['reporting_year' => $year, 'reporting_quarter' => $quarter]);
            $report = RmsQuarterlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $reportData = $data->build($year, $quarter);
            if (! hash_equals($reportData['source_token'], $validated['source_token'])) {
                throw ValidationException::withMessages(['source_token' => 'College submissions changed while you were editing. Review the refreshed records before saving.']);
            }
            $keys = collect(RmsQuarterlyReportData::FIELDS)->keys()->flatMap(fn (string $section) => array_column($reportData[$section], 'key'))->all();
            if (array_diff(array_keys($validated['row_details'] ?? []), $keys) !== []) {
                throw ValidationException::withMessages(['row_details' => 'Some report rows no longer match the submitted college reports. Review the refreshed records before saving.']);
            }
            $report->update([
                'campus' => $validated['campus'], 'signatories' => $validated['signatories'] ?? [],
                'row_details' => $validated['row_details'] ?? [], 'updated_by' => $request->user()->id,
            ]);

            return $report;
        });
        if (($validated['intent'] ?? 'save') === 'download' && ! $status->forPeriod($year, $quarter, $report)['ready']) {
            return $this->downloadsPending($request, $year, $quarter)->with('status', 'RMS draft saved. Excel download is waiting for all college reports.');
        }

        return redirect()->route(($validated['intent'] ?? 'save') === 'download' ? 'research_head.rms-quarterly-reports.download' : 'research_head.rms-quarterly-reports.index', ['year' => $year, 'quarter' => $quarter])
            ->with('status', 'RMS quarterly report draft saved.');
    }

    public function download(Request $request, RmsQuarterlyReportData $data, RmsQuarterlyReportDocument $document, RmsQuarterlyReportStatus $status): Response|RedirectResponse
    {
        [$year, $quarter] = $this->reportingPeriod($request);
        if (! $status->forPeriod($year, $quarter)['ready']) {
            return $this->downloadsPending($request, $year, $quarter);
        }
        $content = DB::transaction(function () use ($year, $quarter, $data, $document, $status): string {
            // Keep submissions and corrections stable while generating this workbook.
            CollegeQuarterlyReport::where('reporting_year', $year)->where('reporting_quarter', $quarter)
                ->orderBy('id')->lockForUpdate()->get(['id']);
            $report = RmsQuarterlyReport::firstOrCreate(['reporting_year' => $year, 'reporting_quarter' => $quarter]);
            $report = RmsQuarterlyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $reportStatus = $status->forPeriod($year, $quarter, $report);
            if (! $reportStatus['ready']) {
                throw ValidationException::withMessages(['college_reports' => 'Excel download is disabled until all college reports for this quarter have been submitted.'])
                    ->redirectTo(route('research_head.rms-quarterly-reports.index', compact('year', 'quarter')));
            }
            $reportData = $data->build($year, $quarter, $report->row_details ?? []);
            $content = $document->generate($reportData, [
                'year' => $year, 'quarter' => $quarter, 'campus' => $report->campus, 'signatories' => $report->signatories ?? [],
            ]);
            $report->timestamps = false;
            $report->forceFill(['downloaded_at' => now(), 'downloaded_fingerprint' => $status->fingerprint($reportStatus, $report)])->save();

            return $content;
        });

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="RMS-Quarterly-Monitoring-Report-'.$year.'-Q'.$quarter.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function downloadsPending(Request $request, int $year, int $quarter): RedirectResponse
    {
        $errors = ['college_reports' => 'Excel download is disabled until all college reports for this quarter have been submitted.'];
        if ($request->expectsJson()) {
            throw ValidationException::withMessages($errors);
        }

        return redirect()->route('research_head.rms-quarterly-reports.index', compact('year', 'quarter'))->withErrors($errors);
    }

    /** @return array{int, int} */
    private function reportingPeriod(Request $request): array
    {
        abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'quarter' => ['nullable', 'integer', 'between:1,4']]);

        return [(int) ($validated['year'] ?? now()->year), (int) ($validated['quarter'] ?? now()->quarter)];
    }
}
