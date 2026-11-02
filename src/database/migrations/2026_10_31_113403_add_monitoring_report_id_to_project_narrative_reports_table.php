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
        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->foreignId('monitoring_report_id')->nullable()->constrained('project_progress_reports')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('monitoring_report_id');
        });
    }
};
