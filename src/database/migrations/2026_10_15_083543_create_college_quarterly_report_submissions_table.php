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
        Schema::create('college_quarterly_report_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('college_quarterly_reports')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->foreignId('submitted_by')->constrained('users');
            $table->json('snapshot');
            $table->timestamp('submitted_at');
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('return_reason')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'version_number'], 'college_quarterly_submission_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('college_quarterly_report_submissions');
    }
};
