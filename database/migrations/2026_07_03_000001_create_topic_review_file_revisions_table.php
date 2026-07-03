<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_review_file_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_review_id')->constrained('topic_reviews')->cascadeOnDelete();
            $table->foreignId('proposal_version_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resolved_by_version_file_id')->nullable()->constrained('proposal_version_files')->nullOnDelete();
            $table->string('document_type');
            $table->string('original_filename');
            $table->text('revision_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['topic_review_id', 'resolved_at']);
            $table->index(['document_type', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_review_file_revisions');
    }
};
