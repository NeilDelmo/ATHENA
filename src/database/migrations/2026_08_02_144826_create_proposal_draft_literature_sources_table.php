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
        Schema::create('proposal_draft_literature_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('fingerprint', 64);
            $table->string('title', 500);
            $table->text('authors')->nullable();
            $table->text('abstract')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('venue', 500)->nullable();
            $table->string('doi')->nullable();
            $table->text('url')->nullable();
            $table->string('provider', 100);
            $table->unsignedInteger('citation_count')->nullable();
            $table->boolean('is_open_access')->default(false);
            $table->string('publication_type', 100)->nullable();
            $table->timestamps();

            $table->unique(['proposal_draft_id', 'fingerprint'], 'proposal_literature_source_unique');
            $table->index(['proposal_draft_id', 'created_at'], 'proposal_literature_draft_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_draft_literature_sources');
    }
};
