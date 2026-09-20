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
        Schema::table('topics', function (Blueprint $table) {
            $table->foreignId('research_secretary_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->foreignId('budget_prepared_by')->nullable()->after('budget_utilization')->constrained('users')->nullOnDelete();
            $table->timestamp('budget_prepared_at')->nullable()->after('budget_prepared_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_progress_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_prepared_by');
            $table->dropColumn('budget_prepared_at');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('research_secretary_id');
        });
    }
};
