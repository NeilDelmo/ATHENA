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
        Schema::table('rms_quarterly_reports', function (Blueprint $table) {
            $table->timestamp('downloaded_at')->nullable();
            $table->string('downloaded_fingerprint', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rms_quarterly_reports', function (Blueprint $table) {
            $table->dropColumn(['downloaded_at', 'downloaded_fingerprint']);
        });
    }
};
