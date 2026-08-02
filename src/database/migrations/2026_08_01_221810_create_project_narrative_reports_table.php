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
        Schema::create('project_narrative_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->date('submission_date');
            $table->string('tracking_number')->nullable();
            $table->text('researchers');
            $table->date('implementation_start');
            $table->date('implementation_end');
            $table->decimal('budget', 12, 2);
            $table->string('funding_agency');
            $table->longText('accomplishment_summary');
            $table->longText('introduction');
            $table->longText('objectives');
            $table->longText('methodology');
            $table->longText('results_discussion');
            $table->json('photos');
            $table->date('prepared_by_date_signed')->nullable();
            $table->string('review_status')->default('pending')->index();
            $table->text('research_head_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['topic_id', 'submission_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_narrative_reports');
    }
};
