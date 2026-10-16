<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRmsQuarterlyReportRequest;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use App\Services\MonitoringQuarterService;
use App\Services\RmsQuarterlyReportData;
use App\Services\RmsQuarterlyReportDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResearchHeadRmsReportController extends Controller
{
    public function index(Request $request, RmsQuarterlyReportData $data, MonitoringQuarterService $quarters): View
    {
        [$year, $quarter] = $this->reportingPeriod($request);
        $report = RmsQuarterlyReport::where('reporting_year', $year)->where('reporting_quarter', $quarter)->first();
        $reportData = $data->build($year, $quarter, $report?->row_details ?? []);
        $period = $quarters->forYearAndQuarter($year, $quarter);

        return view('research_head.rms-quarterly-reports.index', compact('year', 'quarter', 'report', 'reportData', 'period'));
    }

    public function store(SaveRmsQuarterlyReportRequest $request, RmsQuarterlyReportData $data): RedirectResponse
    {
        $validated = $request->validated();
        $year = (int) $validated['reporting_year'];
        $quarter = (int) $validated['reporting_quarter'];
        $reportData = $data->build($year, $quarter);
        if (! hash_equals($reportData['source_token'], $validated['source_token'])) {
            throw ValidationException::withMessages(['source_token' => 'College submissions changed while you were editing. Review the refreshed records before saving.']);
        }
        $keys = collect(RmsQuarterlyReportData::FIELDS)->keys()->flatMap(fn (string $section) => array_column($reportData[$section], 'key'))->all();
        if (array_diff(array_keys($validated['row_details'] ?? []), $keys) !== []) {
            throw ValidationException::withMessages(['row_details' => 'Some report rows no longer match the submitted college reports. Review the refreshed records before saving.']);
        }
        RmsQuarterlyReport::updateOrCreate(['reporting_year' => $year, 'reporting_quarter' => $quarter], [
            'campus' => $validated['campus'], 'signatories' => $validated['signatories'] ?? [],
            'row_details' => $validated['row_details'] ?? [], 'updated_by' => $request->user()->id,
        ]);

        return redirect()->route(($validated['intent'] ?? 'save') === 'download' ? 'research_head.rms-quarterly-reports.download' : 'research_head.rms-quarterly-reports.index', ['year' => $year, 'quarter' => $quarter])
            ->with('status', 'Quarterly monitoring draft saved.');
    }

    public function download(Request $request, RmsQuarterlyReportData $data, RmsQuarterlyReportDocument $document): Response
    {
        [$year, $quarter] = $this->reportingPeriod($request);
        $report = RmsQuarterlyReport::where('reporting_year', $year)->where('reporting_quarter', $quarter)->first();
        $reportData = $data->build($year, $quarter, $report?->row_details ?? []);

        return response($document->generate($reportData, [
            'year' => $year, 'quarter' => $quarter, 'campus' => $report?->campus ?? 'ARASOF-Nasugbu', 'signatories' => $report?->signatories ?? [],
        ]), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="RMS-Quarterly-Monitoring-Report-'.$year.'-Q'.$quarter.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{int, int} */
    private function reportingPeriod(Request $request): array
    {
        abort_unless($request->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $validated = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'quarter' => ['nullable', 'integer', 'between:1,4']]);

        return [(int) ($validated['year'] ?? now()->year), (int) ($validated['quarter'] ?? now()->quarter)];
    }
}
