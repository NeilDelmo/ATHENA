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
        Schema::create('proposal_draft_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_draft_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('source_data')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['proposal_draft_id', 'document_type', 'position'],
                'proposal_draft_document_slot_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_draft_documents');
    }
};
