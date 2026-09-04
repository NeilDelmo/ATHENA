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
        Schema::create('harvested_literature_sources', function (Blueprint $table) {
            $table->id();
            $table->string('repository_key', 64)->index();
            $table->char('url_hash', 64)->unique();
            $table->text('url');
            $table->char('fingerprint', 64)->nullable()->index();
            $table->string('title', 500)->nullable();
            $table->text('authors')->nullable();
            $table->text('abstract')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('doi')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('harvested_at')->nullable();
            $table->timestamp('next_harvest_at')->nullable();
            $table->timestamps();
            $table->index(['repository_key', 'next_harvest_at'], 'harvest_repository_due_index');
            $table->index(['status', 'publication_year'], 'harvest_status_year_index');
            $table->fullText(['title', 'authors', 'abstract'], 'harvest_metadata_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvested_literature_sources');
    }
};
