<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_conferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users');
            $table->string('fingerprint', 64);
            $table->string('title', 500);
            $table->text('url');
            $table->text('official_url')->nullable();
            $table->string('source')->default('Researcher entry');
            $table->string('location', 500)->nullable();
            $table->date('submission_deadline')->nullable();
            $table->date('event_date')->nullable();
            $table->string('attendance_mode')->nullable();
            $table->string('fees', 500)->nullable();
            $table->text('publication_details')->nullable();
            $table->timestamp('source_checked_at')->nullable();
            $table->string('status')->default('shortlisted');
            $table->date('submitted_on')->nullable();
            $table->date('accepted_on')->nullable();
            $table->date('presented_on')->nullable();
            $table->text('evidence_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['topic_id', 'fingerprint']);
        });

        Schema::create('researcher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('openalex_id')->nullable();
            $table->string('display_name', 500)->nullable();
            $table->text('affiliation')->nullable();
            $table->string('orcid')->nullable();
            $table->text('scholar_url')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('research_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->string('openalex_id')->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('title', 1000);
            $table->text('authors');
            $table->string('venue', 500)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('type')->nullable();
            $table->text('url')->nullable();
            $table->string('source')->default('Researcher entry');
            $table->timestamp('source_checked_at')->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->unique(['user_id', 'fingerprint']);
        });

        Schema::create('research_publication_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['research_publication_id', 'topic_id'], 'publication_topic_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_publication_topic');
        Schema::dropIfExists('research_publications');
        Schema::dropIfExists('researcher_profiles');
        Schema::dropIfExists('project_conferences');
    }
};
