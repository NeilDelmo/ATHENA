<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['project_progress_reports', 'project_narrative_reports'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('review_stage', 20)->nullable()->index();
                $table->text('secretary_remarks')->nullable();
                $table->foreignId('secretary_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('secretary_reviewed_at')->nullable();
                $table->timestamp('forwarded_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['project_progress_reports', 'project_narrative_reports'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('secretary_reviewed_by');
                $table->dropIndex(['review_stage']);
                $table->dropColumn(['review_stage', 'secretary_remarks', 'secretary_reviewed_at', 'forwarded_at']);
            });
        }
    }
};
