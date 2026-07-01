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
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('research_call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('estimated_budget', 12, 2);
            $table->unsignedSmallInteger('estimated_duration_months');
            // Legacy pointers retained temporarily for compatibility; proposal_versions is authoritative.
            $table->string('initial_file_path')->nullable();
            $table->string('final_file_path')->nullable();
            $table->string('signed_approval_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['research_call_id', 'user_id']);
            $table->index(['status', 'research_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
