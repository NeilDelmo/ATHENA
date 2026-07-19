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
        Schema::create('proposal_draft_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_draft_id')
                ->constrained(indexName: 'draft_doc_versions_draft_fk')
                ->cascadeOnDelete();
            $table->foreignId('proposal_draft_document_id')
                ->nullable()
                ->constrained(indexName: 'draft_doc_versions_document_fk')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users', indexName: 'draft_doc_versions_creator_fk')
                ->nullOnDelete();
            $table->string('document_type');
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('version_number');
            $table->boolean('is_current')->default(true);
            $table->json('source_data')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['proposal_draft_id', 'document_type', 'position', 'version_number'],
                'proposal_draft_document_version_unique',
            );
            $table->index(
                ['proposal_draft_id', 'created_at'],
                'draft_doc_versions_draft_created_index',
            );
            $table->index(
                ['proposal_draft_id', 'document_type', 'is_current'],
                'draft_doc_versions_current_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_draft_document_versions');
    }
};
