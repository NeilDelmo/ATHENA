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
        Schema::create('proposal_file_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_version_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('topic_review_file_revision_id')->nullable()->constrained(indexName: 'proposal_file_annotations_revision_fk')->nullOnDelete();
            $table->string('annotation_type', 20);
            $table->unsignedSmallInteger('page_number');
            $table->text('selected_text')->nullable();
            $table->json('rectangles');
            $table->text('comment');
            $table->timestamps();

            $table->index(['proposal_version_file_id', 'page_number'], 'proposal_file_annotations_file_page_index');
            $table->index(['topic_review_file_revision_id', 'created_at'], 'proposal_file_annotations_revision_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_file_annotations');
    }
};
