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
            $table->unsignedSmallInteger('reporting_year')->nullable()->after('reporting_date');
            $table->unsignedTinyInteger('reporting_quarter')->nullable()->after('reporting_year');
            $table->unsignedSmallInteger('version_number')->default(1)->after('reporting_quarter');
            $table->foreignId('supersedes_report_id')
                ->nullable()
                ->after('version_number')
                ->constrained('project_progress_reports');

            $table->index(
                ['topic_id', 'reporting_year', 'reporting_quarter'],
                'progress_reports_quarter_index',
            );
            $table->unique(
                ['topic_id', 'reporting_year', 'reporting_quarter', 'version_number'],
                'progress_reports_quarter_version_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->dropUnique('progress_reports_quarter_version_unique');
            $table->dropIndex('progress_reports_quarter_index');
            $table->dropForeign(['supersedes_report_id']);
            $table->dropColumn([
                'reporting_year',
                'reporting_quarter',
                'version_number',
                'supersedes_report_id',
            ]);
        });
    }
};
