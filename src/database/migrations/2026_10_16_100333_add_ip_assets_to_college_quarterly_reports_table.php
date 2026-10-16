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
        Schema::table('college_quarterly_reports', function (Blueprint $table) {
            $table->json('ip_assets')->nullable()->after('manual_research');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('college_quarterly_reports', function (Blueprint $table) {
            $table->dropColumn('ip_assets');
        });
    }
};
