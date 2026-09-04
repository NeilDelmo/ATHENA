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
        Schema::create('proposal_file_review_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_version_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->unique(
                ['proposal_version_file_id', 'reviewer_id'],
                'proposal_file_review_checks_file_reviewer_unique',
            );
            $table->index(
                ['reviewer_id', 'reviewed_at'],
                'proposal_file_review_checks_reviewer_reviewed_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_file_review_checks');
    }
};
