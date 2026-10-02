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
            $table->date('reporting_date')->nullable();
            $table->unsignedSmallInteger('reporting_quarter')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->index(['topic_id', 'report_type', 'reporting_quarter'], 'narrative_reporting_quarter_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->dropIndex('narrative_reporting_quarter_index');
            $table->dropColumn(['reporting_date', 'reporting_quarter', 'period_start', 'period_end', 'version_number']);
        });
    }
};
