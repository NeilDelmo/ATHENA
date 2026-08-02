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
        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->json('accomplishments')->nullable()->after('accomplishment_summary');
            $table->longText('rationale')->nullable()->after('introduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_narrative_reports', function (Blueprint $table) {
            $table->dropColumn(['accomplishments', 'rationale']);
        });
    }
};
