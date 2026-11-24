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
        Schema::create('proposal_draft_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_draft_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->json('member_ids');
            $table->json('approved_member_ids');
            $table->timestamps();

            $table->index(['proposal_draft_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposal_draft_deletion_requests');
    }
};
