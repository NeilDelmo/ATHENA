<?php

namespace App\Actions;

use App\Models\CollegeQuarterlyReport;
use App\Models\RmsQuarterlyReport;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\RmsQuarterlyReportStatus;
use Illuminate\Support\Facades\DB;

class NotifyRmsQuarterlyReportStatus
{
    public function __construct(private readonly RmsQuarterlyReportStatus $status) {}

    public function handle(int $year, int $quarter): void
    {
        DB::afterCommit(function () use ($year, $quarter): void {
            DB::transaction(function () use ($year, $quarter): void {
                // Read status after locking the same source rows used by submission, correction, and export.
                CollegeQuarterlyReport::where('reporting_year', $year)->where('reporting_quarter', $quarter)
                    ->orderBy('id')->lockForUpdate()->get(['id']);
                $report = RmsQuarterlyReport::where('reporting_year', $year)->where('reporting_quarter', $quarter)->lockForUpdate()->first();
                $status = $this->status->forPeriod($year, $quarter, $report);
                if (! $status['ready'] && ! $status['outdated']) {
                    return;
                }

                $periodKey = $year.'-Q'.$quarter;
                $period = 'Q'.$quarter.' '.$year;
                $statusToken = $status['source_token'].':'.($status['ready'] ? 'ready' : 'pending');
                [$title, $message, $level] = match (true) {
                    ! $status['ready'] => ['RMS report awaiting corrected submissions',
                        $period.' is awaiting corrected college submissions. The previous RMS download is out of date. Download a fresh workbook once all '.$status['total_count'].' colleges have submitted.', 'warning'],
                    $status['outdated'] => ['RMS report updated since download',
                        $period.' has updated college submissions. Download a fresh RMS workbook before using or sharing the previous export.', 'warning'],
                    default => ['All college reports received',
                        'All '.$status['total_count'].' college reports for '.$period.' have current submissions. Review the report and download its Excel workbook.', 'success'],
                };

                foreach (User::role('research_head')->orderBy('id')->get(['id']) as $recipient) {
                    $head = User::query()->lockForUpdate()->find($recipient->id);
                    if ($head === null || $head->notifications()->where('type', ProposalActivityNotification::class)
                        ->where('data->action_data->rms_period_key', $periodKey)
                        ->where('data->action_data->rms_status_token', $statusToken)->exists()) {
                        continue;
                    }

                    $head->notify(new ProposalActivityNotification(
                        title: $title,
                        message: $message,
                        url: route('research_head.rms-quarterly-reports.index', ['year' => $year, 'quarter' => $quarter]),
                        level: $level,
                        actionData: ['rms_period_key' => $periodKey, 'rms_status_token' => $statusToken],
                        workspace: User::WORKSPACE_RESEARCH_HEAD,
                        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_RMS_REPORTS,
                    ));
                }
            }, 3);
        });
    }
}
