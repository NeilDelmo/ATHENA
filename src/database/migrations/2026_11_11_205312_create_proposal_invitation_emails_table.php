<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_invitation_emails', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('draft_member_id')->nullable()->unique()->constrained('proposal_draft_members')->cascadeOnDelete();
            $table->foreignId('topic_collaborator_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('attempt')->nullable();
            $table->string('status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_invitation_emails');
    }
};
