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
        Schema::create('research_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->nullable()->unique()->constrained('topics')->cascadeOnDelete();
            $table->foreignId('leader_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('team_members')->nullable();
            $table->string('funding_source', 20);
            $table->json('labels');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_outcomes');
    }
};
