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
        Schema::create('project_monitoring_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_report_id')
                ->nullable()
                ->constrained('project_progress_reports')
                ->nullOnDelete();
            $table->string('source_key');
            $table->json('source_data');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['topic_id', 'user_id', 'source_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_monitoring_drafts');
    }
};
