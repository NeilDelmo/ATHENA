<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('reporting_date');
            $table->json('work_plan')->nullable()->after('issues');
            $table->json('budget_utilization')->nullable()->after('work_plan');
            $table->date('prepared_by_date_signed')->nullable()->after('budget_utilization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_number',
                'work_plan',
                'budget_utilization',
                'prepared_by_date_signed',
            ]);
        });
    }
};
