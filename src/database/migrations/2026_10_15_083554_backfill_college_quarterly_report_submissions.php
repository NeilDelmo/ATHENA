<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('college_quarterly_reports')->whereNotNull('submitted_at')->orderBy('id')
            ->chunkById(100, function ($reports): void {
                foreach ($reports as $report) {
                    DB::table('college_quarterly_report_submissions')->insertOrIgnore([
                        'report_id' => $report->id, 'version_number' => 1, 'submitted_by' => $report->prepared_by,
                        'snapshot' => $report->snapshot ?? '{}', 'submitted_at' => $report->submitted_at,
                        'created_at' => $report->submitted_at, 'updated_at' => $report->submitted_at,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Submission history is retained; rolling back the preceding table migration removes it.
    }
};
