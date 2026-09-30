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
        Schema::create('project_journal_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('journal_name');
            $table->string('issn', 9)->nullable();
            $table->string('journal_url', 2000)->nullable();
            $table->string('manuscript_title', 500);
            $table->string('status', 32)->default('shortlisted');
            $table->string('submission_reference')->nullable();
            $table->date('submitted_on')->nullable();
            $table->date('accepted_on')->nullable();
            $table->date('published_on')->nullable();
            $table->string('publication_url', 2000)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['topic_id', 'fingerprint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_journal_submissions');
    }
};
