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
        Schema::create('proposal_draft_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamps();

            $table->unique(['proposal_draft_id', 'user_id'], 'proposal_draft_member_user_unique');
            $table->unique(['proposal_draft_id', 'email'], 'proposal_draft_member_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_draft_members');
    }
};
