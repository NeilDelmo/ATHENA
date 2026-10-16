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
        Schema::create('college_quarterly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('college', 191);
            $table->unsignedSmallInteger('reporting_year');
            $table->unsignedTinyInteger('reporting_quarter');
            $table->foreignId('prepared_by')->constrained('users');
            $table->json('seminars')->nullable();
            $table->text('other_accomplishments')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->string('reviewed_by_title')->nullable();
            $table->json('evidence')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['college', 'reporting_year', 'reporting_quarter'], 'college_quarterly_reports_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('college_quarterly_reports');
    }
};
