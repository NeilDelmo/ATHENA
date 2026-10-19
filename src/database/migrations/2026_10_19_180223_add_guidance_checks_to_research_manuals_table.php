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
        Schema::table('research_manuals', function (Blueprint $table) {
            $table->string('guidance_check_status', 20)->nullable();
            $table->json('guidance_check_results')->nullable();
            $table->timestamp('guidance_check_started_at')->nullable();
            $table->timestamp('guidance_check_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_manuals', function (Blueprint $table) {
            $table->dropColumn(['guidance_check_status', 'guidance_check_results', 'guidance_check_started_at', 'guidance_check_completed_at']);
        });
    }
};
