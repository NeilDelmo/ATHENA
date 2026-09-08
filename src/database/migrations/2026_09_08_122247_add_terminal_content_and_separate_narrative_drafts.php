<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->json('terminal_data')->nullable();
        });
        Schema::table('project_narrative_report_drafts', function (Blueprint $table) {
            $table->string('report_type', 20)->default('progress');
            $table->unique(['topic_id', 'user_id', 'report_type'], 'narrative_draft_project_user_type_unique');
        });
        Schema::table('project_narrative_report_drafts', function (Blueprint $table) {
            $table->dropUnique(['topic_id', 'user_id']);
        });
        DB::table('project_narrative_report_drafts')->where('source_data->report_type', 'terminal')->update(['report_type' => 'terminal']);
    }

    public function down(): void
    {
        // Keep the separate draft records: merging them would discard researchers' work.
        if (DB::table('project_narrative_report_drafts')->select('topic_id', 'user_id')->groupBy('topic_id', 'user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Resolve separate progress and terminal drafts before rolling back this migration.');
        }
        Schema::table('project_narrative_report_drafts', function (Blueprint $table) {
            $table->unique(['topic_id', 'user_id']);
        });
        Schema::table('project_narrative_report_drafts', function (Blueprint $table) {
            $table->dropUnique('narrative_draft_project_user_type_unique');
            $table->dropColumn('report_type');
        });
        Schema::table('project_narrative_reports', fn (Blueprint $table) => $table->dropColumn('terminal_data'));
    }
};
