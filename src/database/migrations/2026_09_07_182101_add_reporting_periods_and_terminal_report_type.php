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
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
        });
        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->string('report_type', 20)->default('progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_progress_reports', fn (Blueprint $table) => $table->dropColumn(['period_start', 'period_end']));
        Schema::table('project_narrative_reports', fn (Blueprint $table) => $table->dropColumn('report_type'));
    }
};
