<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->string('project_status')->nullable()->after('status');
        });

        Schema::create('project_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->date('reporting_date');
            $table->unsignedTinyInteger('progress_percentage');
            $table->text('accomplishments');
            $table->text('issues')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('review_status')->default('pending');
            $table->text('research_head_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['topic_id', 'reporting_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_progress_reports');

        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('project_status');
        });
    }
};
