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
        Schema::create('rms_quarterly_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('reporting_year');
            $table->unsignedTinyInteger('reporting_quarter');
            $table->string('campus')->default('ARASOF-Nasugbu');
            $table->json('signatories')->nullable();
            $table->json('row_details')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['reporting_year', 'reporting_quarter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rms_quarterly_reports');
    }
};
