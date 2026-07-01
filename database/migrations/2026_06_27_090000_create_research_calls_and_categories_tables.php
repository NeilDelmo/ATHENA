<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('research_calls', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('academic_year');
            $table->string('term')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->unsignedSmallInteger('max_proposals_per_faculty')->default(2);
            $table->decimal('maximum_budget', 12, 2)->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'opens_at', 'closes_at']);
        });

        Schema::create('research_call_category', function (Blueprint $table) {
            $table->foreignId('research_call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['research_call_id', 'research_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_call_category');
        Schema::dropIfExists('research_calls');
        Schema::dropIfExists('research_categories');
    }
};
