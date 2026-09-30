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
        Schema::create('research_annual_targets', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year', 50)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('projects_target')->nullable();
            $table->unsignedInteger('publications_target')->nullable();
            $table->unsignedInteger('faculty_target')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_annual_targets');
    }
};
